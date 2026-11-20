<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\FactWorkHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class FactWorkController extends Controller
{
    /**
     * Определение колонки привязки к заявке
     */
    protected function getAppIdColumn(): string
    {
        $cols = Schema::getColumnListing('fact_works');
        if (in_array('application_id', $cols, true)) {
            return 'application_id';
        }
        if (in_array('id_application', $cols, true)) {
            return 'id_application';
        }
        return 'application_id';
    }

    /**
     * Определение колонки цены в таблице fact_works
     */
    protected function getPriceColumn(): string
    {
        $cols = Schema::getColumnListing('fact_works');
        if (in_array('price', $cols, true)) {
            return 'price';
        }
        if (in_array('price_service', $cols, true)) {
            return 'price_service';
        }
        if (in_array('sum', $cols, true)) {
            return 'sum';
        }
        if (in_array('cost', $cols, true)) {
            return 'cost';
        }
        return 'price';
    }

    /**
     * Определение роли / автора для раскраски:
     * 1 - МРК (голубой)
     * 2 - Наставник (сиреневый)
     * 4 - Сервис / СТО (желтый)
     * 5 - Другой (коричневый)
     */
    protected function resolveAuthorType($user, $app): int
    {
        if (!$user) {
            return 0;
        }

        // 1. Проверка через метод модели User
        if (method_exists($user, 'isSto') && $user->isSto()) {
            return 4;
        }

        $uid = (int) $user->id;
        $gid = (int) ($user->GroupId ?? $user->user_group_id ?? $user->group_id ?? 0);

        if ($app) {
            $mrkId = (int) ($app->id_MRK ?? $app->id_user ?? $app->mrk_id ?? 0);
            $mentorId = (int) ($app->id_mentor ?? $app->mentor_id ?? 0);

            if ($mrkId > 0 && $uid === $mrkId) {
                return 1;
            }
            if ($mentorId > 0 && $uid === $mentorId) {
                return 2;
            }
        }

        if ($gid === 7 || $gid === 8 || ($user->is_service ?? false) || !empty($user->id_service) || !empty($user->service_id)) {
            return 4;
        }

        if ($gid === 5) {
            return 1;
        }
        if ($gid === 6 || $gid === 10) {
            return 2;
        }

        return 5;
    }

    /**
     * Получение названия СТО строго из таблицы services
     */
    protected function resolveServiceName(?int $serviceId, $app): string
    {
        if (!$serviceId || $serviceId <= 0) {
            return '';
        }

        try {
            if (Schema::hasTable('services')) {
                $serv = DB::table('services')->where('id', $serviceId)->first();
                if ($serv) {
                    return (string) ($serv->name ?? $serv->title ?? $serv->short_name ?? '');
                }
            }
        } catch (\Throwable $e) {
            Log::warning('FactWorkController resolveServiceName error: ' . $e->getMessage());
        }

        if ($app && !empty($app->service_name)) {
            return (string) $app->service_name;
        }

        return '';
    }

    /**
     * Запись в аудит-лог
     */
    protected function logHistory(int $appId, ?int $workId, string $actionType, ?string $fieldName, ?string $oldVal, ?string $newVal): void
    {
        try {
            if (Schema::hasTable('fact_work_histories')) {
                FactWorkHistory::create([
                    'application_id' => $appId,
                    'fact_work_id' => $workId,
                    'user_id' => Auth::id(),
                    'action_type' => $actionType,
                    'field_name' => $fieldName,
                    'old_value' => $oldVal !== null ? (string) $oldVal : null,
                    'new_value' => $newVal !== null ? (string) $newVal : null,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('FactWorkController logHistory error: ' . $e->getMessage());
        }
    }

    /**
     * Список фактических работ дела
     */
    public function index($applicationId): JsonResponse
    {
        $appId = (int) $applicationId;
        if ($appId <= 0) {
            return response()->json([
                'data' => [],
                'total_sum' => 0,
                'service_id' => null,
                'service_name' => '',
            ]);
        }

        try {
            $app = Application::find($appId);
            $appCol = $this->getAppIdColumn();
            $priceCol = $this->getPriceColumn();

            $worksQuery = DB::table('fact_works');
            if (Schema::hasColumn('fact_works', 'application_id') && Schema::hasColumn('fact_works', 'id_application')) {
                $worksQuery->where(function ($q) use ($appId) {
                    $q->where('application_id', $appId)->orWhere('id_application', $appId);
                });
            } else {
                $worksQuery->where($appCol, $appId);
            }

            $works = $worksQuery->orderBy('id', 'asc')->get();

            // Приводим поля к единому формату для фронтенда
            $normalizedWorks = $works->map(function ($w) use ($priceCol) {
                $w->price = (float) ($w->{$priceCol} ?? $w->price ?? $w->price_service ?? $w->sum ?? 0);
                $w->author_type = (int) ($w->author_type ?? 0);
                return $w;
            });

            $totalSum = (float) $normalizedWorks->sum('price');

            // Автосервис дела строго из таблицы services
            $serviceId = null;
            $serviceName = '';
            if ($app) {
                $serviceId = (int) ($app->id_service ?? $app->service_id ?? 0);
                if ($serviceId > 0) {
                    $serviceName = $this->resolveServiceName($serviceId, $app);
                }
            }

            return response()->json([
                'data' => $normalizedWorks,
                'total_sum' => $totalSum,
                'service_id' => $serviceId > 0 ? $serviceId : null,
                'service_name' => $serviceName,
            ]);
        } catch (\Throwable $e) {
            Log::error('FactWorkController@index error: ' . $e->getMessage());
            return response()->json([
                'data' => [],
                'total_sum' => 0,
                'service_id' => null,
                'service_name' => '',
                'error' => $e->getMessage(),
            ], 200);
        }
    }

    /**
     * Создание фактической работы (вручную или из каталога)
     */
    public function store(Request $request, $applicationId): JsonResponse
    {
        try {
            $appId = (int) $applicationId;
            $app = Application::findOrFail($appId);
            $user = Auth::user();

            $authorType = $this->resolveAuthorType($user, $app);
            $name = trim($request->input('name', 'Новая фактическая работа'));
            $price = (float) $request->input('price', 0);
            $standardWorkId = $request->input('standard_work_id') ? (int) $request->input('standard_work_id') : null;

            $appCol = $this->getAppIdColumn();
            $priceCol = $this->getPriceColumn();
            $cols = Schema::getColumnListing('fact_works');

            $data = [
                $appCol => $appId,
                'name' => $name,
            ];

            // Записываем цену в определенную колонку
            if (in_array($priceCol, $cols, true)) {
                $data[$priceCol] = $price;
            }
            // Если в таблице есть и 'price', и 'price_service' — заполняем обе
            if ($priceCol !== 'price' && in_array('price', $cols, true)) {
                $data['price'] = $price;
            }
            if ($priceCol !== 'price_service' && in_array('price_service', $cols, true)) {
                $data['price_service'] = $price;
            }

            if (in_array('standard_work_id', $cols, true)) {
                $data['standard_work_id'] = $standardWorkId;
            }
            if (in_array('author_type', $cols, true)) {
                $data['author_type'] = $authorType;
            }
            if (in_array('author_role', $cols, true)) {
                $data['author_role'] = ($authorType === 4 ? 'service' : ($authorType === 2 ? 'mentor' : 'mrk'));
            }
            if (in_array('created_by_user_id', $cols, true)) {
                $data['created_by_user_id'] = $user?->id;
            }
            if (in_array('user_id', $cols, true)) {
                $data['user_id'] = $user?->id;
            }
            if (in_array('created_at', $cols, true)) {
                $data['created_at'] = now();
            }
            if (in_array('updated_at', $cols, true)) {
                $data['updated_at'] = now();
            }

            $newId = DB::table('fact_works')->insertGetId($data);
            $work = DB::table('fact_works')->where('id', $newId)->first();
            $work->price = (float) ($work->{$priceCol} ?? $work->price ?? $price);
            $work->author_type = (int) ($work->author_type ?? $authorType);

            $this->logHistory($appId, $newId, 'created', 'позиция', null, "{$name} ({$price} ₽)");

            return response()->json([
                'status' => 'ok',
                'data' => $work,
            ], 201);
        } catch (\Throwable $e) {
            Log::error('FactWorkController@store error: ' . $e->getMessage());
            return response()->json(['message' => 'Ошибка создания работы: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Импорт согласованных плановых работ дела в фактические
     */
    public function importFromPlan(Request $request, $applicationId): JsonResponse
    {
        try {
            $appId = (int) $applicationId;
            $app = Application::findOrFail($appId);
            $user = Auth::user();
            $authorType = $this->resolveAuthorType($user, $app);

            $planAppCol = Schema::hasColumn('plan_works', 'application_id') ? 'application_id' : 'id_application';
            $factAppCol = $this->getAppIdColumn();
            $priceCol = $this->getPriceColumn();
            $cols = Schema::getColumnListing('fact_works');

            // Берём плановые работы (исключаем отклонённые)
            $planWorks = DB::table('plan_works')
                ->where($planAppCol, $appId)
                ->where(function ($q) {
                    $q->whereNull('approval_status')
                        ->orWhere('approval_status', '!=', 'rejected');
                })
                ->get();

            if ($planWorks->isEmpty()) {
                return response()->json([
                    'message' => 'В плановых работах нет позиций для импорта',
                    'imported_count' => 0,
                    'data' => [],
                    'total_sum' => 0,
                ], 200);
            }

            // Существующие фактические работы для исключения дубликатов
            $existingFactNames = DB::table('fact_works')
                ->where($factAppCol, $appId)
                ->pluck('name')
                ->map(fn($n) => mb_strtolower(trim((string) $n)))
                ->toArray();

            $imported = 0;

            DB::transaction(function () use ($planWorks, $appId, $factAppCol, $priceCol, $cols, $authorType, $user, $existingFactNames, &$imported) {
                foreach ($planWorks as $pw) {
                    $cleanName = mb_strtolower(trim((string) $pw->name));
                    if (in_array($cleanName, $existingFactNames, true)) {
                        continue;
                    }

                    $insertPrice = (float) ($pw->price ?? 0);
                    $insert = [
                        $factAppCol => $appId,
                        'name' => $pw->name,
                    ];

                    if (in_array($priceCol, $cols, true)) {
                        $insert[$priceCol] = $insertPrice;
                    }
                    if ($priceCol !== 'price' && in_array('price', $cols, true)) {
                        $insert['price'] = $insertPrice;
                    }
                    if ($priceCol !== 'price_service' && in_array('price_service', $cols, true)) {
                        $insert['price_service'] = $insertPrice;
                    }

                    if (in_array('standard_work_id', $cols, true)) {
                        $insert['standard_work_id'] = $pw->standard_work_id ?? null;
                    }
                    if (in_array('plan_work_id', $cols, true)) {
                        $insert['plan_work_id'] = $pw->id;
                    }
                    if (in_array('author_type', $cols, true)) {
                        $insert['author_type'] = $authorType;
                    }
                    if (in_array('author_role', $cols, true)) {
                        $insert['author_role'] = ($authorType === 4 ? 'service' : 'mrk');
                    }
                    if (in_array('created_by_user_id', $cols, true)) {
                        $insert['created_by_user_id'] = $user?->id;
                    }
                    if (in_array('created_at', $cols, true)) {
                        $insert['created_at'] = now();
                    }
                    if (in_array('updated_at', $cols, true)) {
                        $insert['updated_at'] = now();
                    }

                    $newId = DB::table('fact_works')->insertGetId($insert);
                    $this->logHistory($appId, $newId, 'imported', 'импорт из плана', null, "{$pw->name} ({$insertPrice} ₽)");
                    $imported++;
                }
            });

            // Возвращаем свежий список
            $freshWorks = DB::table('fact_works')
                ->where($factAppCol, $appId)
                ->orderBy('id', 'asc')
                ->get()
                ->map(function ($w) use ($priceCol) {
                    $w->price = (float) ($w->{$priceCol} ?? $w->price ?? $w->price_service ?? $w->sum ?? 0);
                    $w->author_type = (int) ($w->author_type ?? 0);
                    return $w;
                });

            $freshTotal = (float) $freshWorks->sum('price');

            return response()->json([
                'message' => "Импортировано позиций: {$imported}",
                'imported_count' => $imported,
                'data' => $freshWorks,
                'total_sum' => $freshTotal,
            ]);
        } catch (\Throwable $e) {
            Log::error('FactWorkController@importFromPlan error: ' . $e->getMessage());
            return response()->json(['message' => 'Ошибка импорта: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Инлайн-редактирование ячейки (name или price)
     */
    public function updateField(Request $request, $applicationId, $workId): JsonResponse
    {
        try {
            $appId = (int) $applicationId;
            $wId = (int) $workId;
            $appCol = $this->getAppIdColumn();
            $priceCol = $this->getPriceColumn();
            $cols = Schema::getColumnListing('fact_works');

            $work = DB::table('fact_works')
                ->where('id', $wId)
                ->where($appCol, $appId)
                ->first();

            if (!$work) {
                return response()->json(['message' => 'Работа не найдена'], 404);
            }

            $field = $request->input('field');
            $value = $request->input('value');
            $oldValue = $work->{$field} ?? null;

            if ($field === 'price') {
                $value = (float) str_replace([' ', ','], ['', '.'], (string) $value);
                $oldValue = (float) ($work->{$priceCol} ?? $work->price ?? 0);
            }

            if ((string) $oldValue === (string) $value) {
                $work->price = (float) ($work->{$priceCol} ?? $work->price ?? 0);
                return response()->json(['data' => $work]);
            }

            $user = Auth::user();
            $app = Application::find($appId);
            $authorType = $this->resolveAuthorType($user, $app);

            $update = ['updated_at' => now()];

            if ($field === 'price') {
                if (in_array($priceCol, $cols, true)) {
                    $update[$priceCol] = $value;
                }
                if ($priceCol !== 'price' && in_array('price', $cols, true)) {
                    $update['price'] = $value;
                }
                if ($priceCol !== 'price_service' && in_array('price_service', $cols, true)) {
                    $update['price_service'] = $value;
                }
            } elseif (in_array($field, $cols, true)) {
                $update[$field] = $value;
            }

            if (in_array('author_type', $cols, true)) {
                $update['author_type'] = $authorType;
            }
            if (in_array('updated_by_user_id', $cols, true)) {
                $update['updated_by_user_id'] = $user?->id;
            }

            DB::table('fact_works')->where('id', $wId)->update($update);
            $updatedWork = DB::table('fact_works')->where('id', $wId)->first();
            $updatedWork->price = (float) ($updatedWork->{$priceCol} ?? $updatedWork->price ?? 0);
            $updatedWork->author_type = (int) ($updatedWork->author_type ?? $authorType);

            $this->logHistory($appId, $wId, 'updated', $field, (string) $oldValue, (string) $value);

            return response()->json(['data' => $updatedWork]);
        } catch (\Throwable $e) {
            Log::error('FactWorkController@updateField error: ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Удаление строки
     */
    public function destroy($applicationId, $workId): JsonResponse
    {
        try {
            $appId = (int) $applicationId;
            $wId = (int) $workId;
            $appCol = $this->getAppIdColumn();

            $work = DB::table('fact_works')->where('id', $wId)->where($appCol, $appId)->first();
            if ($work) {
                $workName = $work->name;
                DB::table('fact_works')->where('id', $wId)->delete();
                $this->logHistory($appId, $wId, 'deleted', 'позиция', $workName, null);
            }

            return response()->json(['status' => 'ok']);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * История аудита фактических работ
     */
    public function history($applicationId): JsonResponse
    {
        $appId = (int) $applicationId;

        try {
            if (!Schema::hasTable('fact_work_histories')) {
                return response()->json(['data' => []]);
            }

            $hasLastName = Schema::hasColumn('users', 'last_name');
            $userCols = $hasLastName ? 'id,name,last_name' : 'id,name';

            $history = FactWorkHistory::with("user:{$userCols}")
                ->where('application_id', $appId)
                ->orderBy('id', 'desc')
                ->limit(150)
                ->get();

            return response()->json(['data' => $history]);
        } catch (\Throwable $e) {
            Log::error('FactWorkController@history error: ' . $e->getMessage());
            return response()->json(['data' => []]);
        }
    }
}
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\PlanWork;
use App\Models\PlanWorkHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class PlanWorkController extends Controller
{
    /**
     * Определение колонки привязки к делу (application_id vs id_application)
     */
    protected function getAppIdColumn(): string
    {
        if (Schema::hasColumn('plan_works', 'application_id')) {
            return 'application_id';
        }
        if (Schema::hasColumn('plan_works', 'id_application')) {
            return 'id_application';
        }
        return 'application_id';
    }

    /**
     * Определение роли автора:
     * 1 - МРК
     * 2 - Наставник
     * 3 - СПМ
     * 4 - Сервис / СТО
     * 5 - Другой
     */
    protected function resolveAuthorType($user, $app): int
    {
        if (!$user) {
            return 0;
        }

        // Проверка через метод модели User
        if (method_exists($user, 'isSto') && $user->isSto()) {
            return 4;
        }

        $uid = (int) $user->id;
        $gid = (int) ($user->GroupId ?? $user->user_group_id ?? 0);

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

        // Если пользователь СТО по группам или сервисной привязке
        if ($gid === 7 || $gid === 8 || ($user->is_service ?? false) || !empty($user->id_service) || !empty($user->service_id)) {
            return 4;
        }

        if ($gid === 5)
            return 1;
        if ($gid === 6 || $gid === 10)
            return 2;
        if ($gid === 9)
            return 3;

        return 5;
    }

    /**
     * Получение точного названия автосервиса СТРОГО из таблицы services
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
            Log::warning('resolveServiceName error: ' . $e->getMessage());
        }

        if ($app && !empty($app->service_name)) {
            return (string) $app->service_name;
        }

        return '';
    }

    /**
     * Выдача списка плановых работ
     */
    public function index(Request $request, $applicationId): JsonResponse
    {
        $appId = (int) $applicationId;
        if ($appId <= 0) {
            return response()->json([
                'data' => [],
                'total_sum' => 0,
                'service_approved' => false,
                'fully_approved' => false,
                'can_complete_approval' => false,
                'service_id' => null,
                'service_name' => '',
                'is_service_user' => false,
            ]);
        }

        try {
            $app = Application::find($appId);
            $appCol = $this->getAppIdColumn();

            // Если в таблице есть обе колонки или только одна — делаем выборку надежно
            $worksQuery = DB::table('plan_works');
            if (Schema::hasColumn('plan_works', 'application_id') && Schema::hasColumn('plan_works', 'id_application')) {
                $worksQuery->where(function ($q) use ($appId) {
                    $q->where('application_id', $appId)->orWhere('id_application', $appId);
                });
            } else {
                $worksQuery->where($appCol, $appId);
            }

            $works = $worksQuery->orderBy('id', 'asc')->get();

            // Нормализация полей для фронтенда
            $normalizedWorks = $works->map(function ($w) {
                $status = $w->approval_status ?? $w->status ?? 'approved';
                $w->approval_status = $status;
                $w->status = $status;
                $w->author_type = (int) ($w->author_type ?? 1);
                $w->price = (float) ($w->price ?? 0);
                return $w;
            });

            // Итоговая сумма по одобренным работам
            $totalSum = (float) $normalizedWorks->filter(function ($w) {
                return ($w->approval_status ?? $w->status) === 'approved';
            })->sum('price');

            // Определение сервиса СТРОГО из services
            $serviceId = null;
            $serviceName = '';
            if ($app) {
                $serviceId = (int) ($app->id_service ?? $app->service_id ?? 0);
                if ($serviceId > 0) {
                    $serviceName = $this->resolveServiceName($serviceId, $app);
                }
            }

            $serviceApproved = (bool) ($app->plan_works_approved_by_service ?? false);
            $fullyApproved = (bool) ($app->plan_works_fully_approved ?? false);

            $hasPendingRows = $normalizedWorks->contains(fn($w) => ($w->approval_status ?? $w->status) === 'pending');
            $hasServiceRows = $normalizedWorks->contains(fn($w) => (int) ($w->author_type ?? 0) === 4 || ($w->author_role ?? '') === 'service');

            // Кнопка доступна, если сервис отправил запрос ИЛИ есть строки сервиса, нет зависших pending и согласование еще не финализировано
            $canCompleteApproval = false;
            if (!$fullyApproved && $normalizedWorks->isNotEmpty()) {
                if ($serviceApproved && !$hasPendingRows) {
                    $canCompleteApproval = true;
                } elseif ($hasServiceRows && !$hasPendingRows) {
                    $canCompleteApproval = true;
                }
            }

            $user = Auth::user();
            $isServiceUser = false;
            if ($user) {
                if (method_exists($user, 'isSto')) {
                    $isServiceUser = (bool) $user->isSto();
                } else {
                    $gid = (int) ($user->GroupId ?? $user->user_group_id ?? 0);
                    $isServiceUser = ($gid === 7 || $gid === 8 || ($user->is_service ?? false));
                }
            }

            return response()->json([
                'data' => $normalizedWorks,
                'total_sum' => $totalSum,
                'service_approved' => $serviceApproved,
                'fully_approved' => $fullyApproved,
                'can_complete_approval' => $canCompleteApproval,
                'service_id' => $serviceId > 0 ? $serviceId : null,
                'service_name' => $serviceName,
                'is_service_user' => $isServiceUser,
            ]);
        } catch (\Throwable $e) {
            Log::error('PlanWorkController@index error: ' . $e->getMessage());
            return response()->json([
                'data' => [],
                'total_sum' => 0,
                'service_id' => null,
                'service_name' => '',
                'is_service_user' => false,
                'error' => $e->getMessage()
            ], 200);
        }
    }

    /**
     * Добавление новой работы
     */
    public function store(Request $request, $applicationId): JsonResponse
    {
        try {
            $appId = (int) $applicationId;
            $app = Application::findOrFail($appId);
            $user = Auth::user();

            $isService = $user && method_exists($user, 'isSto') && $user->isSto();
            $authorType = $isService ? 4 : $this->resolveAuthorType($user, $app);

            $name = trim($request->input('name', 'Новая плановая работа'));
            $price = (float) $request->input('price', 0);
            $standardWorkId = $request->input('standard_work_id') ? (int) $request->input('standard_work_id') : null;

            $appCol = $this->getAppIdColumn();
            $cols = Schema::getColumnListing('plan_works');

            $initialStatus = ($authorType === 4) ? 'pending' : 'approved';

            $data = [
                $appCol => $appId,
                'name' => $name,
                'price' => $price,
            ];

            if (in_array('standard_work_id', $cols, true))
                $data['standard_work_id'] = $standardWorkId;
            if (in_array('author_type', $cols, true))
                $data['author_type'] = $authorType;
            if (in_array('author_role', $cols, true))
                $data['author_role'] = ($authorType === 4 ? 'service' : ($authorType === 2 ? 'mentor' : 'mrk'));
            if (in_array('approval_status', $cols, true))
                $data['approval_status'] = $initialStatus;
            if (in_array('status', $cols, true))
                $data['status'] = $initialStatus;
            if (in_array('created_by_user_id', $cols, true))
                $data['created_by_user_id'] = $user?->id;
            if (in_array('user_id', $cols, true))
                $data['user_id'] = $user?->id;
            if (in_array('created_at', $cols, true))
                $data['created_at'] = now();
            if (in_array('updated_at', $cols, true))
                $data['updated_at'] = now();

            $newId = DB::table('plan_works')->insertGetId($data);
            $work = DB::table('plan_works')->where('id', $newId)->first();

            // Логируем в историю
            $this->logHistory($appId, $newId, 'created', 'позиция', null, "{$name} ({$price} ₽)");

            return response()->json([
                'status' => 'ok',
                'data' => $work,
            ], 201);
        } catch (\Throwable $e) {
            Log::error('PlanWorkController@store error: ' . $e->getMessage());
            return response()->json(['message' => 'Ошибка создания работы: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Изменение поля ячейки (name или price)
     */
    public function updateField(Request $request, $applicationId, $workId): JsonResponse
    {
        try {
            $appId = (int) $applicationId;
            $wId = (int) $workId;
            $appCol = $this->getAppIdColumn();

            $work = DB::table('plan_works')->where('id', $wId)->first();
            if (!$work) {
                return response()->json(['message' => 'Работа не найдена'], 404);
            }

            $field = $request->input('field');
            $value = $request->input('value');
            $oldValue = $work->{$field} ?? null;

            if ($field === 'price') {
                $value = (float) str_replace([' ', ','], ['', '.'], (string) $value);
            }

            if ((string) $oldValue === (string) $value) {
                return response()->json(['data' => $work]);
            }

            $cols = Schema::getColumnListing('plan_works');
            $update = [
                $field => $value,
                'updated_at' => now(),
            ];

            if ($field === 'price' && in_array('is_custom_price', $cols, true)) {
                $update['is_custom_price'] = true;
            }

            $user = Auth::user();
            $app = Application::find($appId);
            $authorType = $this->resolveAuthorType($user, $app);

            if (in_array('author_type', $cols, true))
                $update['author_type'] = $authorType;
            if (in_array('updated_by_user_id', $cols, true))
                $update['updated_by_user_id'] = $user?->id;

            // Если СТО меняет стоимость или название — статус строки возвращается на согласование (pending)
            if ($authorType === 4) {
                if (in_array('approval_status', $cols, true))
                    $update['approval_status'] = 'pending';
                if (in_array('status', $cols, true))
                    $update['status'] = 'pending';
            }

            DB::table('plan_works')->where('id', $wId)->update($update);
            $updatedWork = DB::table('plan_works')->where('id', $wId)->first();

            $this->logHistory($appId, $wId, 'updated', $field, (string) $oldValue, (string) $value);

            return response()->json(['data' => $updatedWork]);
        } catch (\Throwable $e) {
            Log::error('PlanWorkController@updateField error: ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * МРК/Наставник меняет статус строки: Принять / Отклонить
     * (Единый метод, закрывающий и setRowApproval, и changeRowApproval)
     */
    public function setRowApproval(Request $request, $applicationId, $workId): JsonResponse
    {
        return $this->changeRowApproval($request, $applicationId, $workId);
    }

    public function changeRowApproval(Request $request, $applicationId, $workId): JsonResponse
    {
        try {
            $user = Auth::user();
            if ($user && method_exists($user, 'isSto') && $user->isSto()) {
                return response()->json(['message' => 'Автосервису недоступно согласование позиций'], 403);
            }

            $appId = (int) $applicationId;
            $wId = (int) $workId;
            $status = $request->input('status'); // 'approved' или 'rejected'

            $work = DB::table('plan_works')->where('id', $wId)->first();
            if (!$work) {
                return response()->json(['message' => 'Позиция не найдена'], 404);
            }

            $oldStatus = $work->approval_status ?? $work->status ?? 'pending';
            $cols = Schema::getColumnListing('plan_works');

            $update = ['updated_at' => now()];
            if (in_array('approval_status', $cols, true))
                $update['approval_status'] = $status;
            if (in_array('status', $cols, true))
                $update['status'] = $status;

            DB::table('plan_works')->where('id', $wId)->update($update);
            $updatedWork = DB::table('plan_works')->where('id', $wId)->first();

            $this->logHistory(
                $appId,
                $wId,
                'status_change',
                'согласование',
                $oldStatus === 'approved' ? 'Принято' : ($oldStatus === 'rejected' ? 'Отклонено' : 'На проверке'),
                $status === 'approved' ? 'Принято' : 'Отклонено'
            );

            return response()->json(['data' => $updatedWork]);
        } catch (\Throwable $e) {
            Log::error('changeRowApproval error: ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Сервис нажимает "Согласовать"
     */
    public function submitServiceApproval($applicationId): JsonResponse
    {
        try {
            $appId = (int) $applicationId;
            $app = Application::findOrFail($appId);
            $appCol = $this->getAppIdColumn();

            $app->plan_works_approved_by_service = true;
            $app->save();

            $cols = Schema::getColumnListing('plan_works');
            $update = ['updated_at' => now()];
            if (in_array('approval_status', $cols, true))
                $update['approval_status'] = 'pending';
            if (in_array('status', $cols, true))
                $update['status'] = 'pending';

            DB::table('plan_works')
                ->where($appCol, $appId)
                ->where(function ($q) {
                    $q->where('author_type', 4)->orWhere('author_role', 'service');
                })
                ->update($update);

            $this->logHistory($appId, null, 'service_submitted', 'согласование', null, 'Сервис отправил работы на согласование');

            return response()->json(['status' => 'ok', 'message' => 'Работы переданы на согласование МРК']);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * МРК завершает согласование
     */
    public function completeApproval($applicationId): JsonResponse
    {
        try {
            $appId = (int) $applicationId;
            $app = Application::findOrFail($appId);
            $app->plan_works_fully_approved = true;
            $app->save();

            $this->logHistory($appId, null, 'approval_completed', 'согласование', null, 'МРК завершил согласование плановых работ');

            return response()->json(['status' => 'ok', 'message' => 'Согласование плановых работ полностью завершено']);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Удаление работы
     */
    public function destroy($applicationId, $workId): JsonResponse
    {
        try {
            $appId = (int) $applicationId;
            $wId = (int) $workId;

            $work = DB::table('plan_works')->where('id', $wId)->first();
            if ($work) {
                $workName = $work->name;
                $workPrice = $work->price;
                DB::table('plan_works')->where('id', $wId)->delete();
                $this->logHistory($appId, $wId, 'deleted', 'позиция', "{$workName} ({$workPrice} ₽)", null);
            }

            return response()->json(['status' => 'ok']);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * История изменений
     */
    public function history($applicationId): JsonResponse
    {
        $appId = (int) $applicationId;

        try {
            if (!Schema::hasTable('plan_work_histories')) {
                return response()->json(['data' => []]);
            }

            $history = PlanWorkHistory::with('user:id,name,last_name')
                ->where('application_id', $appId)
                ->orderBy('id', 'desc')
                ->limit(150)
                ->get();

            return response()->json(['data' => $history]);
        } catch (\Throwable $e) {
            Log::error('PlanWorkController@history error: ' . $e->getMessage());
            return response()->json(['data' => []]);
        }
    }

    /**
     * Вспомогательный метод записи истории
     */
    protected function logHistory(int $appId, ?int $workId, string $actionType, ?string $fieldName, ?string $oldVal, ?string $newVal): void
    {
        try {
            if (Schema::hasTable('plan_work_histories')) {
                PlanWorkHistory::create([
                    'application_id' => $appId,
                    'plan_work_id' => $workId,
                    'user_id' => Auth::id(),
                    'action_type' => $actionType,
                    'field_name' => $fieldName,
                    'old_value' => $oldVal !== null ? (string) $oldVal : null,
                    'new_value' => $newVal !== null ? (string) $newVal : null,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('logHistory error: ' . $e->getMessage());
        }
    }
}
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class CalculationWorkController extends Controller
{
    /**
     * Определение имени колонки привязки к делу (id_application или application_id)
     */
    protected function getAppIdColumn(): string
    {
        if (Schema::hasColumn('works', 'id_application')) {
            return 'id_application';
        }
        return 'application_id';
    }

    public function index(int $applicationId): JsonResponse
    {
        try {
            $appCol = $this->getAppIdColumn();

            $works = DB::table('works')
                ->where($appCol, $applicationId)
                ->orderBy('id', 'asc')
                ->get();

            // Нормализуем поля для фронтенда
            $mapped = $works->map(function ($w) {
                $isPaint = false;
                if (isset($w->is_paint)) {
                    $isPaint = (bool) $w->is_paint;
                } elseif (isset($w->work_category)) {
                    $isPaint = ($w->work_category === 'paint');
                }

                $price = (float) ($w->price ?? 1000);
                $normHours = (float) ($w->count_hours ?? $w->norm_hours ?? 0);
                $hoursPzn = (float) ($w->hours_pzn ?? $w->pzn_hours ?? $normHours);
                $sumSk = (float) ($w->sum_sk ?? $w->sum_ic ?? round($price * $normHours, 2));
                $sumPzn = (float) ($w->sum_pzn ?? round($price * $hoursPzn, 2));

                return [
                    'id' => $w->id,
                    'name' => $w->name ?? '',
                    'price' => $price,
                    'count_hours' => $normHours,
                    'hours_pzn' => $hoursPzn,
                    'sum_sk' => $sumSk,
                    'sum_pzn' => $sumPzn,
                    'is_paint' => $isPaint,
                ];
            });

            $repairWorks = $mapped->filter(fn($w) => !$w['is_paint'])->values();
            $paintWorks = $mapped->filter(fn($w) => $w['is_paint'])->values();

            // Получение ЛКМ из applications или estimates
            $app = DB::table('applications')->where('id', $applicationId)->first();
            $approvedLkm = (float) ($app->paint_materials_approved ?? 0);
            $planLkm = (float) ($app->paint_materials_plan ?? 0);

            if ($approvedLkm == 0 && $planLkm == 0 && Schema::hasTable('estimates')) {
                $estCol = Schema::hasColumn('estimates', 'id_application') ? 'id_application' : 'application_id';
                $est = DB::table('estimates')->where($estCol, $applicationId)->first();
                if ($est) {
                    $approvedLkm = (float) ($est->paint_materials_approved ?? 0);
                    $planLkm = (float) ($est->paint_materials_plan ?? 0);
                }
            }

            return response()->json([
                'repair_works' => $repairWorks,
                'paint_works' => $paintWorks,
                'materials' => [
                    'approved' => $approvedLkm,
                    'plan' => $planLkm,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('CalculationWorkController@index error: ' . $e->getMessage());
            return response()->json([
                'repair_works' => [],
                'paint_works' => [],
                'materials' => ['approved' => 0, 'plan' => 0],
            ]);
        }
    }

    public function store(Request $request, int $applicationId): JsonResponse
    {
        try {
            $user = Auth::user();
            $name = trim($request->input('name', 'Новая операция'));
            $price = (float) $request->input('price', 1000);
            $countHours = (float) $request->input('count_hours', 0.5);
            $hoursPzn = (float) $request->input('hours_pzn', $countHours);
            $isPaint = (bool) $request->input('is_paint', false);

            $sumSk = round($price * $countHours, 2);
            $sumPzn = round($price * $hoursPzn, 2);

            $cols = Schema::getColumnListing('works');
            $insert = [
                'name' => $name,
            ];

            // Поддержка обоих вариантов внешнего ключа
            if (in_array('id_application', $cols, true)) {
                $insert['id_application'] = $applicationId;
            }
            if (in_array('application_id', $cols, true)) {
                $insert['application_id'] = $applicationId;
            }

            // Ставка н/ч
            if (in_array('price', $cols, true)) {
                $insert['price'] = $price;
            }

            // Нормочасы
            if (in_array('count_hours', $cols, true)) {
                $insert['count_hours'] = $countHours;
            }
            if (in_array('norm_hours', $cols, true)) {
                $insert['norm_hours'] = $countHours;
            }

            // Часы ПЗН
            if (in_array('hours_pzn', $cols, true)) {
                $insert['hours_pzn'] = $hoursPzn;
            }
            if (in_array('pzn_hours', $cols, true)) {
                $insert['pzn_hours'] = $hoursPzn;
            }

            // Суммы
            if (in_array('sum_sk', $cols, true)) {
                $insert['sum_sk'] = $sumSk;
            }
            if (in_array('sum_ic', $cols, true)) {
                $insert['sum_ic'] = $sumSk;
            }
            if (in_array('sum_pzn', $cols, true)) {
                $insert['sum_pzn'] = $sumPzn;
            }
            if (in_array('summ', $cols, true)) {
                $insert['summ'] = $sumSk;
            }

            // Категория / признак окраски
            if (in_array('is_paint', $cols, true)) {
                $insert['is_paint'] = $isPaint;
            }
            if (in_array('work_category', $cols, true)) {
                $insert['work_category'] = $isPaint ? 'paint' : 'repair';
            }

            // Автор
            if (in_array('created_by_user_id', $cols, true)) {
                $insert['created_by_user_id'] = $user?->id;
            }
            if (in_array('updated_by_user_id', $cols, true)) {
                $insert['updated_by_user_id'] = $user?->id;
            }
            if (in_array('author_type', $cols, true)) {
                $insert['author_type'] = 1;
            }

            if (in_array('created_at', $cols, true)) {
                $insert['created_at'] = now();
            }
            if (in_array('updated_at', $cols, true)) {
                $insert['updated_at'] = now();
            }

            $id = DB::table('works')->insertGetId($insert);

            $newRow = [
                'id' => $id,
                'name' => $name,
                'price' => $price,
                'count_hours' => $countHours,
                'hours_pzn' => $hoursPzn,
                'sum_sk' => $sumSk,
                'sum_pzn' => $sumPzn,
                'is_paint' => $isPaint,
            ];

            // Безопасное логирование истории
            $this->logHistory($applicationId, $id, 'created', 'позиция', null, "{$name} ({$countHours} н/ч)");

            return response()->json(['data' => $newRow], 201);
        } catch (\Throwable $e) {
            Log::error('CalculationWorkController@store error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Ошибка создания работы калькуляции: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateField(Request $request, int $applicationId, int $id): JsonResponse
    {
        try {
            $field = $request->input('field');
            $value = $request->input('value');
            $appCol = $this->getAppIdColumn();

            $oldWork = DB::table('works')->where('id', $id)->first();
            if (!$oldWork) {
                return response()->json(['message' => 'Работа не найдена'], 404);
            }

            $cols = Schema::getColumnListing('works');
            $update = [];

            if (in_array($field, ['price', 'count_hours', 'hours_pzn'])) {
                $numVal = (float) str_replace([' ', ','], ['', '.'], (string) $value);

                if ($field === 'price') {
                    if (in_array('price', $cols, true))
                        $update['price'] = $numVal;
                    $ch = (float) ($oldWork->count_hours ?? $oldWork->norm_hours ?? 0);
                    $hp = (float) ($oldWork->hours_pzn ?? $oldWork->pzn_hours ?? $ch);
                    if (in_array('sum_sk', $cols, true))
                        $update['sum_sk'] = round($numVal * $ch, 2);
                    if (in_array('sum_pzn', $cols, true))
                        $update['sum_pzn'] = round($numVal * $hp, 2);
                } elseif ($field === 'count_hours') {
                    if (in_array('count_hours', $cols, true))
                        $update['count_hours'] = $numVal;
                    if (in_array('norm_hours', $cols, true))
                        $update['norm_hours'] = $numVal;
                    $pr = (float) ($oldWork->price ?? 1000);
                    if (in_array('sum_sk', $cols, true))
                        $update['sum_sk'] = round($pr * $numVal, 2);
                } elseif ($field === 'hours_pzn') {
                    if (in_array('hours_pzn', $cols, true))
                        $update['hours_pzn'] = $numVal;
                    if (in_array('pzn_hours', $cols, true))
                        $update['pzn_hours'] = $numVal;
                    $pr = (float) ($oldWork->price ?? 1000);
                    if (in_array('sum_pzn', $cols, true))
                        $update['sum_pzn'] = round($pr * $numVal, 2);
                }
            } else {
                if (in_array($field, $cols, true)) {
                    $update[$field] = $value;
                }
            }

            if (in_array('updated_at', $cols, true)) {
                $update['updated_at'] = now();
            }

            if (!empty($update)) {
                DB::table('works')->where('id', $id)->update($update);
            }

            $oldValStr = (string) ($oldWork->{$field} ?? '');
            $newValStr = (string) $value;
            if ($oldValStr !== $newValStr) {
                $this->logHistory($applicationId, $id, 'updated', $field, $oldValStr, $newValStr);
            }

            $updated = DB::table('works')->where('id', $id)->first();
            return response()->json(['data' => $updated]);
        } catch (\Throwable $e) {
            Log::error('CalculationWorkController@updateField error: ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function transfer(int $applicationId, int $id): JsonResponse
    {
        try {
            $work = DB::table('works')->where('id', $id)->first();
            if (!$work) {
                return response()->json(['message' => 'Работа не найдена'], 404);
            }

            $cols = Schema::getColumnListing('works');
            $currentIsPaint = false;
            if (isset($work->is_paint)) {
                $currentIsPaint = (bool) $work->is_paint;
            } elseif (isset($work->work_category)) {
                $currentIsPaint = ($work->work_category === 'paint');
            }

            $newIsPaint = !$currentIsPaint;
            $update = [];

            if (in_array('is_paint', $cols, true)) {
                $update['is_paint'] = $newIsPaint;
            }
            if (in_array('work_category', $cols, true)) {
                $update['work_category'] = $newIsPaint ? 'paint' : 'repair';
            }
            if (in_array('updated_at', $cols, true)) {
                $update['updated_at'] = now();
            }

            DB::table('works')->where('id', $id)->update($update);

            $this->logHistory(
                $applicationId,
                $id,
                $newIsPaint ? 'transferred_to_paint' : 'transferred_to_repair',
                'категория',
                $currentIsPaint ? 'Окрасочные' : 'Ремонтные',
                $newIsPaint ? 'Окрасочные' : 'Ремонтные'
            );

            return response()->json(['status' => 'ok']);
        } catch (\Throwable $e) {
            Log::error('CalculationWorkController@transfer error: ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function saveMaterials(Request $request, int $applicationId): JsonResponse
    {
        try {
            $approved = (float) $request->input('paint_materials_approved', 0);
            $plan = (float) $request->input('paint_materials_plan', 0);

            // Сохраняем в applications
            $appCols = Schema::getColumnListing('applications');
            $appUpdate = [];
            if (in_array('paint_materials_approved', $appCols, true)) {
                $appUpdate['paint_materials_approved'] = $approved;
            }
            if (in_array('paint_materials_plan', $appCols, true)) {
                $appUpdate['paint_materials_plan'] = $plan;
            }

            if (!empty($appUpdate)) {
                DB::table('applications')->where('id', $applicationId)->update($appUpdate);
            }

            return response()->json(['status' => 'ok']);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function destroy(int $applicationId, int $id): JsonResponse
    {
        try {
            $work = DB::table('works')->where('id', $id)->first();
            DB::table('works')->where('id', $id)->delete();

            if ($work) {
                $this->logHistory($applicationId, $id, 'deleted', 'позиция', (string) $work->name, null);
            }

            return response()->json(['status' => 'ok']);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function history(int $applicationId): JsonResponse
    {
        try {
            $this->ensureHistoryTableExists();

            $hasLastName = Schema::hasColumn('users', 'last_name');

            $items = DB::table('calculation_work_histories')
                ->leftJoin('users', 'calculation_work_histories.user_id', '=', 'users.id')
                ->where('calculation_work_histories.application_id', $applicationId)
                ->select(
                    'calculation_work_histories.*',
                    'users.name as user_name',
                    $hasLastName ? 'users.last_name as user_last_name' : DB::raw('NULL as user_last_name')
                )
                ->orderBy('calculation_work_histories.id', 'desc')
                ->limit(100)
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'created_at' => $item->created_at,
                        'action_type' => $item->action_type ?? 'updated',
                        'field_name' => $item->field_name ?? '',
                        'old_value' => $item->old_value,
                        'new_value' => $item->new_value,
                        'user' => [
                            'name' => $item->user_name ?? 'Система',
                            'last_name' => $item->user_last_name ?? '',
                        ],
                    ];
                });

            return response()->json(['data' => $items]);
        } catch (\Throwable $e) {
            Log::error('CalculationWorkController@history error: ' . $e->getMessage());
            return response()->json(['data' => []]);
        }
    }

    protected function logHistory(int $applicationId, int $workId, string $action, ?string $field, ?string $old, ?string $new): void
    {
        try {
            $cleanOld = trim((string) $old);
            $cleanNew = trim((string) $new);
            if ($action === 'updated' && $cleanOld === $cleanNew) {
                return;
            }

            $this->ensureHistoryTableExists();

            $user = Auth::user();
            DB::table('calculation_work_histories')->insert([
                'application_id' => $applicationId,
                'work_id' => $workId,
                'user_id' => $user?->id,
                'action_type' => $action,
                'field_name' => $field,
                'old_value' => $old,
                'new_value' => $new,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('logHistory calculation works: ' . $e->getMessage());
        }
    }

    protected function ensureHistoryTableExists(): void
    {
        if (!Schema::hasTable('calculation_work_histories')) {
            Schema::create('calculation_work_histories', function ($table) {
                $table->id();
                $table->unsignedBigInteger('application_id');
                $table->unsignedBigInteger('work_id')->default(0);
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('action_type', 50)->default('updated');
                $table->string('field_name', 100)->nullable();
                $table->text('old_value')->nullable();
                $table->text('new_value')->nullable();
                $table->timestamps();

                $table->index('application_id');
            });
        }
    }
}
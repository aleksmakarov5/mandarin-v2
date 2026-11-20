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
use App\Models\PlanWork;

class PlanWorkController extends Controller
{
    protected function getAppIdColumn(): string
    {
        return Schema::hasColumn('plan_works', 'application_id') ? 'application_id' : 'id_application';
    }

    /**
     * Определение роли / автора для раскраски:
     * 1 - МРК (пастельно-синий)
     * 2 - Наставник (сиреневый)
     * 3 - Запчастист (зеленый)
     * 4 - Сервис / СТО (желтый)
     * 5 - Другой пользователь (коричневый)
     */
    private function resolveAuthorType($user, Application $app): int
    {
        if (!$user) {
            return 5;
        }

        // Проверяем, является ли пользователь сервисом:
        // - флаг isSto()
        // - group_id == 8 или role_id == 8
        // - наличие service_id / id_service
        // - совпадение с id_service текущего дела
        $isStoUser = (method_exists($user, 'isSto') && $user->isSto())
            || (int) ($user->GroupId ?? $user->group_id ?? $user->role_id ?? 0) === 8
            || !empty($user->id_service)
            || !empty($user->service_id);

        if ($isStoUser) {
            return 4; // СЕРВИС
        }

        $userId = (int) $user->id;

        // Наставник дела
        if (!empty($app->id_mentor) && (int) $app->id_mentor === $userId) {
            return 2;
        }

        // МРК дела (или куратор)
        if (!empty($app->id_MRK) && (int) $app->id_MRK === $userId) {
            return 1;
        }

        // СПМ дела
        if (!empty($app->id_spm) && (int) $app->id_spm === $userId) {
            return 3;
        }

        // Если пользователь имеет роль МРК (group 5)
        $groupId = (int) ($user->GroupId ?? $user->group_id ?? $user->role_id ?? 0);
        if ($groupId === 5) {
            return 1;
        }
        if ($groupId === 6) {
            return 2;
        }
        if ($groupId === 9) {
            return 3;
        }

        return 5; // Другой сотрудник
    }

    public function index(int $applicationId): JsonResponse
    {
        try {
            $app = Application::find($applicationId);
            $appCol = $this->getAppIdColumn();

            $works = DB::table('plan_works')
                ->where($appCol, $applicationId)
                ->orderBy('id', 'asc')
                ->get();

            // Считаем сумму ТОЛЬКО из принятых позиций (approved)
            $totalSum = (float) $works->filter(function ($w) {
                $status = $w->approval_status ?? $w->status ?? 'approved';
                return $status === 'approved';
            })->sum('price');

            $allProcessed = $works->count() > 0 && $works->every(function ($w) {
                $status = $w->approval_status ?? $w->status ?? 'approved';
                return in_array($status, ['approved', 'rejected'], true);
            });

            $serviceId = (int) ($app->id_service ?? $app->service_id ?? 0);

            return response()->json([
                'data' => $works,
                'total_sum' => $totalSum,
                'service_approved' => (bool) ($app->plan_works_approved_by_service ?? false),
                'fully_approved' => (bool) ($app->plan_works_fully_approved ?? false),
                'can_complete_approval' => (bool) (($app->plan_works_approved_by_service ?? false) && $allProcessed),
                'service_id' => $serviceId > 0 ? $serviceId : null,
            ]);
        } catch (\Throwable $e) {
            Log::error('PlanWorkController@index error: ' . $e->getMessage());
            return response()->json(['data' => [], 'total_sum' => 0, 'service_id' => null]);
        }
    }

    public function store(Request $request, int $applicationId): JsonResponse
    {
        try {
            $user = Auth::user();
            $app = Application::find($applicationId);
            $appCol = $this->getAppIdColumn();

            $name = trim($request->input('name', 'Новая работа'));
            $price = (float) $request->input('price', 0);
            $standardWorkId = $request->input('standard_work_id') ? (int) $request->input('standard_work_id') : null;

            // Если передана типовая работа и цена 0, подтягиваем из прайса сервиса
            if ($standardWorkId && $price <= 0) {
                $serviceId = (int) ($app->id_service ?? $app->service_id ?? 0);
                $serviceCol = Schema::hasColumn('service_work_prices', 'service_id') ? 'service_id' : 'our_company_id';

                if ($serviceId > 0 && Schema::hasTable('service_work_prices')) {
                    $sPrice = DB::table('service_work_prices')
                        ->where('standard_work_id', $standardWorkId)
                        ->where($serviceCol, $serviceId)
                        ->value('price');
                    if ($sPrice !== null) {
                        $price = (float) $sPrice;
                    }
                }

                if ($price <= 0 && Schema::hasTable('standard_works')) {
                    $sw = DB::table('standard_works')->where('id', $standardWorkId)->first();
                    if ($sw) {
                        $price = (float) ($sw->base_price ?? $sw->price ?? 0);
                    }
                }
            }

            $authorType = $this->resolveAuthorType($user, $app);
            $initialStatus = ($authorType === 4) ? 'pending' : 'approved';

            // Получаем реальные колонки таблицы из БД
            $existingColumns = Schema::getColumnListing('plan_works');

            // Возможные данные для вставки
            $potentialData = [
                $appCol => $applicationId,
                'name' => $name,
                'price' => $price,
                'standard_work_id' => $standardWorkId,
                'author_type' => $authorType,
                'author_role' => ($authorType === 4) ? 'service' : 'mrk',
                'approval_status' => $initialStatus,
                'status' => $initialStatus,
                'is_custom_price' => false,
                'user_id' => $user?->id,
                'id_user' => $user?->id,
                'created_by_user_id' => $user?->id,
                'updated_by_user_id' => $user?->id,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Оставляем ТОЛЬКО те поля, которые физически есть в таблице plan_works
            $insert = array_intersect_key($potentialData, array_flip($existingColumns));

            $id = DB::table('plan_works')->insertGetId($insert);
            $newWork = DB::table('plan_works')->where('id', $id)->first();

            return response()->json(['data' => $newWork], 201);
        } catch (\Throwable $e) {
            Log::error('PlanWorkController@store error: ' . $e->getMessage());
            return response()->json(['message' => 'Ошибка создания работы: ' . $e->getMessage()], 500);
        }
    }

    public function updateField(Request $request, int $applicationId, int $id): JsonResponse
    {
        try {
            $appCol = $this->getAppIdColumn();
            $field = $request->input('field');
            $value = $request->input('value');

            if ($field === 'price') {
                $value = (float) str_replace([' ', ','], ['', '.'], (string) $value);
            }

            $user = Auth::user();
            $app = Application::find($applicationId);
            $cols = Schema::getColumnListing('plan_works');

            $update = [$field => $value];
            $authorType = $this->resolveAuthorType($user, $app);

            if (in_array('author_type', $cols, true)) {
                $update['author_type'] = $authorType;
            }
            if (in_array('user_id', $cols, true)) {
                $update['user_id'] = $user?->id;
            }
            if (in_array('id_user', $cols, true)) {
                $update['id_user'] = $user?->id;
            }
            if (in_array('updated_by_user_id', $cols, true)) {
                $update['updated_by_user_id'] = $user?->id;
            }
            if ($field === 'price' && in_array('is_custom_price', $cols, true)) {
                $update['is_custom_price'] = true;
            }

            // Если редактирует сервис — возвращаем статус на согласование
            if ($authorType === 4) {
                if (in_array('approval_status', $cols, true)) {
                    $update['approval_status'] = 'pending';
                }
                if (in_array('status', $cols, true)) {
                    $update['status'] = 'pending';
                }
            }

            if (in_array('updated_at', $cols, true)) {
                $update['updated_at'] = now();
            }

            DB::table('plan_works')
                ->where($appCol, $applicationId)
                ->where('id', $id)
                ->update($update);

            $work = DB::table('plan_works')->where('id', $id)->first();
            return response()->json(['data' => $work]);
        } catch (\Throwable $e) {
            Log::error('PlanWorkController@updateField error: ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Пересчет цен всех плановых работ при смене сервиса в деле
     */
    public function syncPricesWithService(Request $request, int $applicationId): JsonResponse
    {
        try {
            $app = Application::find($applicationId);
            $serviceId = (int) ($request->input('service_id') ?? $app->id_service ?? $app->service_id ?? 0);

            if ($serviceId <= 0 || !Schema::hasTable('service_work_prices')) {
                return response()->json(['status' => 'no_service', 'updated_count' => 0]);
            }

            $appCol = $this->getAppIdColumn();
            $serviceCol = Schema::hasColumn('service_work_prices', 'service_id') ? 'service_id' : 'our_company_id';

            // Находим строки из справочника, цены которых не фиксировались вручную
            $works = DB::table('plan_works')
                ->where($appCol, $applicationId)
                ->whereNotNull('standard_work_id')
                ->where('standard_work_id', '>', 0)
                ->get();

            $updatedCount = 0;
            foreach ($works as $w) {
                // Если стоит флаг ручной цены — пропускаем
                if (isset($w->is_custom_price) && $w->is_custom_price) {
                    continue;
                }

                $sPrice = DB::table('service_work_prices')
                    ->where('standard_work_id', $w->standard_work_id)
                    ->where($serviceCol, $serviceId)
                    ->value('price');

                if ($sPrice !== null && (float) $w->price !== (float) $sPrice) {
                    DB::table('plan_works')
                        ->where('id', $w->id)
                        ->update(['price' => (float) $sPrice, 'updated_at' => now()]);
                    $updatedCount++;
                }
            }

            $freshWorks = DB::table('plan_works')->where($appCol, $applicationId)->orderBy('id', 'asc')->get();

            return response()->json([
                'status' => 'ok',
                'updated_count' => $updatedCount,
                'data' => $freshWorks,
                'total_sum' => (float) $freshWorks->sum('price'),
            ]);
        } catch (\Throwable $e) {
            Log::error('syncPricesWithService error: ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Изменение статуса согласования конкретной строки (МРК нажимает Принять / Отклонить)
     */
    public function setRowApproval(Request $request, int $applicationId, int $id): JsonResponse
    {
        try {
            $status = $request->input('status'); // 'approved' или 'rejected'
            $appCol = $this->getAppIdColumn();
            $cols = Schema::getColumnListing('plan_works');

            $update = [];
            if (in_array('approval_status', $cols, true)) {
                $update['approval_status'] = $status;
            }
            if (in_array('status', $cols, true)) {
                $update['status'] = $status;
            }
            if (in_array('updated_at', $cols, true)) {
                $update['updated_at'] = now();
            }

            DB::table('plan_works')
                ->where($appCol, $applicationId)
                ->where('id', $id)
                ->update($update);

            $work = DB::table('plan_works')->where('id', $id)->first();
            return response()->json(['data' => $work]);
        } catch (\Throwable $e) {
            Log::error('setRowApproval error: ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Сервис нажимает кнопку "Согласовать с МРК"
     */
    public function submitServiceApproval(int $applicationId): JsonResponse
    {
        try {
            $app = Application::find($applicationId);
            if ($app) {
                $app->plan_works_approved_by_service = true;
                $app->save();
            }

            $appCol = $this->getAppIdColumn();
            DB::table('plan_works')
                ->where($appCol, $applicationId)
                ->where('author_type', 4)
                ->where('approval_status', 'approved') // переводим сервис в pending
                ->update(['approval_status' => 'pending', 'updated_at' => now()]);

            return response()->json(['status' => 'ok']);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * МРК нажимает кнопку "Согласование завершено"
     */
    public function completeApproval(int $applicationId): JsonResponse
    {
        try {
            $app = Application::find($applicationId);
            if ($app) {
                $app->plan_works_fully_approved = true;
                $app->save();
            }
            return response()->json(['status' => 'ok']);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function destroy(int $applicationId, int $id): JsonResponse
    {
        try {
            $appCol = $this->getAppIdColumn();
            DB::table('plan_works')
                ->where($appCol, $applicationId)
                ->where('id', $id)
                ->delete();

            return response()->json(['status' => 'ok']);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }


}
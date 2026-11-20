<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\PlanWork;
use App\Models\StandardWork;
use App\Models\ServiceStandardWorkPrice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class PlanWorkController extends Controller
{
    protected function getAppIdColumn(): string
    {
        return Schema::hasColumn('plan_works', 'application_id') ? 'application_id' : 'id_application';
    }

    protected function resolveAuthorType($user, $app): int
    {
        if (!$user)
            return 0;
        $uid = (int) $user->id;
        $gid = (int) ($user->GroupId ?? $user->user_group_id ?? 0);

        if ($app) {
            $mrkId = (int) ($app->id_user ?? $app->mrk_id ?? 0);
            $mentorId = (int) ($app->mentor_id ?? 0);
            $serviceUserId = (int) ($app->service_user_id ?? 0);

            if ($mrkId && $uid === $mrkId)
                return 1;
            if ($mentorId && $uid === $mentorId)
                return 2;
            if ($serviceUserId && $uid === $serviceUserId)
                return 4;
        }

        if ($gid === 8 || ($user->is_service ?? false))
            return 4;
        if ($gid === 5)
            return 1;
        if ($gid === 6 || $gid === 10)
            return 2;

        return 5;
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

            $totalSum = (float) $works->sum('price');
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

            $name = trim($request->input('name', 'Новая плановая работа'));
            $price = (float) $request->input('price', 0);
            $standardWorkId = $request->input('standard_work_id') ? (int) $request->input('standard_work_id') : null;

            // Если передана типовая работа и цена 0, подтягиваем из базы
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

            // Собираем данные с учетом существующих колонок
            $cols = Schema::getColumnListing('plan_works');
            $insert = [
                $appCol => $applicationId,
                'name' => $name,
                'price' => $price,
            ];

            if (in_array('standard_work_id', $cols, true)) {
                $insert['standard_work_id'] = $standardWorkId;
            }
            if (in_array('author_type', $cols, true)) {
                $insert['author_type'] = $authorType;
            }
            if (in_array('author_role', $cols, true)) {
                $insert['author_role'] = ($authorType === 4) ? 'service' : 'mrk';
            }
            if (in_array('created_by_user_id', $cols, true)) {
                $insert['created_by_user_id'] = $user?->id;
            }
            if (in_array('user_id', $cols, true)) {
                $insert['user_id'] = $user?->id;
            }
            if (in_array('updated_by_user_id', $cols, true)) {
                $insert['updated_by_user_id'] = $user?->id;
            }
            if (in_array('approval_status', $cols, true)) {
                $insert['approval_status'] = ($authorType === 4) ? 'pending' : 'approved';
            } elseif (in_array('status', $cols, true)) {
                $insert['status'] = ($authorType === 4) ? 'pending' : 'approved';
            }
            if (in_array('created_at', $cols, true)) {
                $insert['created_at'] = now();
                $insert['updated_at'] = now();
            }

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
                $value = (float) str_replace([' ', ','], ['', '.'], $value);
            }

            $user = Auth::user();
            $app = Application::find($applicationId);
            $cols = Schema::getColumnListing('plan_works');

            $update = [$field => $value];

            if (in_array('author_type', $cols, true)) {
                $update['author_type'] = $this->resolveAuthorType($user, $app);
            }
            if (in_array('updated_by_user_id', $cols, true)) {
                $update['updated_by_user_id'] = $user?->id;
            }
            if ($field === 'price' && in_array('is_custom_price', $cols, true)) {
                $update['is_custom_price'] = true;
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
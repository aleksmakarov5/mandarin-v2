<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\SparePart;
use App\Models\SparePartHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class SparePartController extends Controller
{
    /**
     * Определение роли / автора:
     * 0 - Автоматически (из калькуляции)
     * 1 - МРК
     * 2 - Наставник
     * 3 - Запчастист (СПМ)
     * 4 - Сервис / СТО
     * 5 - Другой пользователь
     */
    protected function resolveAuthorType(?\App\Models\User $user, ?\App\Models\Application $application): int
    {
        if (!$user) {
            return 0;
        }

        // 1. Автосервис / СТО (точно как в PlanWorkController)
        if (method_exists($user, 'isSto') && $user->isSto()) {
            return 4;
        }

        $userGroupId = (int) ($user->user_group_id ?? $user->GroupId ?? $user->group_id ?? 0);
        if ($userGroupId === 8 || !empty($user->id_service) || !empty($user->service_id)) {
            return 4;
        }

        if (!$application) {
            return 5;
        }

        $userId = (int) $user->id;

        // 2. МРК дела
        $mrkId = (int) ($application->id_MRK ?? $application->id_mrk ?? $application->mrk_id ?? 0);
        if ($mrkId > 0 && $mrkId === $userId) {
            return 1;
        }

        // 3. Наставник дела
        $mentorId = (int) ($application->id_mentor ?? $application->mentor_id ?? 0);
        if ($mentorId > 0 && $mentorId === $userId) {
            return 2;
        }

        // 4. Запчастист дела (СПМ)
        $spmId = (int) ($application->id_spm ?? $application->spm_id ?? 0);
        if ($spmId > 0 && $spmId === $userId) {
            return 3;
        }

        // 5. Проверка по группе пользователя
        if ($userGroupId === 5)
            return 1;
        if ($userGroupId === 6 || $userGroupId === 10)
            return 2;
        if ($userGroupId === 7 || $userGroupId === 9)
            return 3;

        return 5;
    }

    public function index(Request $request, ...$params): JsonResponse
    {
        try {
            $applicationId = $this->resolveApplicationId($request, $params);
            if (!$applicationId) {
                return response()->json(['data' => [], 'message' => 'Не указан ID дела'], 400);
            }

            $columns = Schema::getColumnListing('spare_parts');
            $appKey = in_array('application_id', $columns) ? 'application_id' : 'id_application';
            $hasCommentsTable = Schema::hasTable('spare_part_comments');

            $query = SparePart::with('creator:id,name')
                ->where($appKey, $applicationId)
                ->orderBy(in_array('is_small_parts', $columns) ? 'is_small_parts' : 'id', 'asc')
                ->orderBy('id', 'asc');

            $hasLastName = Schema::hasColumn('users', 'last_name');
            $userFields = $hasLastName ? 'id,name,last_name' : 'id,name';

            if ($hasCommentsTable) {
                $query->with([
                    'comments' => function ($q) use ($userFields) {
                        $q->orderBy('created_at', 'asc')->with("user:{$userFields}");
                    }
                ]);
            }

            $parts = $query->get();

            // Подстраховка виртуального комментария
            foreach ($parts as $part) {
                if ($hasCommentsTable && $part->relationLoaded('comments') && $part->comments->isEmpty() && !empty($part->comment)) {
                    $part->setRelation('comments', collect([
                        [
                            'id' => 0,
                            'spare_part_id' => $part->id,
                            'user_id' => $part->updated_by_user_id ?? $part->created_by_user_id,
                            'comment' => (string) $part->comment,
                            'created_at' => $part->updated_at ?? $part->created_at,
                            'user' => $part->creator ?? null,
                        ]
                    ]));
                }
            }

            $app = Application::find($applicationId);
            $statusId = (int) ($app->id_status ?? $app->status_id ?? 0);

            // Определение КАСКО
            $ubType = mb_strtolower((string) ($app->ub_type ?? ''));
            $isCasco = str_contains($ubType, 'каско') || str_contains($ubType, 'casco') || $ubType === '1';

            // Определение СТО через единый метод
            $user = Auth::user();
            $isSto = $user && method_exists($user, 'isSto') ? (bool) $user->isSto() : false;
            if (!$isSto && $user) {
                $groupId = (int) ($user->user_group_id ?? $user->GroupId ?? $user->group_id ?? 0);
                $isSto = ($groupId === 8);
            }

            // Извлечение текущей скидки дела
            $firstWithIznos = $parts->first(fn($p) => (float) ($p->iznos ?? 0) > 0);
            $discount = (float) ($firstWithIznos->iznos ?? $app->discount_percent ?? 0);

            return response()->json([
                'data' => $parts,
                'is_casco' => $isCasco,
                'is_sto' => $isSto,
                'status_id' => $statusId,
                'discount_percent' => $discount,
            ]);
        } catch (\Throwable $e) {
            Log::error('SparePartController@index error: ' . $e->getMessage());
            return response()->json(['data' => [], 'message' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request, ...$params): JsonResponse
    {
        try {
            $applicationId = $this->resolveApplicationId($request, $params);
            $user = Auth::user();

            $columns = Schema::getColumnListing('spare_parts');
            $appKey = in_array('application_id', $columns) ? 'application_id' : 'id_application';
            $app = Application::find($applicationId);

            // 1. Извлекаем текущую скидку
            $discount = (float) ($app->discount_percent ?? 0);
            if ($discount <= 0) {
                $firstWithIznos = SparePart::where($appKey, $applicationId)
                    ->where('iznos', '>', 0)
                    ->first();
                $discount = (float) ($firstWithIznos->iznos ?? 0);
            }

            $priceIc = (float) $request->input('price_ic', 0);
            $multiplier = (100.0 - $discount) / 100.0;
            $priceHalf = $discount > 0 ? round($priceIc * $multiplier, 2) : $priceIc;

            // Вычисляем author_type
            $authorType = $this->resolveAuthorType($user, $app);

            $data = [
                $appKey => $applicationId,
                'name' => trim($request->input('name', 'Новая деталь')),
                'part_number' => trim($request->input('part_number', '')),
                'count' => 1,
                'status' => 'В поиске',
            ];

            if (in_array('price_ic', $columns))
                $data['price_ic'] = $priceIc;
            if (in_array('price_ic_full', $columns))
                $data['price_ic_full'] = $priceIc;
            if (in_array('price_ic_half', $columns))
                $data['price_ic_half'] = $priceHalf;
            if (in_array('price_pzn', $columns))
                $data['price_pzn'] = (float) $request->input('price_pzn', 0);
            if (in_array('price_sto', $columns))
                $data['price_sto'] = (float) $request->input('price_pzn', 0);
            if (in_array('iznos', $columns))
                $data['iznos'] = $discount;
            if (in_array('is_manual', $columns))
                $data['is_manual'] = true;
            if (in_array('author_type', $columns))
                $data['author_type'] = $authorType;
            if (in_array('created_by_user_id', $columns))
                $data['created_by_user_id'] = $user?->id;
            if (in_array('updated_by_user_id', $columns))
                $data['updated_by_user_id'] = $user?->id;

            $part = SparePart::create($data);

            try {
                $this->logHistory($part, 'created', null, null, 'Создана деталь: ' . $part->name);
            } catch (\Throwable $th) {
            }

            $updatedSmallPart = $this->syncSmallPartsPrice($applicationId);

            return response()->json([
                'message' => 'Позиция добавлена',
                'data' => $part->load('creator:id,name'),
                'updated_small_part' => $updatedSmallPart,
            ], 201);
        } catch (\Throwable $e) {
            Log::error('SparePartController@store error: ' . $e->getMessage());
            return response()->json(['message' => 'Ошибка создания детали: ' . $e->getMessage()], 500);
        }
    }

    public function updateField(Request $request, ...$params): JsonResponse
    {
        try {
            $applicationId = $this->resolveApplicationId($request, $params);
            $user = Auth::user();

            $partId = (int) ($request->route('partId') ?? $request->route('part') ?? $request->input('part_id') ?? 0);
            if (!$partId && isset($params[1]) && is_numeric($params[1])) {
                $partId = (int) $params[1];
            }

            $columns = Schema::getColumnListing('spare_parts');
            $appKey = in_array('application_id', $columns) ? 'application_id' : 'id_application';

            $part = SparePart::where($appKey, $applicationId)->findOrFail($partId);
            $app = Application::find($applicationId);

            $field = $request->input('field');
            $newValue = $request->input('value');
            $oldValue = $part->{$field};

            $dbField = $field;
            if ($field === 'price_ic' && !in_array('price_ic', $columns) && in_array('price_ic_full', $columns)) {
                $dbField = 'price_ic_full';
            }

            // Статус "Заказана" -> авто дата заказа
            if ($field === 'status' && mb_strtolower((string) $newValue) === 'заказана') {
                if (in_array('order_date', $columns) && empty($part->order_date)) {
                    $part->order_date = Carbon::now()->toDateString();
                }
            }
            if ($field === 'status' && mb_strtolower((string) $newValue) === 'в поиске') {
                if (in_array('order_date', $columns)) {
                    $part->order_date = null;
                }
            }

            // Обработка дат
            if (in_array($field, ['delivery_date', 'order_date'])) {
                $newValue = !empty($newValue) ? Carbon::parse($newValue)->format('Y-m-d') : null;
            }

            if ($request->has('price_ic_half') && in_array('price_ic_half', $columns)) {
                $part->price_ic_half = (float) $request->input('price_ic_half');
            }

            $part->{$dbField} = $newValue;

            // Обновление автора: только если строка ручная и не мелкие детали
            $isManual = (bool) ($part->is_manual ?? false)
                || !empty($part->created_by_user_id)
                || ((int) ($part->author_type ?? 0) !== 0);

            if ($isManual && !($part->is_small_parts ?? false) && in_array('author_type', $columns)) {
                $newAuthorType = $this->resolveAuthorType($user, $app);
                if ($newAuthorType > 0) {
                    $part->author_type = $newAuthorType;
                }
            }

            if (in_array('updated_by_user_id', $columns)) {
                $part->updated_by_user_id = $user?->id;
            }

            $part->save();

            // Логирование аудита
            $this->logHistory($part, 'updated', $field, (string) $oldValue, (string) $newValue);

            // Пересчёт мелких деталей
            $updatedSmallPart = null;
            if (in_array($field, ['price_ic', 'price_ic_full', 'price_ic_half', 'price_pzn', 'price_sto', 'count'])) {
                $updatedSmallPart = $this->syncSmallPartsPrice($applicationId);
            }

            // Безопасная синхронизация даты поставки
            if ($field === 'delivery_date') {
                try {
                    $this->syncMaxDeliveryDateToApplication($applicationId);
                } catch (\Throwable $th) {
                    Log::warning('syncMaxDeliveryDateToApplication failed: ' . $th->getMessage());
                }
            }

            return response()->json([
                'message' => 'Сохранено',
                'data' => $part->fresh(),
                'updated_small_part' => $updatedSmallPart,
            ]);
        } catch (\Throwable $e) {
            Log::error('SparePartController@updateField error: ' . $e->getMessage());
            return response()->json(['message' => 'Ошибка сохранения: ' . $e->getMessage()], 500);
        }
    }

    public function toggleSelectedMrk(Request $request, int $applicationId, int $partId): JsonResponse
    {
        try {
            $columns = Schema::getColumnListing('spare_parts');
            $appKey = in_array('application_id', $columns) ? 'application_id' : 'id_application';

            $part = SparePart::where($appKey, $applicationId)->findOrFail($partId);
            $type = $request->input('type'); // 'original', 'analogue', 'used', 'sto'

            $old = $part->selected_mrk;
            $new = ($part->selected_mrk === $type) ? null : $type;

            $part->selected_mrk = $new;

            if (in_array('selected_price', $columns)) {
                $typeToNum = [
                    'original' => 1,
                    'analogue' => 2,
                    'used' => 3,
                    'sto' => 4,
                ];
                $part->selected_price = $new ? ($typeToNum[$new] ?? null) : null;
            }

            $user = Auth::user();
            $app = Application::find($applicationId);

            $isManual = (bool) ($part->is_manual ?? false)
                || !empty($part->created_by_user_id)
                || ((int) ($part->author_type ?? 0) !== 0);

            if ($isManual && !($part->is_small_parts ?? false) && in_array('author_type', $columns)) {
                $newAuthorType = $this->resolveAuthorType($user, $app);
                if ($newAuthorType > 0) {
                    $part->author_type = $newAuthorType;
                }
            }

            if (in_array('updated_by_user_id', $columns)) {
                $part->updated_by_user_id = $user?->id;
            }

            $part->save();

            try {
                $this->logHistory($part, 'supplier_selected', 'selected_mrk', (string) $old, (string) $new);
            } catch (\Throwable $th) {
            }

            return response()->json(['data' => $part->fresh()]);
        } catch (\Throwable $e) {
            Log::error('toggleSelectedMrk error: ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function handleSmallParts(Request $request, ...$params): JsonResponse
    {
        try {
            $applicationId = $this->resolveApplicationId($request, $params);
            $columns = Schema::getColumnListing('spare_parts');
            $appKey = in_array('application_id', $columns) ? 'application_id' : 'id_application';
            $hasSmallPartsCol = in_array('is_small_parts', $columns);

            $existing = SparePart::where($appKey, $applicationId)
                ->where(function ($q) use ($hasSmallPartsCol) {
                    if ($hasSmallPartsCol) {
                        $q->where('is_small_parts', true);
                    }
                    $q->orWhereRaw('LOWER(name) LIKE ?', ['%мелкие детали%']);
                })->first();

            if ($existing) {
                return response()->json([
                    'exists' => true,
                    'part_id' => $existing->id,
                    'message' => 'Позиция "Мелкие детали" уже присутствует в смете.',
                ]);
            }

            $app = Application::find($applicationId);
            $discount = (float) ($app->discount_percent ?? 0);

            $totals = $this->calculateSumTotals($applicationId);
            $priceIc = round($totals['sum_ic'] * 0.02, 2);
            $priceHalf = round($totals['sum_half'] * 0.02, 2);
            $pricePzn = round($totals['sum_pzn'] * 0.02, 2);

            $user = Auth::user();

            $data = [
                $appKey => $applicationId,
                'name' => 'МЕЛКИЕ ДЕТАЛИ',
                'part_number' => '2%',
                'count' => 1,
                'status' => 'В поиске',
            ];

            if (in_array('price_ic', $columns))
                $data['price_ic'] = $priceIc;
            if (in_array('price_ic_full', $columns))
                $data['price_ic_full'] = $priceIc;
            if (in_array('price_ic_half', $columns))
                $data['price_ic_half'] = $priceHalf;
            if (in_array('price_pzn', $columns))
                $data['price_pzn'] = $pricePzn;
            if (in_array('price_sto', $columns))
                $data['price_sto'] = $pricePzn;
            if (in_array('iznos', $columns))
                $data['iznos'] = $discount;
            if ($hasSmallPartsCol)
                $data['is_small_parts'] = true;
            if (in_array('is_manual', $columns))
                $data['is_manual'] = false;
            if (in_array('created_by_user_id', $columns))
                $data['created_by_user_id'] = $user?->id;

            $part = SparePart::create($data);

            return response()->json([
                'exists' => false,
                'action' => 'created',
                'data' => $part->fresh(),
            ], 201);
        } catch (\Throwable $e) {
            Log::error('handleSmallParts error: ' . $e->getMessage());
            return response()->json(['message' => 'Ошибка создания мелких деталей: ' . $e->getMessage()], 500);
        }
    }

    protected function syncSmallPartsPrice(int $applicationId): ?SparePart
    {
        try {
            $columns = Schema::getColumnListing('spare_parts');
            $appKey = in_array('application_id', $columns) ? 'application_id' : 'id_application';
            $hasSmallPartsCol = in_array('is_small_parts', $columns);

            $query = SparePart::where($appKey, $applicationId);

            $smallPart = $query->where(function ($q) use ($hasSmallPartsCol) {
                if ($hasSmallPartsCol) {
                    $q->where('is_small_parts', true);
                }
                $q->orWhereRaw('LOWER(name) LIKE ?', ['%мелкие детали%']);
            })->first();

            if (!$smallPart) {
                return null;
            }

            $totals = $this->calculateSumTotals($applicationId, $smallPart->id);

            $discount = (float) $totals['discount'];
            $newPriceIc = round($totals['sum_ic'] * 0.02, 2);
            $newPriceHalf = $discount > 0 ? round($totals['sum_half'] * 0.02, 2) : $newPriceIc;
            $newPricePzn = round($totals['sum_pzn'] * 0.02, 2);

            $updateData = [];
            if (in_array('price_ic', $columns))
                $updateData['price_ic'] = $newPriceIc;
            if (in_array('price_ic_full', $columns))
                $updateData['price_ic_full'] = $newPriceIc;
            if (in_array('price_ic_half', $columns))
                $updateData['price_ic_half'] = $newPriceHalf;
            if (in_array('price_pzn', $columns))
                $updateData['price_pzn'] = $newPricePzn;
            if (in_array('price_sto', $columns))
                $updateData['price_sto'] = $newPricePzn;
            if (in_array('iznos', $columns))
                $updateData['iznos'] = $discount;

            if (!empty($updateData)) {
                DB::table('spare_parts')
                    ->where('id', $smallPart->id)
                    ->update($updateData);
            }

            return SparePart::find($smallPart->id);
        } catch (\Throwable $e) {
            Log::warning('Не удалось пересчитать мелкие детали: ' . $e->getMessage());
            return null;
        }
    }

    protected function calculateSumTotals(int $applicationId, ?int $excludeId = null): array
    {
        $columns = Schema::getColumnListing('spare_parts');
        $appKey = in_array('application_id', $columns) ? 'application_id' : 'id_application';
        $hasSmallPartsCol = in_array('is_small_parts', $columns);

        $app = Application::find($applicationId);

        $discount = (float) ($app->discount_percent ?? 0);
        if ($discount <= 0) {
            $rowWithIznos = SparePart::where($appKey, $applicationId)
                ->where('iznos', '>', 0)
                ->first();
            $discount = (float) ($rowWithIznos->iznos ?? 0);
        }

        $multiplier = $discount > 0 ? ((100.0 - $discount) / 100.0) : 1.0;

        $query = SparePart::where($appKey, $applicationId);

        if ($hasSmallPartsCol) {
            $query->where('is_small_parts', false);
        } else {
            $query->whereRaw('LOWER(name) NOT LIKE ?', ['%мелкие детали%']);
        }

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $parts = $query->get();

        $sumIc = 0.0;
        $sumHalf = 0.0;
        $sumPzn = 0.0;

        foreach ($parts as $p) {
            $ic = (float) ($p->price_ic ?? $p->price_ic_full ?? 0);
            $half = (float) ($p->price_ic_half ?? 0);

            if ($discount > 0) {
                if ($half <= 0 && $ic > 0) {
                    $half = round($ic * $multiplier, 2);
                }
            } else {
                $half = $ic;
            }

            $pzn = (float) ($p->price_pzn ?? $p->price_sto ?? 0);
            if ($pzn <= 0) {
                $pzn = $ic;
            }

            $sumIc += $ic;
            $sumHalf += $half;
            $sumPzn += $pzn;
        }

        return [
            'discount' => $discount,
            'sum_ic' => $sumIc,
            'sum_half' => $sumHalf,
            'sum_pzn' => $sumPzn,
        ];
    }

    public function destroy(Request $request, ...$params): JsonResponse
    {
        try {
            $partId = (int) ($params[1] ?? $params[0] ?? $request->route('partId') ?? $request->route('part') ?? 0);
            $part = SparePart::findOrFail($partId);
            $part->delete();

            return response()->json(['message' => 'Позиция удалена']);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function history(Request $request, ...$params): JsonResponse
    {
        try {
            $applicationId = $this->resolveApplicationId($request, $params);

            if (!Schema::hasTable('spare_part_histories')) {
                return response()->json(['data' => []]);
            }

            $cols = Schema::getColumnListing('spare_part_histories');
            $appCol = in_array('application_id', $cols) ? 'application_id' : (in_array('id_application', $cols) ? 'id_application' : null);

            if (!$appCol) {
                return response()->json(['data' => []]);
            }

            $hasLastName = Schema::hasColumn('users', 'last_name');

            $history = DB::table('spare_part_histories')
                ->leftJoin('users', 'spare_part_histories.user_id', '=', 'users.id')
                ->where("spare_part_histories.{$appCol}", $applicationId)
                ->select(
                    'spare_part_histories.*',
                    'users.name as user_name',
                    $hasLastName ? 'users.last_name as user_last_name' : DB::raw('NULL as user_last_name')
                )
                ->orderBy('spare_part_histories.id', 'desc')
                ->limit(100)
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'created_at' => $item->created_at,
                        'action_type' => $item->action_type ?? 'изменение',
                        'field_name' => $item->field_name ?? '',
                        'old_value' => $item->old_value,
                        'new_value' => $item->new_value,
                        'user' => [
                            'name' => $item->user_name ?? 'Система',
                            'last_name' => $item->user_last_name ?? '',
                        ],
                    ];
                });

            return response()->json(['data' => $history]);
        } catch (\Throwable $e) {
            Log::error('SparePartController@history error: ' . $e->getMessage());
            return response()->json(['data' => []]);
        }
    }

    protected function syncMaxDeliveryDateToApplication(int $applicationId): void
    {
        try {
            if (!$applicationId) {
                return;
            }

            $partColumns = Schema::getColumnListing('spare_parts');
            $appKey = in_array('application_id', $partColumns) ? 'application_id' : 'id_application';

            $maxDate = SparePart::where($appKey, $applicationId)
                ->whereNotNull('delivery_date')
                ->where('delivery_date', '!=', '')
                ->where('delivery_date', '!=', '0000-00-00')
                ->max('delivery_date');

            if (!$maxDate) {
                return;
            }

            $appColumns = Schema::getColumnListing('applications');
            $targetCol = null;

            if (in_array('sp_date', $appColumns)) {
                $targetCol = 'sp_date';
            } elseif (in_array('sp_date_plan', $appColumns)) {
                $targetCol = 'sp_date_plan';
            } elseif (in_array('delivery_date', $appColumns)) {
                $targetCol = 'delivery_date';
            }

            if ($targetCol) {
                $cleanDate = Carbon::parse($maxDate)->format('Y-m-d');
                DB::table('applications')
                    ->where('id', $applicationId)
                    ->update([$targetCol => $cleanDate]);
            }
        } catch (\Throwable $e) {
            Log::warning('syncMaxDeliveryDateToApplication error: ' . $e->getMessage());
        }
    }

    protected function logHistory(SparePart $part, string $action, ?string $field, ?string $old, ?string $new, ?string $description = null): void
    {
        try {
            $cleanOld = trim((string) $old);
            $cleanNew = trim((string) $new);
            if ($action === 'updated' && $cleanOld === $cleanNew) {
                return;
            }
            if (!Schema::hasTable('spare_part_histories')) {
                return;
            }

            $cols = Schema::getColumnListing('spare_part_histories');
            $user = Auth::user();

            $partColumns = Schema::getColumnListing('spare_parts');
            $appKey = in_array('application_id', $partColumns) ? 'application_id' : 'id_application';
            $appId = $part->{$appKey} ?? $part->application_id ?? 0;

            $record = [];

            if (in_array('application_id', $cols))
                $record['application_id'] = $appId;
            elseif (in_array('id_application', $cols))
                $record['id_application'] = $appId;

            if (in_array('spare_part_id', $cols))
                $record['spare_part_id'] = $part->id;
            elseif (in_array('id_spare_part', $cols))
                $record['id_spare_part'] = $part->id;

            if (in_array('user_id', $cols))
                $record['user_id'] = $user?->id;
            if (in_array('action_type', $cols))
                $record['action_type'] = $action;
            if (in_array('field_name', $cols))
                $record['field_name'] = $field;
            if (in_array('old_value', $cols))
                $record['old_value'] = $old;
            if (in_array('new_value', $cols))
                $record['new_value'] = $new;
            if (in_array('description', $cols))
                $record['description'] = $description;
            if (in_array('created_at', $cols))
                $record['created_at'] = now();
            if (in_array('updated_at', $cols))
                $record['updated_at'] = now();

            DB::table('spare_part_histories')->insert($record);
        } catch (\Throwable $e) {
            Log::error('Ошибка записи истории запчастей: ' . $e->getMessage());
        }
    }

    protected function resolveApplicationId(Request $request, array $params = []): int
    {
        if (!empty($params) && is_numeric($params[0]) && (int) $params[0] > 0) {
            return (int) $params[0];
        }

        $route = $request->route();
        if ($route) {
            $fromRoute = $route->parameter('id')
                ?? $route->parameter('application')
                ?? $route->parameter('applicationId');
            if ($fromRoute) {
                return is_object($fromRoute) ? (int) $fromRoute->id : (int) $fromRoute;
            }
        }

        if ($request->filled('application_id')) {
            return (int) $request->input('application_id');
        }

        $segments = $request->segments();
        foreach ($segments as $idx => $seg) {
            if ($seg === 'applications' && isset($segments[$idx + 1]) && is_numeric($segments[$idx + 1])) {
                return (int) $segments[$idx + 1];
            }
        }

        return 0;
    }

    public function storeComment(Request $request, ...$params): JsonResponse
    {
        try {
            $text = trim((string) $request->input('comment', ''));
            if ($text === '') {
                return response()->json(['message' => 'Текст комментария не может быть пустым'], 422);
            }

            $partId = 0;
            if ($request->route('partId')) {
                $partId = (int) $request->route('partId');
            } elseif ($request->route('spare_part')) {
                $partId = (int) $request->route('spare_part');
            } elseif (count($params) >= 2 && is_numeric($params[1])) {
                $partId = (int) $params[1];
            } elseif (count($params) === 1 && is_numeric($params[0])) {
                $partId = (int) $params[0];
            }

            if (!$partId) {
                return response()->json(['message' => 'Не удалось определить ID запчасти'], 400);
            }

            $part = SparePart::findOrFail($partId);
            $userId = Auth::id();
            $user = Auth::user();

            $hasLastName = Schema::hasColumn('users', 'last_name');
            $userPayload = [
                'id' => $user?->id ?? $userId,
                'name' => $user?->name ?? 'Пользователь',
                'last_name' => $hasLastName ? ($user?->last_name ?? '') : '',
            ];

            $hasCommentsTable = Schema::hasTable('spare_part_comments');

            if ($hasCommentsTable) {
                $commentId = DB::table('spare_part_comments')->insertGetId([
                    'spare_part_id' => $part->id,
                    'user_id' => $userId,
                    'comment' => $text,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $createdCommentData = [
                    'id' => $commentId,
                    'spare_part_id' => $part->id,
                    'user_id' => $userId,
                    'comment' => $text,
                    'created_at' => now()->toDateTimeString(),
                    'user' => $userPayload,
                ];
            } else {
                $createdCommentData = [
                    'id' => time(),
                    'spare_part_id' => $part->id,
                    'user_id' => $userId,
                    'comment' => $text,
                    'created_at' => now()->toDateTimeString(),
                    'user' => $userPayload,
                ];
            }

            if (Schema::hasColumn('spare_parts', 'comment')) {
                $part->comment = $text;
                $part->save();
            }

            try {
                $this->logHistory($part, 'updated', 'comment', null, $text);
            } catch (\Throwable $th) {
            }

            return response()->json([
                'message' => 'Комментарий успешно добавлен',
                'data' => $createdCommentData,
            ], 201);
        } catch (\Throwable $e) {
            Log::error('Ошибка storeComment: ' . $e->getMessage());
            return response()->json(['message' => 'Ошибка при сохранении комментария: ' . $e->getMessage()], 500);
        }
    }

    public function bulkIncreasePzn(Request $request, int $applicationId): JsonResponse
    {
        $request->validate([
            'percent' => 'required|numeric|min:0.01|max:500',
        ]);

        $percent = (float) $request->input('percent');
        $multiplier = 1 + ($percent / 100);

        $columns = Schema::getColumnListing('spare_parts');
        $appKey = in_array('application_id', $columns) ? 'application_id' : 'id_application';
        $skCol = in_array('price_ic', $columns) ? 'price_ic' : (in_array('price_sk', $columns) ? 'price_sk' : 'price_ic');

        $parts = SparePart::where($appKey, $applicationId)
            ->where('is_small_parts', false)
            ->get();

        DB::transaction(function () use ($parts, $multiplier, $percent, $skCol) {
            foreach ($parts as $part) {
                $baseSk = (float) ($part->{$skCol} ?? 0);
                if ($baseSk > 0) {
                    $oldPzn = $part->price_pzn;
                    $newPzn = round($baseSk * $multiplier, 2);

                    $part->price_pzn = $newPzn;
                    $part->save();

                    $this->logHistory($part, 'updated', 'price_pzn', (string) $oldPzn, (string) $newPzn, "Массовое увеличение на +{$percent}%");
                }
            }
        });

        $this->syncSmallPartsPrice($applicationId);

        $updatedParts = SparePart::with('creator:id,name,last_name')
            ->where($appKey, $applicationId)
            ->orderBy('is_small_parts', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        return response()->json([
            'message' => "Цены ПЗН успешно увеличены на {$percent}%",
            'data' => $updatedParts,
        ]);
    }

    public function updateCascoDiscount(Request $request, ...$params): JsonResponse
    {
        try {
            $applicationId = $this->resolveApplicationId($request, $params);

            $request->validate([
                'discount' => 'required|numeric|min:0|max:99',
            ]);

            $discount = (float) $request->input('discount');
            $multiplier = (100.0 - $discount) / 100.0;

            $app = Application::find($applicationId);
            if ($app) {
                $columnsApp = Schema::getColumnListing('applications');
                if (in_array('discount_percent', $columnsApp)) {
                    $app->discount_percent = $discount;
                }
                $app->save();
            }

            $columns = Schema::getColumnListing('spare_parts');
            $appKey = in_array('application_id', $columns) ? 'application_id' : 'id_application';
            $hasSmallCol = in_array('is_small_parts', $columns);

            $regularParts = SparePart::where($appKey, $applicationId)
                ->where(function ($q) use ($hasSmallCol) {
                    if ($hasSmallCol) {
                        $q->where('is_small_parts', false);
                    }
                    $q->whereRaw('LOWER(name) NOT LIKE ?', ['%мелкие детали%']);
                })
                ->get();

            $sumHalf = 0.0;
            $sumIc = 0.0;

            foreach ($regularParts as $part) {
                $base = (float) ($part->price_ic ?? $part->price_ic_full ?? 0);
                $newHalf = round($base * $multiplier, 2);

                $updateData = [];
                if (in_array('iznos', $columns)) {
                    $updateData['iznos'] = $discount;
                }
                if (in_array('price_ic_half', $columns)) {
                    $updateData['price_ic_half'] = $newHalf;
                }

                if (!empty($updateData)) {
                    DB::table('spare_parts')
                        ->where('id', $part->id)
                        ->update($updateData);
                }

                $sumIc += $base;
                $sumHalf += $newHalf;
            }

            $smallPart = SparePart::where($appKey, $applicationId)
                ->where(function ($q) use ($hasSmallCol) {
                    if ($hasSmallCol) {
                        $q->where('is_small_parts', true);
                    }
                    $q->orWhereRaw('LOWER(name) LIKE ?', ['%мелкие детали%']);
                })
                ->first();

            if ($smallPart) {
                $smallPriceIc = round($sumIc * 0.02, 2);
                $smallPriceHalf = round($sumHalf * 0.02, 2);

                $smallUpdate = [];
                if (in_array('iznos', $columns))
                    $smallUpdate['iznos'] = $discount;
                if (in_array('price_ic', $columns))
                    $smallUpdate['price_ic'] = $smallPriceIc;
                if (in_array('price_ic_full', $columns))
                    $smallUpdate['price_ic_full'] = $smallPriceIc;
                if (in_array('price_ic_half', $columns))
                    $smallUpdate['price_ic_half'] = $smallPriceHalf;

                DB::table('spare_parts')
                    ->where('id', $smallPart->id)
                    ->update($smallUpdate);
            }

            $freshParts = SparePart::with('creator:id,name,last_name')
                ->where($appKey, $applicationId)
                ->orderBy($hasSmallCol ? 'is_small_parts' : 'id', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            return response()->json([
                'message' => 'Скидка СК успешно записана к каждой позиции',
                'discount_percent' => $discount,
                'data' => $freshParts,
            ]);
        } catch (\Throwable $e) {
            Log::error('Ошибка применения скидки КАСКО: ' . $e->getMessage());
            return response()->json(['message' => 'Ошибка: ' . $e->getMessage()], 500);
        }
    }
}
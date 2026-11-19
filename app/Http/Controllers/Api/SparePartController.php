<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\SparePart;
use App\Models\SparePartHistory;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SparePartController extends Controller
{
    public function index(Request $request, ...$params): JsonResponse
    {
        $user = $request->user();
        $applicationId = $this->resolveApplicationId($request, $params);
        $columns = \Illuminate\Support\Facades\Schema::getColumnListing('spare_parts');
        $appKey = in_array('application_id', $columns) ? 'application_id' : 'id_application';
        $hasSmallCol = in_array('is_small_parts', $columns);

        $app = \App\Models\Application::find($applicationId);
        $ubType = mb_strtolower((string) ($app->ub_type ?? ''));
        $isCasco = str_contains($ubType, 'каско') || str_contains($ubType, 'casco') || $ubType === '1';

        $parts = \App\Models\SparePart::with('creator:id,name,last_name')
            ->where($appKey, $applicationId)
            ->orderBy($hasSmallCol ? 'is_small_parts' : 'id', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        // Скидку берём из столбца iznos первой запчасти (или из заявки)
        $firstWithIznos = $parts->first(fn($p) => (float) ($p->iznos ?? 0) > 0);
        $discount = (float) ($firstWithIznos->iznos ?? $app->discount_percent ?? 0);

        return response()->json([
            'data' => $parts,
            'is_casco' => $isCasco,
            'discount_percent' => $discount, // передаем точный процент в плашку
            'is_sto' => $user->isSto(),
            'status_id' => $app->id_status ?? $app->status_id ?? null,
        ]);
    }

    /**
     * Быстрое создание новой строки
     */
    /**
     * Быстрое создание новой строки
     */
    public function store(Request $request, ...$params): JsonResponse
    {
        $applicationId = $this->resolveApplicationId($request, $params);
        $user = \Illuminate\Support\Facades\Auth::user();

        $columns = \Illuminate\Support\Facades\Schema::getColumnListing('spare_parts');
        $appKey = in_array('application_id', $columns) ? 'application_id' : 'id_application';

        $app = \App\Models\Application::find($applicationId);

        // 1. Извлекаем текущую скидку дела (из application или из столбца iznos других деталей)
        $discount = (float) ($app->discount_percent ?? 0);
        if ($discount <= 0) {
            $firstWithIznos = \App\Models\SparePart::where($appKey, $applicationId)
                ->where('iznos', '>', 0)
                ->first();
            $discount = (float) ($firstWithIznos->iznos ?? 0);
        }

        $priceIc = (float) $request->input('price_ic', 0);
        $multiplier = (100.0 - $discount) / 100.0;
        $priceHalf = $discount > 0 ? round($priceIc * $multiplier, 2) : $priceIc;

        // Вычисляем author_type для создающего пользователя
        $authorType = $this->resolveAuthorType($user, $app);

        $data = [
            $appKey => $applicationId,
            'name' => $request->input('name', 'Новая деталь'),
            'part_number' => $request->input('part_number', ''),
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

        $part = \App\Models\SparePart::create($data);

        try {
            $this->logHistory($part, 'created', null, null, 'Создана деталь: ' . $part->name);
        } catch (\Throwable $th) {
        }

        // 2. Пересчитываем мелкие детали с гарантией сохранения скидки
        $updatedSmallPart = $this->syncSmallPartsPrice($applicationId);

        return response()->json([
            'message' => 'Позиция добавлена',
            'data' => $part->load('creator:id,name,last_name'),
            'updated_small_part' => $updatedSmallPart,
        ], 201);
    }

    /**
     * Мгновенное обновление ячейки (при blur/contenteditable)
     */
    /**
     * Мгновенное обновление ячейки (при blur/contenteditable)
     */
    /**
     * Мгновенное обновление ячейки (при blur/contenteditable)
     */
    public function updateField(Request $request, ...$params): JsonResponse
    {
        try {
            $applicationId = $this->resolveApplicationId($request, $params);
            $user = \Illuminate\Support\Facades\Auth::user();

            $partId = (int) ($request->route('partId') ?? $request->route('part') ?? $request->input('part_id') ?? 0);
            if (!$partId && isset($params[1]) && is_numeric($params[1])) {
                $partId = (int) $params[1];
            }

            $columns = \Illuminate\Support\Facades\Schema::getColumnListing('spare_parts');
            $appKey = in_array('application_id', $columns) ? 'application_id' : 'id_application';

            $part = \App\Models\SparePart::where($appKey, $applicationId)->findOrFail($partId);
            $app = \App\Models\Application::find($applicationId);

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
                    $part->order_date = \Carbon\Carbon::now()->toDateString();
                }
            }
            // При статусе "В поиске" очищаем дату заказа
            if ($field === 'status' && mb_strtolower((string) $newValue) === 'в поиске') {
                if (in_array('order_date', $columns)) {
                    $part->order_date = null;
                }
            }

            // Если передана цена со скидкой вместе с ценой СК
            if ($request->has('price_ic_half') && in_array('price_ic_half', $columns)) {
                $part->price_ic_half = (float) $request->input('price_ic_half');
            }

            $part->{$dbField} = $newValue;

            // ПРАВИЛО: если строка НЕ загружена автоматически из калькуляции (author_type != 0),
            // перекрашиваем её в цвет изменившего пользователя
            if (in_array('author_type', $columns) && (int) ($part->author_type ?? 0) !== 0) {
                $part->author_type = $this->resolveAuthorType($user, $app);
            }

            if (in_array('updated_by_user_id', $columns)) {
                $part->updated_by_user_id = $user?->id;
            }

            $part->save();

            // Логируем в историю
            $this->logHistory($part, 'updated', $field, (string) $oldValue, (string) $newValue);

            // ВАЖНО: Включаем price_ic_half в список полей, вызывающих пересчет мелких деталей!
            $updatedSmallPart = null;
            if (in_array($field, ['price_ic', 'price_ic_full', 'price_ic_half', 'price_pzn', 'count'])) {
                $updatedSmallPart = $this->syncSmallPartsPrice($applicationId);
            }

            if ($field === 'delivery_date') {
                $this->syncMaxDeliveryDateToApplication($applicationId);
            }

            return response()->json([
                'message' => 'Сохранено',
                'data' => $part->fresh(),
                'updated_small_part' => $updatedSmallPart,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Ошибка в updateField: ' . $e->getMessage());
            return response()->json(['message' => 'Ошибка сохранения: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Выбор варианта МРК по правому клику мыши (selected_mrk)
     */
    public function toggleSelectedMrk(Request $request, int $applicationId, int $partId): JsonResponse
    {
        $part = SparePart::where('application_id', $applicationId)->findOrFail($partId);
        $type = $request->input('type'); // 'original', 'analogue', 'used', 'sto'

        $old = $part->selected_mrk;
        $new = ($part->selected_mrk === $type) ? null : $type;

        $part->selected_mrk = $new;
        $part->save();

        $this->logHistory($part, 'supplier_selected', 'selected_mrk', (string) $old, (string) $new);

        return response()->json(['data' => $part]);
    }

    /**
     * Работа с «Мелкими деталями» (создание или удаление)
     */
    /**
     * Работа с «Мелкими деталями» (создание или проверка наличия для удаления)
     */
    /**
     * Создание позиции Мелкие детали
     */
    public function handleSmallParts(Request $request, ...$params): JsonResponse
    {
        try {
            $applicationId = $this->resolveApplicationId($request, $params);
            $columns = \Illuminate\Support\Facades\Schema::getColumnListing('spare_parts');
            $appKey = in_array('application_id', $columns) ? 'application_id' : 'id_application';
            $hasSmallPartsCol = in_array('is_small_parts', $columns);

            // 1. Проверяем, существует ли уже
            $existing = \App\Models\SparePart::where($appKey, $applicationId)
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

            // 2. Получаем скидку дела
            $app = \App\Models\Application::find($applicationId);
            $discount = (float) ($app->discount_percent ?? 0);

            // 3. Считаем суммы остальных деталей
            $regularParts = \App\Models\SparePart::where($appKey, $applicationId)
                ->where(function ($q) use ($hasSmallPartsCol) {
                    if ($hasSmallPartsCol) {
                        $q->where('is_small_parts', false);
                    }
                    $q->whereRaw('LOWER(name) NOT LIKE ?', ['%мелкие детали%']);
                })
                ->get();

            $sumIc = 0.0;
            $sumHalf = 0.0;
            $sumPzn = 0.0;

            foreach ($regularParts as $p) {
                $base = (float) ($p->price_ic ?? $p->price_ic_full ?? 0);
                $half = (float) ($p->price_ic_half ?? 0);

                // Если у детали не было посчитано price_ic_half, но есть общая скидка
                if ($half <= 0 && $discount > 0) {
                    $half = round($base * ((100.0 - $discount) / 100.0), 2);
                } elseif ($half <= 0) {
                    $half = $base;
                }

                $pzn = (float) ($p->price_pzn ?? $p->price_sto ?? 0);
                if ($pzn <= 0)
                    $pzn = $base;

                $sumIc += $base;
                $sumHalf += $half;
                $sumPzn += $pzn;
            }

            $priceIc = round($sumIc * 0.02, 2);
            $priceHalf = round($sumHalf * 0.02, 2); // 2% от суммы со скидкой
            $pricePzn = round($sumPzn * 0.02, 2);

            $user = \Illuminate\Support\Facades\Auth::user();

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

            $part = \App\Models\SparePart::create($data);

            return response()->json([
                'exists' => false,
                'action' => 'created',
                'data' => $part->fresh(),
            ], 201);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Ошибка handleSmallParts: ' . $e->getMessage());
            return response()->json(['message' => 'Ошибка создания мелких деталей: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Пересчет цены мелких деталей с учетом типа дела (КАСКО vs ОСАГО)
     */
    protected function syncSmallPartsPrice(int $applicationId): ?\App\Models\SparePart
    {
        try {
            $columns = \Illuminate\Support\Facades\Schema::getColumnListing('spare_parts');
            $appKey = in_array('application_id', $columns) ? 'application_id' : 'id_application';
            $hasSmallPartsCol = in_array('is_small_parts', $columns);

            $query = \App\Models\SparePart::where($appKey, $applicationId);

            $smallPart = $query->where(function ($q) use ($hasSmallPartsCol) {
                if ($hasSmallPartsCol) {
                    $q->where('is_small_parts', true);
                }
                $q->orWhereRaw('LOWER(name) LIKE ?', ['%мелкие детали%']);
            })->first();

            if (!$smallPart) {
                return null;
            }

            // Суммируем остальные строки
            $totals = $this->calculateSumTotals($applicationId, $smallPart->id);

            $discount = (float) $totals['discount'];
            $newPriceIc = round($totals['sum_ic'] * 0.02, 2);

            // ЕСЛИ ЕСТЬ СКИДКА: считаем строго 2% от суммы со скидкой!
            if ($discount > 0) {
                $newPriceHalf = round($totals['sum_half'] * 0.02, 2);
            } else {
                $newPriceHalf = $newPriceIc;
            }

            $newPricePzn = round($totals['sum_pzn'] * 0.02, 2);

            // Прямое обновление в БД чтобы обойти любые возможные блокировки моделей
            $updateData = [];
            if (in_array('price_ic', $columns))
                $updateData['price_ic'] = $newPriceIc;
            if (in_array('price_ic_full', $columns))
                $updateData['price_ic_full'] = $newPriceIc;
            if (in_array('price_ic_half', $columns))
                $updateData['price_ic_half'] = $newPriceHalf;
            if (in_array('price_pzn', $columns))
                $updateData['price_pzn'] = $newPricePzn;
            if (in_array('iznos', $columns))
                $updateData['iznos'] = $discount;

            if (!empty($updateData)) {
                \Illuminate\Support\Facades\DB::table('spare_parts')
                    ->where('id', $smallPart->id)
                    ->update($updateData);
            }

            return \App\Models\SparePart::find($smallPart->id);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Не удалось пересчитать мелкие детали: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Безопасный подсчет сумм по деталям калькуляции
     */


    /**
     * Удаление строки с подтверждением
     */
    public function destroy(Request $request, ...$params): JsonResponse
    {
        try {
            $partId = (int) ($params[1] ?? $params[0] ?? $request->route('partId') ?? $request->route('part') ?? 0);
            $part = \App\Models\SparePart::findOrFail($partId);
            $part->delete();

            return response()->json(['message' => 'Позиция удалена']);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Получение истории изменений
     */
    /**
     * Получение истории изменений по запчастям дела
     */
    public function history(Request $request, ...$params): JsonResponse
    {
        try {
            $applicationId = $this->resolveApplicationId($request, $params);

            if (!\Illuminate\Support\Facades\Schema::hasTable('spare_part_histories')) {
                return response()->json(['data' => []]);
            }

            $cols = \Illuminate\Support\Facades\Schema::getColumnListing('spare_part_histories');
            $appCol = in_array('application_id', $cols) ? 'application_id' : (in_array('id_application', $cols) ? 'id_application' : null);

            if (!$appCol) {
                return response()->json(['data' => []]);
            }

            $hasLastName = \Illuminate\Support\Facades\Schema::hasColumn('users', 'last_name');

            $history = \Illuminate\Support\Facades\DB::table('spare_part_histories')
                ->leftJoin('users', 'spare_part_histories.user_id', '=', 'users.id')
                ->where("spare_part_histories.{$appCol}", $applicationId)
                ->select(
                    'spare_part_histories.*',
                    'users.name as user_name',
                    $hasLastName ? 'users.last_name as user_last_name' : \Illuminate\Support\Facades\DB::raw('NULL as user_last_name')
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
            \Illuminate\Support\Facades\Log::error('Ошибка получения истории: ' . $e->getMessage());
            return response()->json(['data' => []]);
        }
    }

    /**
     * Пересчет цены мелких деталей
     */


    protected function calculateSumTotals(int $applicationId, ?int $excludeId = null): array
    {
        $columns = \Illuminate\Support\Facades\Schema::getColumnListing('spare_parts');
        $appKey = in_array('application_id', $columns) ? 'application_id' : 'id_application';
        $hasSmallPartsCol = in_array('is_small_parts', $columns);

        $app = \App\Models\Application::find($applicationId);

        // Скидка: из заявки, либо из столбца iznos любой строки
        $discount = (float) ($app->discount_percent ?? 0);
        if ($discount <= 0) {
            $rowWithIznos = \App\Models\SparePart::where($appKey, $applicationId)
                ->where('iznos', '>', 0)
                ->first();
            $discount = (float) ($rowWithIznos->iznos ?? 0);
        }

        $multiplier = $discount > 0 ? ((100.0 - $discount) / 100.0) : 1.0;

        $query = \App\Models\SparePart::where($appKey, $applicationId);

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

            // Если у строки есть сохраненный price_ic_half > 0, берем его. 
            // Иначе, если есть скидка, рассчитываем на лету:
            $half = (float) ($p->price_ic_half ?? 0);
            if ($discount > 0) {
                if ($half <= 0 && $ic > 0) {
                    $half = round($ic * $multiplier, 2);
                }
            } else {
                $half = $ic;
            }

            $pzn = (float) ($p->price_pzn ?? $p->price_sto ?? 0);
            if ($pzn <= 0)
                $pzn = $ic;

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
    /**
     * Сравнение и обновление максимальной даты поставки в таблице applications
     */
    /**
     * Сравнение и безопасное обновление максимальной даты поставки в таблице applications
     */
    protected function syncMaxDeliveryDateToApplication(int $applicationId): void
    {
        try {
            if (!$applicationId) {
                return;
            }

            $partColumns = \Illuminate\Support\Facades\Schema::getColumnListing('spare_parts');
            $appKey = in_array('application_id', $partColumns) ? 'application_id' : 'id_application';

            // Находим максимальную непустую дату поставки среди всех запчастей этого дела
            $maxDate = \App\Models\SparePart::where($appKey, $applicationId)
                ->whereNotNull('delivery_date')
                ->where('delivery_date', '!=', '')
                ->where('delivery_date', '!=', '0000-00-00')
                ->max('delivery_date');

            if (!$maxDate) {
                return;
            }

            // Проверяем наличие колонок в таблице applications
            $appColumns = \Illuminate\Support\Facades\Schema::getColumnListing('applications');
            $targetCol = null;

            if (in_array('sp_date', $appColumns)) {
                $targetCol = 'sp_date';
            } elseif (in_array('sp_date_plan', $appColumns)) {
                $targetCol = 'sp_date_plan';
            } elseif (in_array('delivery_date', $appColumns)) {
                $targetCol = 'delivery_date';
            }

            if ($targetCol) {
                $cleanDate = \Carbon\Carbon::parse($maxDate)->format('Y-m-d');

                // Обновляем напрямую через DB::table, чтобы исключить падение из-за $fillable или связей
                \Illuminate\Support\Facades\DB::table('applications')
                    ->where('id', $applicationId)
                    ->update([$targetCol => $cleanDate]);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Не удалось синхронизировать максимальную дату поставки: ' . $e->getMessage());
        }
    }

    protected function logHistory(\App\Models\SparePart $part, string $action, ?string $field, ?string $old, ?string $new, ?string $description = null): void
    {
        try {

            // Если старое и новое значения равны (или оба пустые) — не пишем мусор в историю
            $cleanOld = trim((string) $old);
            $cleanNew = trim((string) $new);
            if ($action === 'updated' && $cleanOld === $cleanNew) {
                return;
            }
            if (!\Illuminate\Support\Facades\Schema::hasTable('spare_part_histories')) {
                return;
            }

            $cols = \Illuminate\Support\Facades\Schema::getColumnListing('spare_part_histories');
            $user = \Illuminate\Support\Facades\Auth::user();

            $partColumns = \Illuminate\Support\Facades\Schema::getColumnListing('spare_parts');
            $appKey = in_array('application_id', $partColumns) ? 'application_id' : 'id_application';
            $appId = $part->{$appKey} ?? $part->application_id ?? 0;

            $record = [];

            // Связь с делом
            if (in_array('application_id', $cols)) {
                $record['application_id'] = $appId;
            } elseif (in_array('id_application', $cols)) {
                $record['id_application'] = $appId;
            }

            // Связь с запчастью
            if (in_array('spare_part_id', $cols)) {
                $record['spare_part_id'] = $part->id;
            } elseif (in_array('id_spare_part', $cols)) {
                $record['id_spare_part'] = $part->id;
            }

            // Пользователь
            if (in_array('user_id', $cols)) {
                $record['user_id'] = $user?->id;
            }

            // Поля действия и изменений
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

            \Illuminate\Support\Facades\DB::table('spare_part_histories')->insert($record);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Ошибка записи истории запчастей: ' . $e->getMessage());
        }
    }

    /**
     * Универсальное определение ID дела из любого источника
     */
    protected function resolveApplicationId(Request $request, array $params = []): int
    {
        // 1. Из позиционных параметров функции
        if (!empty($params) && is_numeric($params[0]) && (int) $params[0] > 0) {
            return (int) $params[0];
        }

        // 2. Из маршрута route parameters
        $route = $request->route();
        if ($route) {
            $fromRoute = $route->parameter('id')
                ?? $route->parameter('application')
                ?? $route->parameter('applicationId');
            if ($fromRoute) {
                return is_object($fromRoute) ? (int) $fromRoute->id : (int) $fromRoute;
            }
        }

        // 3. Из тела запроса
        if ($request->filled('application_id')) {
            return (int) $request->input('application_id');
        }

        // 4. Из URI сегментов (например: /api/applications/561/spare-parts)
        $segments = $request->segments();
        foreach ($segments as $idx => $seg) {
            if ($seg === 'applications' && isset($segments[$idx + 1]) && is_numeric($segments[$idx + 1])) {
                return (int) $segments[$idx + 1];
            }
        }

        return 0;
    }

    /**
     * Добавление комментария к запчасти (в таблицу чата или напрямую в spare_parts)
     */
    public function storeComment(Request $request, ...$params): JsonResponse
    {
        try {
            $text = trim((string) $request->input('comment', ''));
            if ($text === '') {
                return response()->json(['message' => 'Текст комментария не может быть пустым'], 422);
            }

            // Извлекаем partId из параметров маршрута или позиционных аргументов
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

            $part = \App\Models\SparePart::findOrFail($partId);

            $userId = \Illuminate\Support\Facades\Auth::id();
            $user = \Illuminate\Support\Facades\Auth::user();

            $hasLastName = \Illuminate\Support\Facades\Schema::hasColumn('users', 'last_name');
            $userPayload = [
                'id' => $user?->id ?? $userId,
                'name' => $user?->name ?? 'Пользователь',
                'last_name' => $hasLastName ? ($user?->last_name ?? '') : '',
            ];

            $createdCommentData = null;

            // 1. Проверяем, существует ли таблица spare_part_comments
            $hasCommentsTable = \Illuminate\Support\Facades\Schema::hasTable('spare_part_comments');

            if ($hasCommentsTable) {
                // Создаем запись через DB::table, чтобы не зависеть от $fillable модели
                $commentId = \Illuminate\Support\Facades\DB::table('spare_part_comments')->insertGetId([
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
                // Если таблицы spare_part_comments еще нет — создаем псевдо-комментарий для ответа
                $createdCommentData = [
                    'id' => time(),
                    'spare_part_id' => $part->id,
                    'user_id' => $userId,
                    'comment' => $text,
                    'created_at' => now()->toDateTimeString(),
                    'user' => $userPayload,
                ];
            }

            // 2. Обновляем текстовое поле comment в самой таблице spare_parts (для совместимости)
            if (\Illuminate\Support\Facades\Schema::hasColumn('spare_parts', 'comment')) {
                $part->comment = $text;
                $part->save();
            }

            // 3. Безопасное логирование в аудит (если метод существует)
            if (method_exists($this, 'logHistory')) {
                try {
                    $this->logHistory($part, 'updated', 'comment', null, $text);
                } catch (\Throwable $th) {
                    // Игнорируем ошибку аудита, чтобы не прерывать добавление комментария
                }
            }

            return response()->json([
                'message' => 'Комментарий успешно добавлен',
                'data' => $createdCommentData,
            ], 201);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Ошибка storeComment: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Ошибка при сохранении комментария: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Массовое увеличение цен ПЗН всех деталей на заданный процент
     */
    public function bulkIncreasePzn(Request $request, int $applicationId): JsonResponse
    {
        $request->validate([
            'percent' => 'required|numeric|min:0.01|max:500',
        ]);

        $percent = (float) $request->input('percent');
        $multiplier = 1 + ($percent / 100);

        $columns = \Illuminate\Support\Facades\Schema::getColumnListing('spare_parts');
        $appKey = in_array('application_id', $columns) ? 'application_id' : 'id_application';
        $skCol = in_array('price_ic', $columns) ? 'price_ic' : (in_array('price_sk', $columns) ? 'price_sk' : 'price_ic');

        // Выбираем все позиции кроме мелких деталей (они считаются от общей суммы)
        $parts = \App\Models\SparePart::where($appKey, $applicationId)
            ->where('is_small_parts', false)
            ->get();

        \Illuminate\Support\Facades\DB::transaction(function () use ($parts, $multiplier, $percent, $skCol) {
            foreach ($parts as $part) {
                $baseSk = (float) ($part->{$skCol} ?? 0);
                if ($baseSk > 0) {
                    $oldPzn = $part->price_pzn;
                    $newPzn = round($baseSk * $multiplier, 2);

                    $part->price_pzn = $newPzn;
                    $part->save();

                    // Логируем в историю
                    $this->logHistory($part, 'updated', 'price_pzn', (string) $oldPzn, (string) $newPzn, "Массовое увеличение на +{$percent}%");
                }
            }
        });

        // Пересчитываем мелкие детали от новых сумм
        $this->syncSmallPartsPrice($applicationId);

        // Возвращаем актуальный список деталей
        $updatedParts = \App\Models\SparePart::with('creator:id,name,last_name')
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

            // Сохраняем в application
            $app = \App\Models\Application::find($applicationId);
            if ($app) {
                $columnsApp = \Illuminate\Support\Facades\Schema::getColumnListing('applications');
                if (in_array('discount_percent', $columnsApp)) {
                    $app->discount_percent = $discount;
                }
                $app->save();
            }

            $columns = \Illuminate\Support\Facades\Schema::getColumnListing('spare_parts');
            $appKey = in_array('application_id', $columns) ? 'application_id' : 'id_application';
            $hasSmallCol = in_array('is_small_parts', $columns);

            // 1. Обновляем ВСЕ обычные детали: пересчитываем price_ic_half и пишем iznos
            $regularParts = \App\Models\SparePart::where($appKey, $applicationId)
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
                // Записываем скидку в столбец iznos каждой позиции:
                if (in_array('iznos', $columns)) {
                    $updateData['iznos'] = $discount;
                }
                if (in_array('price_ic_half', $columns)) {
                    $updateData['price_ic_half'] = $newHalf;
                }

                if (!empty($updateData)) {
                    \Illuminate\Support\Facades\DB::table('spare_parts')
                        ->where('id', $part->id)
                        ->update($updateData);
                }

                $sumIc += $base;
                $sumHalf += $newHalf;
            }

            // 2. Обновляем строку "Мелкие детали": тоже пишем iznos и 2% от суммы со скидкой
            $smallPart = \App\Models\SparePart::where($appKey, $applicationId)
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
                if (in_array('iznos', $columns)) {
                    $smallUpdate['iznos'] = $discount; // пишем iznos и для мелких деталей!
                }
                if (in_array('price_ic', $columns))
                    $smallUpdate['price_ic'] = $smallPriceIc;
                if (in_array('price_ic_full', $columns))
                    $smallUpdate['price_ic_full'] = $smallPriceIc;
                if (in_array('price_ic_half', $columns))
                    $smallUpdate['price_ic_half'] = $smallPriceHalf;

                \Illuminate\Support\Facades\DB::table('spare_parts')
                    ->where('id', $smallPart->id)
                    ->update($smallUpdate);
            }

            // Возвращаем свежие данные из базы
            $freshParts = \App\Models\SparePart::with('creator:id,name,last_name')
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
            \Illuminate\Support\Facades\Log::error('Ошибка применения скидки КАСКО: ' . $e->getMessage());
            return response()->json(['message' => 'Ошибка: ' . $e->getMessage()], 500);
        }
    }
    protected function resolveAuthorType(?\App\Models\User $user, \App\Models\Application $application): int
    {
        if (!$user) {
            return 0; // Автоматически
        }

        $userId = (int) $user->id;

        // 1. МРК дела
        if ($application->id_MRK && (int) $application->id_MRK === $userId) {
            return 1;
        }

        // 2. Наставник дела (проверяем id_mentor или mentor_id)
        $mentorId = (int) ($application->id_mentor ?? $application->mentor_id ?? 0);
        if ($mentorId > 0 && $mentorId === $userId) {
            return 2;
        }

        // 3. Запчастист (СПМ) дела
        if ($application->id_spm && (int) $application->id_spm === $userId) {
            return 3;
        }

        // 4. Сервис (СТО) дела
        // Проверяем: либо сервис дела привязан к пользователю, либо у пользователя роль СТО в филиале дела
        $branchId = (int) ($application->our_company ?? $application->our_company_id ?? 0);
        if (method_exists($user, 'isStoForBranch') && $user->isStoForBranch($branchId)) {
            return 4;
        }
        if (method_exists($user, 'getServiceId') && $application->id_service && (int) $application->id_service === (int) $user->getServiceId()) {
            return 4;
        }

        // 5. Любой другой пользователь (сисадмин, руководитель, чужой МРК)
        return 5;
    }

}
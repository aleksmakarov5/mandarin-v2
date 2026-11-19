<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\SparePart;
use App\Models\SparePartHistory;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SparePartController extends Controller
{
    /**
     * Список запчастей дела
     */
    public function index(Request $request, int $applicationId): JsonResponse
    {
        $app = Application::select('id', 'ub_type', 'loss_type', 'type_loss')->findOrFail($applicationId);
        $user = $request->user();

        // Проверяем роль пользователя: СТО (роль 7 / service) или сотрудник МРК/СПМ/Админ
        $isStoUser = $user && method_exists($user, 'isSto') ? $user->isSto() : false;
        if (!$isStoUser && $user) {
            $isStoUser = (int) ($user->user_group_id ?? 0) === 7;
        }

        $query = SparePart::with('creator:id,name,last_name')
            ->where('application_id', $applicationId)
            ->orderBy('is_small_parts', 'asc')
            ->orderBy('id', 'asc');

        // Для СТО можно отфильтровать закрытые поля на уровне SQL или отдать полностью
        $parts = $query->get();

        // Определяем тип убытка: КАСКО или ОСАГО
        $rawLoss = mb_strtolower((string) ($app->ub_type ?? $app->loss_type ?? $app->type_loss ?? ''));
        $isCasco = str_contains($rawLoss, 'каско') || str_contains($rawLoss, 'casco') || $rawLoss === '2';

        return response()->json([
            'data' => $parts,
            'is_casco' => $isCasco,
            'is_sto' => $isStoUser,
        ]);
    }

    /**
     * Быстрое создание новой строки
     */
    public function store(Request $request, int $applicationId): JsonResponse
    {
        $user = Auth::user();

        $part = SparePart::create([
            'application_id' => $applicationId,
            'name' => $request->input('name', 'Новая запчасть'),
            'part_number' => $request->input('part_number', ''),
            'count' => 1,
            'price_ic' => (float) $request->input('price_ic', 0),
            'price_pzn' => (float) $request->input('price_pzn', 0),
            'is_manual' => true,
            'created_by_user_id' => $user?->id,
            'status' => 'В поиске',
        ]);

        $this->logHistory($part, 'created', null, null, 'Создана позиция: ' . $part->name);

        $this->syncSmallPartsPrice($applicationId);

        return response()->json([
            'message' => 'Позиция добавлена',
            'data' => $part->load('creator:id,name,last_name')
        ], 201);
    }

    /**
     * Мгновенное обновление ячейки (при blur/contenteditable)
     */
    public function updateField(Request $request, int $applicationId, int $partId): JsonResponse
    {
        $part = SparePart::where('application_id', $applicationId)->findOrFail($partId);
        $field = $request->input('field');
        $newValue = $request->input('value');
        $oldValue = $part->{$field};

        if ($oldValue == $newValue) {
            return response()->json(['data' => $part]);
        }

        DB::transaction(function () use ($part, $field, $newValue, $oldValue, $applicationId) {
            // Если меняем статус на "Заказана" — автопроставляем дату заказа
            if ($field === 'status' && mb_strtolower($newValue) === 'заказана' && !$part->order_date) {
                $part->order_date = Carbon::now()->toDateString();
            }

            // Если обновили цену СК и цена ПЗН еще не была изменена вручную
            if ($field === 'price_ic' && (float) $part->price_pzn === 0.0) {
                $part->price_pzn = (float) $newValue;
            }

            $part->{$field} = $newValue;
            $part->save();

            // Логируем в историю
            $this->logHistory($part, 'updated', $field, (string) $oldValue, (string) $newValue);

            // Если изменилась дата поставки — проверяем максимальную дату для applications
            if ($field === 'delivery_date') {
                $this->syncMaxDeliveryDateToApplication($applicationId);
            }

            // Если изменились цены — пересчитываем мелкие детали
            if (in_array($field, ['price_ic', 'price_pzn'])) {
                $this->syncSmallPartsPrice($applicationId);
            }
        });

        return response()->json([
            'message' => 'Сохранено',
            'data' => $part->fresh('creator:id,name,last_name')
        ]);
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
    public function handleSmallParts(int $applicationId): JsonResponse
    {
        $existing = SparePart::where('application_id', $applicationId)
            ->where(function ($q) {
                $q->where('is_small_parts', true)
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%мелкие детали%']);
            })
            ->first();

        if ($existing) {
            // Если уже есть — возвращаем флаг, что можно удалить
            return response()->json([
                'exists' => true,
                'part_id' => $existing->id,
                'message' => 'Позиция "Мелкие детали" уже присутствует в смете.'
            ]);
        }

        // Создаем
        $totals = $this->calculateSumTotals($applicationId);
        $priceIc = round($totals['sum_ic'] * 0.02, 2);
        $pricePzn = round($totals['sum_pzn'] * 0.02, 2);

        $part = SparePart::create([
            'application_id' => $applicationId,
            'name' => 'МЕЛКИЕ ДЕТАЛИ',
            'part_number' => '2%',
            'count' => 1,
            'price_ic' => $priceIc,
            'price_pzn' => $pricePzn,
            'is_manual' => false,
            'is_small_parts' => true,
        ]);

        $this->logHistory($part, 'created', null, null, 'Добавлены Мелкие детали (2%)');

        return response()->json([
            'exists' => false,
            'action' => 'created',
            'data' => $part
        ]);
    }

    /**
     * Удаление строки с подтверждением
     */
    public function destroy(int $applicationId, int $partId): JsonResponse
    {
        $part = SparePart::where('application_id', $applicationId)->findOrFail($partId);
        $partName = $part->name;

        $this->logHistory($part, 'deleted', null, $partName, null);
        $part->delete();

        $this->syncSmallPartsPrice($applicationId);
        $this->syncMaxDeliveryDateToApplication($applicationId);

        return response()->json(['message' => 'Позиция удалена']);
    }

    /**
     * Получение истории изменений
     */
    public function history(int $applicationId): JsonResponse
    {
        $history = SparePartHistory::with('user:id,name,last_name')
            ->where('application_id', $applicationId)
            ->orderBy('id', 'desc')
            ->limit(150)
            ->get();

        return response()->json(['data' => $history]);
    }

    /**
     * Пересчет цены мелких деталей
     */
    protected function syncSmallPartsPrice(int $applicationId): void
    {
        $smallPart = SparePart::where('application_id', $applicationId)
            ->where('is_small_parts', true)
            ->first();

        if (!$smallPart)
            return;

        $totals = $this->calculateSumTotals($applicationId, $smallPart->id);
        $smallPart->price_ic = round($totals['sum_ic'] * 0.02, 2);
        $smallPart->price_pzn = round($totals['sum_pzn'] * 0.02, 2);
        $smallPart->save();
    }

    protected function calculateSumTotals(int $applicationId, ?int $excludeId = null): array
    {
        $query = SparePart::where('application_id', $applicationId)
            ->where('is_small_parts', false);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return [
            'sum_ic' => (float) $query->sum('price_ic'),
            'sum_pzn' => (float) $query->sum('price_pzn'),
        ];
    }

    /**
     * Сравнение и обновление максимальной даты поставки в таблице applications
     */
    protected function syncMaxDeliveryDateToApplication(int $applicationId): void
    {
        $maxDate = SparePart::where('application_id', $applicationId)
            ->whereNotNull('delivery_date')
            ->max('delivery_date');

        if ($maxDate) {
            $app = Application::find($applicationId);
            if ($app) {
                // Если даты не было или новая дата больше текущей
                if (!$app->sp_date || Carbon::parse($maxDate)->gt(Carbon::parse($app->sp_date))) {
                    $app->sp_date = $maxDate;
                    $app->save();
                }
            }
        }
    }

    protected function logHistory(SparePart $part, string $action, ?string $field, ?string $old, ?string $new): void
    {
        SparePartHistory::create([
            'spare_part_id' => $part->id,
            'application_id' => $part->application_id,
            'user_id' => Auth::id(),
            'action_type' => $action,
            'field_name' => $field,
            'old_value' => $old,
            'new_value' => $new,
        ]);
    }
}
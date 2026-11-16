<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\City;
use App\Models\InsuranceCompany;
use App\Models\OurCompany;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApplicationController extends Controller
{
    /**
     * Статусы по вкладкам реестра
     */
    protected array $tabs = [
        'requests' => [1, 2, 3, 4],
        'applications' => [8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 26],
        'acceptance' => [28],
        'archive' => [25],
        'trash' => [5, 6, 7, 27],
    ];

    /**
     * Основной реестр дел
     */
    public function index(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $tab = $request->input('tab', 'requests');

        $query = Application::with([
            'status',
            'insuranceCompany:id,name',
            'ourCompany:id,name',
            'city:id,name',
            'mrk:id,name,last_name,patronymic',
            'spm:id,name,last_name,patronymic',
        ]);

        // 1. Фильтр вкладки по статусам (requests, applications, acceptance, archive, trash)
        // ВАЖНО: если в этой вкладке пользователь выбрал конкретные статусы через мультиселект (status_ids),
        // используем их. Если НЕ выбрал ничего — берем дефолтные статусы этой вкладки (равно "выбрано всё").
        if ($request->filled('status_ids')) {
            $statusIds = is_array($request->status_ids)
                ? $request->status_ids
                : explode(',', $request->status_ids);
            $cleanStatusIds = array_filter(array_map('trim', $statusIds));
            if (!empty($cleanStatusIds)) {
                $query->whereIn('id_status', $cleanStatusIds);
            }
        } elseif (isset($this->tabs[$tab])) {
            $query->whereIn('id_status', $this->tabs[$tab]);
        }

        // 2. Изоляция по филиалам (для сотрудников филиалов, кроме суперадмина)
        if (!$user->isSystemAdmin()) {
            $availableBranches = method_exists($user, 'getAvailableBranchIds')
                ? $user->getAvailableBranchIds()
                : [];

            if (!empty($availableBranches)) {
                $query->whereIn('our_company', $availableBranches);
            }
        }

        // 3. МУЛЬТИФИЛЬТР ПО СОТРУДНИКАМ (МРК, запчастист, наставник)
        // Ничего не выбрано => условие не накладывается (выбраны все)
        // Если выбраны конкретные ID => ищем дела, где сотрудник указан как МРК, запчастист или наставник
        if ($request->filled('mrk_ids')) {
            $mrkIds = is_array($request->mrk_ids)
                ? $request->mrk_ids
                : explode(',', $request->mrk_ids);
            $cleanMrkIds = array_filter(array_map('trim', $mrkIds));

            if (!empty($cleanMrkIds)) {
                $query->where(function ($q) use ($cleanMrkIds) {
                    $q->whereIn('id_MRK', $cleanMrkIds)
                        ->orWhereIn('id_spm', $cleanMrkIds);

                    // Если в таблице есть колонка наставника (id_mentor или mentor_id)
                    if (\Illuminate\Support\Facades\Schema::hasColumn('applications', 'id_mentor')) {
                        $q->orWhereIn('id_mentor', $cleanMrkIds);
                    }
                    if (\Illuminate\Support\Facades\Schema::hasColumn('applications', 'mentor_id')) {
                        $q->orWhereIn('mentor_id', $cleanMrkIds);
                    }
                });
            }
        }

        // 4. МУЛЬТИФИЛЬТР ПО ГОРОДАМ
        // Ничего не выбрано => условие не накладывается (выбраны все)
        if ($request->filled('city_ids')) {
            $cityIds = is_array($request->city_ids)
                ? $request->city_ids
                : explode(',', $request->city_ids);
            $cleanCityIds = array_filter(array_map('trim', $cityIds));

            if (!empty($cleanCityIds)) {
                $query->whereIn('id_city', $cleanCityIds);
            }
        }

        // 5. МУЛЬТИФИЛЬТР ПО СТРАХОВЫМ КОМПАНИЯМ (СК)
        // Ничего не выбрано => условие не накладывается (выбраны все)
        if ($request->filled('insurance_ids')) {
            $insIds = is_array($request->insurance_ids)
                ? $request->insurance_ids
                : explode(',', $request->insurance_ids);
            $cleanInsIds = array_filter(array_map('trim', $insIds));

            if (!empty($cleanInsIds)) {
                $query->whereIn('id_insuranceCompany', $cleanInsIds);
            }
        }

        // 6. МУЛЬТИФИЛЬТР ПО СТОА / ФИЛИАЛАМ
        // Ничего не выбрано => условие не накладывается (выбраны все)
        if ($request->filled('branch_ids')) {
            $branchIds = is_array($request->branch_ids)
                ? $request->branch_ids
                : explode(',', $request->branch_ids);
            $cleanBranchIds = array_filter(array_map('trim', $branchIds));

            if (!empty($cleanBranchIds)) {
                $query->whereIn('our_company', $cleanBranchIds);
            }
        }

        // 7. МУЛЬТИФИЛЬТР ПО ТИПУ УБЫТКА (0 - ОСАГО, 1 - КАСКО)
        // Ничего не выбрано => условие не накладывается (выбраны все)
        if ($request->filled('ub_types')) {
            $ubTypes = is_array($request->ub_types)
                ? $request->ub_types
                : explode(',', $request->ub_types);
            $cleanUbTypes = array_filter($ubTypes, fn($v) => $v !== '' && $v !== null);

            if (!empty($cleanUbTypes)) {
                $query->whereIn('ub_type', $cleanUbTypes);
            }
        }

        // 8. ТЕКСТОВЫЕ ФИЛЬТРЫ ИЗ ВТОРОЙ СТРОКИ ШАПКИ
        if ($request->filled('id')) {
            $query->where('id', 'LIKE', '%' . trim($request->id) . '%');
        }
        if ($request->filled('ub_number')) {
            $ub = trim($request->ub_number);
            $query->where(function ($q) use ($ub) {
                $q->where('numberInsuranceCompanyApplication', 'LIKE', "%{$ub}%")
                    ->orWhere('ub_number', 'LIKE', "%{$ub}%");
            });
        }
        if ($request->filled('ClientName')) {
            $query->where('ClientName', 'LIKE', '%' . trim($request->ClientName) . '%');
        }
        if ($request->filled('ClientPhone')) {
            $query->where('ClientPhone', 'LIKE', '%' . trim($request->ClientPhone) . '%');
        }
        if ($request->filled('CarModel')) {
            $query->where('CarModel', 'LIKE', '%' . trim($request->CarModel) . '%');
        }
        if ($request->filled('gnz')) {
            $query->where('gnz', 'LIKE', '%' . trim($request->gnz) . '%');
        }
        if ($request->filled('vin')) {
            $query->where('vin', 'LIKE', '%' . trim($request->vin) . '%');
        }

        $perPage = (int) $request->input('per_page', 50);
        $result = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json($result);
    }

    /**
     * Справочники для фильтров реестра
     */
    public function filterOptions(Request $request): JsonResponse
    {
        // 1. Статусы
        $statuses = \App\Models\ApplicationStatus::select('id', 'name')
            ->orderBy('id', 'asc')
            ->get();

        // 2. Города
        $cities = \App\Models\City::select('id', 'name')
            ->orderBy('name', 'asc')
            ->get();

        // 3. Страховые компании
        $insuranceCompanies = \App\Models\InsuranceCompany::select('id', 'name')
            ->orderBy('name', 'asc')
            ->get();

        // 4. Филиалы / СТОА
        $branches = \App\Models\OurCompany::get()->map(function ($b) {
            return [
                'id' => $b->id,
                'name' => $b->name ?? $b->our_name ?? "Филиал #{$b->id}",
            ];
        });

        // 5. Сотрудники: ТОЛЬКО МРК, наставники и запчастисты
        $allowedRoleCodes = ['mrk', 'zap', 'spm', 'mentor', 'nastavnik'];
        $allowedGroupIds = [5, 6, 8]; // Стандартные ID групп МРК / Запчастист / Наставник

        $employees = \App\Models\User::where(function ($q) use ($allowedRoleCodes, $allowedGroupIds) {
            // Основная группа пользователя
            $q->whereIn('user_group_id', $allowedGroupIds)
                ->orWhereHas('group', fn($g) => $g->whereIn('code', $allowedRoleCodes));

            // Или роль в связке с филиалами (branchRoles)
            if (method_exists(\App\Models\User::class, 'branchRoles')) {
                $q->orWhereHas('branchRoles.role', fn($r) => $r->whereIn('code', $allowedRoleCodes)->orWhereIn('id', $allowedGroupIds));
            }
        })
            ->get()
            ->map(function ($u) {
                $fio = trim(($u->last_name ?? '') . ' ' . ($u->name ?? '') . ' ' . ($u->patronymic ?? ''));
                return [
                    'id' => $u->id,
                    'name' => $fio !== '' ? $fio : ($u->name ?? $u->email),
                    'last_name' => $u->last_name ?? $u->name,
                ];
            });

        return response()->json([
            'statuses' => $statuses,
            'cities' => $cities,
            'insurance_companies' => $insuranceCompanies,
            'branches' => $branches,
            'employees' => $employees,
        ]);
    }

    /**
     * Счетчики бейджей для меню
     */
    public function counts(Request $request): JsonResponse
    {
        $user = $request->user();
        $base = Application::query();

        if (!$user->isSystemAdmin() && method_exists($user, 'getAvailableBranchIds')) {
            $base->whereIn('our_company', $user->getAvailableBranchIds());
        }

        return response()->json([
            'requests' => (clone $base)->whereIn('id_status', $this->tabs['requests'])->count(),
            'applications' => (clone $base)->whereIn('id_status', $this->tabs['applications'])->count(),
            'acceptance' => (clone $base)->whereIn('id_status', $this->tabs['acceptance'])->count(),
            'archive' => (clone $base)->whereIn('id_status', $this->tabs['archive'])->count(),
            'trash' => (clone $base)->whereIn('id_status', $this->tabs['trash'])->count(),
        ]);
    }

    /**
     * Расчет основного таймера с индивидуальной точкой отсчета
     */
    protected function calculateCustomTimer(Application $app): array
    {
        $status = $app->status;
        if (!$status || empty($status->time)) {
            return ['is_overdue' => false, 'text' => ''];
        }

        $now = Carbon::now();
        $startDate = null;

        // Выбор точки отсчета по логике старого контроллера:
        switch ((int) $app->id_status) {
            case 1: // Создана автоматически
            case 2: // У администратора
                $startDate = $app->created_at;
                break;

            case 11: // Ожидается ответ
                $startDate = $app->ic_answer_date ?? $app->updated_at ?? $app->created_at;
                break;

            case 13: // Вызван
                $startDate = $app->view_date ?? $app->updated_at;
                break;

            case 15: // З/Ч заказаны
                $startDate = $app->sp_date ?? $app->updated_at;
                break;

            case 17: // В ремонте
            case 19:
                $startDate = $app->start_date ?? $app->updated_at;
                break;

            default:
                $startDate = $app->check_date ?? $app->updated_at ?? $app->created_at;
                break;
        }

        if (!$startDate) {
            return ['is_overdue' => false, 'text' => ''];
        }

        $start = Carbon::parse($startDate);
        $isDayUnit = (bool) ($status->day ?? $status->is_day ?? false);

        if ($isDayUnit) {
            $workingDays = $this->calculateWorkingDaysBetween($start, $now);
            $limitDays = (int) $status->time;

            if ($workingDays > $limitDays) {
                return ['is_overdue' => true, 'text' => 'просрочено'];
            }

            return ['is_overdue' => false, 'text' => "{$workingDays}дней"];
        }

        // Подсчет в часах
        $diffHours = $start->diffInHours($now);
        $limitHours = (int) $status->time;

        if ($diffHours > $limitHours) {
            return ['is_overdue' => true, 'text' => 'просрочено'];
        }

        return ['is_overdue' => false, 'text' => "{$diffHours}часов"];
    }

    /**
     * Расчет дополнительного блока «Согл/Опл»
     */
    protected function calculateSoglasOplTimer(Application $app): array
    {
        $title = '';
        $timeText = '';
        $isOverdue = false;

        if (!empty($app->check_date)) {
            $start = Carbon::parse($app->check_date);
            $hours = $start->diffInHours(Carbon::now());

            if ($app->id_status == 18 || $app->id_status == 20) {
                $title = 'Счет создан';
                $timeText = "{$hours}ч";
                $isOverdue = $hours > 48;
            } elseif ($app->id_status == 21) {
                $title = 'Согласование АСП';
                $timeText = "{$hours}часов";
                $isOverdue = $hours > 72;
            } elseif ($app->id_status == 22) {
                $title = 'Счет направлен в СК';
                $timeText = "{$hours}ч";
                $isOverdue = $hours > 120;
            }
        }

        return [
            'title' => $title,
            'time_text' => $timeText,
            'is_overdue' => $isOverdue,
        ];
    }

    /**
     * Расчет финансовых показателей дела
     */
    protected function calculateFinances(Application $app): array
    {
        // 1. Сумма СК
        $sumSk = (float) DB::table('works')
            ->where('application_id', $app->id)
            ->sum('price');

        // 2. Сумма запчастей
        $sumZap = (float) DB::table('spare_parts')
            ->where('application_id', $app->id)
            ->sum('price');

        // 3. Сумма ремонта = Работы + З/Ч
        $remontSum = $sumSk + $sumZap;

        // 4. Затраты на ремонт (себестоимость работ и деталей)
        $workCosts = (float) DB::table('works')
            ->where('application_id', $app->id)
            ->sum('cost_price');

        $partCosts = (float) DB::table('spare_parts')
            ->where('application_id', $app->id)
            ->sum('cost_price');

        $totalCosts = $workCosts + $partCosts;

        // 5. Оплаты
        $icPay = (float) ($app->ic_pay ?? 0);
        $stoPay = (float) ($app->sto_pay ?? 0);

        // 6. Остаток и процент выполнения
        $remains = $remontSum - $totalCosts;
        $remainsPercent = $remontSum > 0 ? round(($remains / $remontSum) * 100) : 0;

        return [
            'sum_sk' => $sumSk,
            'sum_zap' => $sumZap,
            'remont_sum' => $remontSum,
            'total_costs' => $totalCosts,
            'ic_pay' => $icPay,
            'sto_pay' => $stoPay,
            'remains' => $remains,
            'remains_percent' => $remainsPercent,
        ];
    }

    /**
     * Подсчет рабочих дней (исключая субботы, воскресенья и официальные праздники РФ)
     */
    protected function calculateWorkingDaysBetween(Carbon $start, Carbon $end): int
    {
        if ($start->gt($end)) {
            return 0;
        }

        // Официальные праздничные дни РФ (формат: мм-дд)
        $holidays = [
            '01-01',
            '01-02',
            '01-03',
            '01-04',
            '01-05',
            '01-06',
            '01-07',
            '01-08',
            '02-23',
            '03-08',
            '05-01',
            '05-09',
            '06-12',
            '11-04',
        ];

        $workDays = 0;
        $current = $start->copy()->startOfDay();
        $target = $end->copy()->startOfDay();

        while ($current->lte($target)) {
            if (!$current->isWeekend() && !in_array($current->format('m-d'), $holidays)) {
                $workDays++;
            }
            $current->addDay();
        }

        return max(0, $workDays - 1);
    }
}
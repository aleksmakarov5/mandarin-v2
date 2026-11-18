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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Services\ApplicationTimerService;

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
     * Получение реестра дел с фильтрацией и точным расчетом таймеров
     */
    public function index(Request $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $user */
            $user = $request->user();
            $tab = $request->input('tab', 'requests');

            // Безопасное формирование связей
            $withRelations = [];
            $appInstance = new Application();

            if (method_exists($appInstance, 'status'))
                $withRelations[] = 'status';
            if (method_exists($appInstance, 'financeStatus'))
                $withRelations[] = 'financeStatus';
            if (method_exists($appInstance, 'finStatus'))
                $withRelations[] = 'finStatus';
            if (method_exists($appInstance, 'insuranceCompany'))
                $withRelations[] = 'insuranceCompany';
            if (method_exists($appInstance, 'ourCompany'))
                $withRelations[] = 'ourCompany';
            if (method_exists($appInstance, 'city'))
                $withRelations[] = 'city';
            if (method_exists($appInstance, 'mrk'))
                $withRelations[] = 'mrk';
            if (method_exists($appInstance, 'spm'))
                $withRelations[] = 'spm';
            if (method_exists($appInstance, 'lawyer'))
                $withRelations[] = 'lawyer';

            $query = Application::query()->with($withRelations);

            // 1. Фильтр вкладки по статусам
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

            // 2. Изоляция по филиалам
            if ($user && method_exists($user, 'isSystemAdmin') && !$user->isSystemAdmin()) {
                $availableBranches = method_exists($user, 'getAvailableBranchIds')
                    ? $user->getAvailableBranchIds()
                    : [];

                if (!empty($availableBranches)) {
                    $query->whereIn('our_company', $availableBranches);
                }
            }

            // 3. Фильтр по сотрудникам
            if ($request->filled('mrk_ids')) {
                $mrkIds = is_array($request->mrk_ids)
                    ? $request->mrk_ids
                    : explode(',', $request->mrk_ids);
                $cleanMrkIds = array_filter(array_map('trim', $mrkIds));

                if (!empty($cleanMrkIds)) {
                    $query->where(function ($q) use ($cleanMrkIds) {
                        $q->whereIn('id_MRK', $cleanMrkIds)
                            ->orWhereIn('id_spm', $cleanMrkIds);

                        if (Schema::hasColumn('applications', 'id_mentor')) {
                            $q->orWhereIn('id_mentor', $cleanMrkIds);
                        }
                    });
                }
            }

            // 4. Фильтр по городам
            if ($request->filled('city_ids')) {
                $cityIds = is_array($request->city_ids)
                    ? $request->city_ids
                    : explode(',', $request->city_ids);
                $cleanCityIds = array_filter(array_map('trim', $cityIds));
                if (!empty($cleanCityIds)) {
                    $query->whereIn('id_city', $cleanCityIds);
                }
            }

            // 5. Фильтр по СК
            if ($request->filled('insurance_ids')) {
                $insIds = is_array($request->insurance_ids)
                    ? $request->insurance_ids
                    : explode(',', $request->insurance_ids);
                $cleanInsIds = array_filter(array_map('trim', $insIds));
                if (!empty($cleanInsIds)) {
                    $query->whereIn('id_insuranceCompany', $cleanInsIds);
                }
            }

            // 6. Фильтр по филиалам
            if ($request->filled('branch_ids')) {
                $branchIds = is_array($request->branch_ids)
                    ? $request->branch_ids
                    : explode(',', $request->branch_ids);
                $cleanBranchIds = array_filter(array_map('trim', $branchIds));
                if (!empty($cleanBranchIds)) {
                    $query->whereIn('our_company', $cleanBranchIds);
                }
            }

            // 7. Фильтр по типу убытка
            if ($request->filled('ub_types')) {
                $ubTypes = is_array($request->ub_types)
                    ? $request->ub_types
                    : explode(',', $request->ub_types);
                $cleanUbTypes = array_filter($ubTypes, fn($v) => $v !== '' && $v !== null);
                if (!empty($cleanUbTypes)) {
                    $query->whereIn('ub_type', $cleanUbTypes);
                }
            }

            // 8. Текстовые поисковые фильтры
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
            if ($request->filled('sto_name')) {
                $query->where('service_name', 'LIKE', '%' . trim($request->sto_name) . '%');
            }

            // 9. Общий поиск
            if ($request->filled('search')) {
                $s = trim($request->search);
                $query->where(function ($q) use ($s) {
                    $q->where('id', 'LIKE', "%{$s}%")
                        ->orWhere('numberInsuranceCompanyApplication', 'LIKE', "%{$s}%")
                        ->orWhere('ub_number', 'LIKE', "%{$s}%")
                        ->orWhere('CarModel', 'LIKE', "%{$s}%")
                        ->orWhere('gnz', 'LIKE', "%{$s}%")
                        ->orWhere('vin', 'LIKE', "%{$s}%")
                        ->orWhere('ClientName', 'LIKE', "%{$s}%")
                        ->orWhere('ClientPhone', 'LIKE', "%{$s}%");
                });
            }

            $perPage = (int) $request->input('per_page', 50);
            $paginator = $query->orderBy('id', 'desc')->paginate($perPage);

            // Подгружаем справочники юристов
            $lawyersMap = [];
            if (Schema::hasTable('lawyers')) {
                $lawyersMap = \Illuminate\Support\Facades\DB::table('lawyers')
                    ->select('id', 'name', 'phone', Schema::hasColumn('lawyers', 'is_cooperating') ? 'is_cooperating' : \Illuminate\Support\Facades\DB::raw('1 as is_cooperating'))
                    ->get()
                    ->keyBy('id')
                    ->toArray();
            }

            // Подгружаем справочник финансовых статусов со всеми возможными полями
            $finStatusesMap = [];
            if (Schema::hasTable('finance_statuses')) {
                $finColumns = Schema::getColumnListing('finance_statuses');
                $selectCols = ['id', 'name'];

                if (in_array('fin_time', $finColumns))
                    $selectCols[] = 'fin_time';
                if (in_array('time', $finColumns))
                    $selectCols[] = 'time';
                if (in_array('fin_day', $finColumns))
                    $selectCols[] = 'fin_day';
                if (in_array('is_day', $finColumns))
                    $selectCols[] = 'is_day';
                if (in_array('day', $finColumns))
                    $selectCols[] = 'day';

                $finStatusesMap = \Illuminate\Support\Facades\DB::table('finance_statuses')
                    ->select($selectCols)
                    ->get()
                    ->keyBy('id')
                    ->toArray();
            }

            // 10. Сервис расчета таймеров
            $timerService = null;
            if (class_exists(\App\Services\ApplicationTimerService::class)) {
                $timerService = app(\App\Services\ApplicationTimerService::class);
            }

            $paginator->getCollection()->transform(function ($app) use ($timerService, $tab, $lawyersMap, $finStatusesMap) {
                // Юрист: если связь пуста, но есть id_lawyer
                $lawyerId = (int) ($app->id_lawyer ?? $app->lawyer_id ?? 0);
                if (!$app->relationLoaded('lawyer') || !$app->lawyer) {
                    if ($lawyerId > 0 && isset($lawyersMap[$lawyerId])) {
                        $app->setRelation('lawyer', (object) $lawyersMap[$lawyerId]);
                        $app->lawyer = (object) $lawyersMap[$lawyerId];
                    }
                }

                // Фин. статус: привязка из справочника
                $finId = (int) ($app->finance_status_id ?? $app->id_finance_status ?? $app->finance_statuses_id ?? 0);
                if ((!$app->relationLoaded('financeStatus') || !$app->financeStatus) && $finId > 0 && isset($finStatusesMap[$finId])) {
                    $app->setRelation('financeStatus', (object) $finStatusesMap[$finId]);
                    $app->financeStatus = (object) $finStatusesMap[$finId];
                }

                // Нормализация параметров времени для фин. статуса
                $finObj = $app->financeStatus ?? $app->finStatus ?? null;
                $finTime = 0;
                $finDay = 0;

                if ($finObj) {
                    $finTime = (int) ($finObj->fin_time ?? $finObj->time ?? 0);
                    $finDay = (int) ($finObj->fin_day ?? $finObj->is_day ?? $finObj->day ?? 0);
                }

                $app->fin_time = $finTime;
                $app->fin_day = $finDay;
                $app->finance_statuses_id = $finId;

                // Основной статус: нормализация времени
                $app->time = (int) ($app->status->time ?? $app->time ?? 0);
                $app->day = (int) ($app->status->day ?? $app->status->is_day ?? $app->day ?? 0);

                try {
                    if ($timerService) {
                        $timers = $timerService->calculate($app);

                        $app->app_timer = $timers['app_timer'] ?? null;
                        $app->finance_timer = $timers['finance_timer'] ?? null;

                        $app->max_hour = $timers['app_timer']['max_hour'] ?? 0;
                        $app->count_hour = $timers['app_timer']['count_hour'] ?? 0;
                        $app->is_overdue = $timers['app_timer']['is_overdue'] ?? false;
                        $app->is_day = $timers['app_timer']['is_day'] ?? false;
                        $app->time_status_text = $timers['app_timer']['display_text'] ?? '';

                        $app->max_hour_f = $timers['finance_timer']['max_hour_f'] ?? 0;
                        $app->count_hour_f = $timers['finance_timer']['count_hour_f'] ?? 0;
                    } else {
                        $app->app_timer = null;
                        $app->finance_timer = null;
                        $app->is_overdue = false;
                        $app->is_day = false;
                        $app->time_status_text = '';
                    }
                } catch (\Throwable $te) {
                    $app->app_timer = null;
                    $app->finance_timer = null;
                    $app->is_overdue = false;
                    $app->is_day = false;
                    $app->time_status_text = '';
                }

                // Название финансового статуса для фронтенда
                $app->finance_status_name = $finObj->name ?? $app->soglas_title ?? '';

                // Финансы
                $sumSk = (float) ($app->sum_sk ?? 0);
                $sumZap = (float) ($app->sum_zap ?? 0);
                $remontSum = (float) ($app->remont_sum ?? 0);
                $totalCosts = $sumZap + $remontSum;
                $remains = max(0, $sumSk - $totalCosts);

                $app->sum_sk = $sumSk;
                $app->sum_zap = $sumZap;
                $app->remont_sum = $remontSum;
                $app->total_costs = $totalCosts;
                $app->ic_pay = (float) ($app->ic_pay ?? 0);
                $app->sto_pay = (float) ($app->sto_pay ?? 0);
                $app->remains = $remains;
                $app->remains_percent = $sumSk > 0 ? (int) round(($remains / $sumSk) * 100) : 0;

                return $app;
            });

            return response()->json($paginator);
        } catch (\Throwable $e) {
            Log::error('Ошибка в ApplicationController::index: ' . $e->getMessage(), [
                'file' => $e->getFile() . ':' . $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => true,
                'message' => $e->getMessage(),
                'location' => basename($e->getFile()) . ':' . $e->getLine(),
            ], 500);
        }
    }

    /**
     * Безопасный расчет финансовых сумм для одного дела
     */
    protected function calculateFinances($app): array
    {
        $sumSk = (float) ($app->sum_sk ?? 0);
        $sumZap = (float) ($app->sum_zap ?? 0);
        $remontSum = (float) ($app->remont_sum ?? 0);
        $icPay = (float) ($app->ic_pay ?? 0);
        $stoPay = (float) ($app->sto_pay ?? 0);

        $totalCosts = $sumZap + $remontSum;
        $remains = max(0, $sumSk - $totalCosts);
        $remainsPercent = $sumSk > 0 ? (int) round(($remains / $sumSk) * 100) : 0;

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
     * Справочники для фильтров реестра
     */
    public function filterOptions(Request $request): JsonResponse
    {
        // 1. Статусы
        $statusTable = \Illuminate\Support\Facades\Schema::hasTable('application_statuses')
            ? 'application_statuses'
            : 'statuses';

        $cols = \Illuminate\Support\Facades\Schema::getColumnListing($statusTable);

        $statuses = \Illuminate\Support\Facades\DB::table($statusTable)
            ->orderBy('id')
            ->get()
            ->map(function ($st) use ($cols) {
                // Извлекаем цвет фона из любой возможной колонки
                $bg = $st->bg_color ?? $st->color ?? $st->background_color ?? $st->bg ?? null;
                // Извлекаем цвет текста
                $text = $st->text_color ?? $st->font_color ?? null;

                // Если hex без решётки (например, "e2e8f0"), добавляем '#'
                if ($bg && !str_starts_with($bg, '#') && !str_starts_with($bg, 'rgb')) {
                    $bg = '#' . $bg;
                }
                if ($text && !str_starts_with($text, '#') && !str_starts_with($text, 'rgb')) {
                    $text = '#' . $text;
                }

                // Дефолтный цвет, если в БД не задан
                if (!$bg) {
                    $palette = [
                        1 => ['#e2e8f0', '#334155'], // Новый / Черновик
                        2 => ['#dbeafe', '#1e40af'], // В работе
                        3 => ['#fef3c7', '#92400e'], // Согласование
                        4 => ['#dcfce7', '#166534'], // Завершено
                        5 => ['#fee2e2', '#991b1b'], // Отказ / Архив
                    ];
                    $bg = $palette[$st->id][0] ?? '#f1f5f9';
                    $text = $palette[$st->id][1] ?? '#0f172a';
                }

                // Если цвет текста не указан, автоматически вычисляем контрастный (белый или темный)
                if (!$text && $bg) {
                    $text = '#0f172a'; // темный по умолчанию
                }

                return [
                    'id' => (int) $st->id,
                    'name' => $st->name ?? $st->title,
                    'bg_color' => $bg,
                    'text_color' => $text ?: '#ffffff',
                ];
            });

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
     * Безопасный расчет финансовых показателей дела
     */


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
    /**
     * Получение данных конкретного дела
     */
    public function show(int $id): JsonResponse
    {
        try {
            $app = Application::with([
                'status',
                'insuranceCompany:id,name',
                'ourCompany:id,name',
                'city:id,name',
                'mrk:id,name',
                'spm:id,name',
                'mentor:id,name',
                'lawyer:id,name,phone,email',
            ])->findOrFail($id);

            // Безопасный расчет финансов
            $finances = $this->calculateFinances($app);

            // Преобразуем модель в массив и объединяем с финансами
            $data = array_merge($app->attributesToArray(), [
                'status' => $app->status,
                'insurance_company' => $app->insuranceCompany,
                'our_company_rel' => $app->ourCompany,
                'city' => $app->city,
                'mrk' => $app->mrk,
                'spm' => $app->spm,
            ], $finances);

            return response()->json([
                'data' => $data,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => "Дело #{$id} не найдено"], 404);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Ошибка в ApplicationController::show({$id}): " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Ошибка загрузки карточки дела: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Обновление данных дела
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $application = Application::findOrFail($id);
        $input = $request->all();

        /*
         * Пустые значения HTML-полей преобразуем в NULL.
         * Важно: число 0 и строка "0" здесь не затрагиваются.
         */
        foreach ($input as $key => $value) {
            if ($value === '') {
                $input[$key] = null;
            }
        }

        /*
         * gender в таблице applications — tinyint(1):
         * 1 — мужской, 0 — женский, NULL — не указан.
         */
        if (
            array_key_exists('gender', $input)
            && $input['gender'] !== null
        ) {
            if (is_bool($input['gender'])) {
                $input['gender'] = $input['gender'] ? 1 : 0;
            } else {
                $gender = mb_strtolower(trim((string) $input['gender']));

                $input['gender'] = match ($gender) {
                    '1', 'м', 'm', 'male' => 1,
                    '0', 'ж', 'f', 'female' => 0,
                    default => $input['gender'],
                };
            }
        }

        /*
         * Приводим российские телефоны к единому формату.
         * Неполный номер останется неполным и будет отклонён валидацией.
         */
        foreach (['ClientPhone', 'pred_phone'] as $field) {
            if (
                !array_key_exists($field, $input)
                || $input[$field] === null
            ) {
                continue;
            }

            $digits = preg_replace(
                '/\D+/',
                '',
                (string) $input[$field]
            ) ?? '';

            if (strlen($digits) === 10) {
                $digits = '7' . $digits;
            }

            if (strlen($digits) === 11 && $digits[0] === '8') {
                $digits = '7' . substr($digits, 1);
            }

            if (strlen($digits) === 11 && $digits[0] === '7') {
                $input[$field] = sprintf(
                    '+7 (%s) %s-%s-%s',
                    substr($digits, 1, 3),
                    substr($digits, 4, 3),
                    substr($digits, 7, 2),
                    substr($digits, 9, 2)
                );
            } else {
                // Оставляем значение для понятной ошибки regex-валидации.
                $input[$field] = $digits;
            }
        }

        $dateFields = [
            'DTPdate',
            'ClientPasport_date',
            'birthday',
            'napr_date',
            'sp_date',
            'start_date',
            'end_date',
            'ic_pay_date',
            'sto_pay_date',
            'zp_date',
            'finish_pay_date',
        ];

        $dateTimeFields = [
            'view_date',
            'check_date',
        ];

        /*
         * sometimes обязателен: при частичном обновлении Laravel не должен
         * требовать поля, которые пользователь сейчас не изменял.
         */
        $rules = [
            // Автомобиль
            'CarModel' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],
            'gnz' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
            'vin' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
            'mnf_year' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
            'vehicle_passport' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
            'Color' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
            'mileage' => [
                'sometimes',
                'nullable',
                'integer',
                'min:0',
            ],

            // Клиент
            'ClientName' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
            'ClientPhone' => [
                'sometimes',
                'nullable',
                'string',
                'max:32',
                'regex:/^\+7 \(\d{3}\) \d{3}-\d{2}-\d{2}$/',
            ],
            'gender' => [
                'sometimes',
                'nullable',
                'integer',
                'in:0,1',
            ],
            'birthday' => [
                'sometimes',
                'nullable',
                'date',
            ],
            'pred' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
            'pred_phone' => [
                'sometimes',
                'nullable',
                'string',
                'max:32',
                'regex:/^\+7 \(\d{3}\) \d{3}-\d{2}-\d{2}$/',
            ],

            // Паспорт клиента
            'ClientPasport' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
            'ClientPasport_number' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
            'ClientPasport_struct' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            // Страховая компания и убыток
            'id_insuranceCompany' => [
                'sometimes',
                'required',
                'integer',
                'min:1',
            ],
            'numberInsuranceCompanyApplication' => [
                'sometimes',
                'required',
                'string',
                'max:40',
            ],
            'ub_number' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
            'ub_type' => [
                'sometimes',
                'nullable',
                'integer',
                'in:0,1',
            ],
            'dogovor' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
            'email_company' => [
                'sometimes',
                'nullable',
                'email',
                'max:255',
            ],

            // Филиал и ответственные
            'our_company' => [
                'sometimes',
                'required',
                'integer',
                'min:1',
            ],
            'id_city' => [
                'sometimes',
                'required',
                'integer',
                'min:0',
            ],
            'id_service' => [
                'sometimes',
                'nullable',
                'integer',
                'min:1',
            ],
            'id_status' => [
                'sometimes',
                'nullable',
                'integer',
                'min:1',
            ],
            'id_MRK' => [
                'sometimes',
                'nullable',
                'integer',
                'min:1',
            ],
            'id_spm' => [
                'sometimes',
                'nullable',
                'integer',
                'min:1',
            ],
            'id_lawyer' => [
                'sometimes',
                'nullable',
                'integer',
                'min:1',
            ],

            // Комментарии
            'comment_true' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
            'comment_false' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
        ];

        foreach ($dateFields as $field) {
            // birthday уже присутствует в основных правилах
            if (!isset($rules[$field])) {
                $rules[$field] = [
                    'sometimes',
                    'nullable',
                    'date',
                ];
            }
        }

        foreach ($dateTimeFields as $field) {
            $rules[$field] = [
                'sometimes',
                'nullable',
                'date',
            ];
        }

        /*
         * В старой версии таблицы колонки id_mentor могло не быть.
         * Если её нет, поле просто игнорируется и не вызывает SQL-ошибку.
         */
        $hasMentorColumn = Schema::hasColumn(
            'applications',
            'id_mentor'
        );

        if ($hasMentorColumn) {
            $rules['id_mentor'] = [
                'sometimes',
                'nullable',
                'integer',
                'min:1',
            ];
        }

        $validated = validator($input, $rules, [
            'ClientPhone.regex' =>
                'Введите телефон клиента полностью: +7 (999) 123-45-67.',
            'pred_phone.regex' =>
                'Введите телефон представителя полностью: +7 (999) 123-45-67.',
            'gender.in' =>
                'Пол должен иметь значение 1, 0 или быть не указан.',
        ])->validate();

        /*
         * Приводим даты к форматам соответствующих колонок MySQL.
         */
        foreach ($dateFields as $field) {
            if (array_key_exists($field, $validated)) {
                $validated[$field] = $validated[$field] !== null
                    ? Carbon::parse($validated[$field])->format('Y-m-d')
                    : null;
            }
        }

        foreach ($dateTimeFields as $field) {
            if (array_key_exists($field, $validated)) {
                $validated[$field] = $validated[$field] !== null
                    ? Carbon::parse($validated[$field])
                        ->format('Y-m-d H:i:s')
                    : null;
            }
        }

        /*
         * Если изменений нет, не выполняем лишний UPDATE.
         */
        if (empty($validated)) {
            return response()->json([
                'message' => 'Изменений нет',
                'data' => $application,
            ]);
        }

        try {
            // Сохраняются только провалидированные и реально переданные поля.
            $application->fill($validated);
            $application->save();
        } catch (\Throwable $e) {
            Log::error("Ошибка обновления дела #{$id}", [
                'fields' => array_keys($validated),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' =>
                    'Не удалось сохранить дело. Подробности записаны в laravel.log.',
                'debug' => config('app.debug')
                    ? $e->getMessage()
                    : null,
            ], 500);
        }

        $relations = [
            'status',
            'insuranceCompany',
            'ourCompany',
            'city',
            'mrk',
            'spm',
            'lawyer',
        ];

        if ($hasMentorColumn) {
            $relations[] = 'mentor';
        }

        return response()->json([
            'message' => 'Дело сохранено',
            'data' => $application->fresh($relations),
        ]);
    }

    private function normalizeApplicationPhone(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        if (strlen($digits) === 10) {
            $digits = '7' . $digits;
        }

        if (strlen($digits) === 11 && $digits[0] === '8') {
            $digits = '7' . substr($digits, 1);
        }

        if (strlen($digits) === 11 && $digits[0] === '7') {
            return sprintf(
                '+7 (%s) %s-%s-%s',
                substr($digits, 1, 3),
                substr($digits, 4, 3),
                substr($digits, 7, 2),
                substr($digits, 9, 2)
            );
        }

        // Старые нестандартные номера не затираем.
        return $phone;
    }
    /**
     * Получить сотрудников филиала по ролям:
     * 5 - МРК, 6 - Наставник, 9 - Запчастист
     */
    /**
     * Получить сотрудников филиала с полным ФИО
     */
    public function getBranchEmployees(Request $request): JsonResponse
    {
        $branchId = $request->query('branch_id') ? (int) $request->query('branch_id') : null;

        // Определяем доступные колонки ФИО в таблице users
        $userCols = \Illuminate\Support\Facades\Schema::getColumnListing('users');
        $hasLastName = in_array('last_name', $userCols);
        $hasPatronymic = in_array('patronymic', $userCols) || in_array('middle_name', $userCols);
        $patronymicCol = in_array('patronymic', $userCols) ? 'patronymic' : 'middle_name';

        $select = [
            'users.id',
            'users.name',
            'our_company_user_role.user_group_id',
            'our_company_user_role.our_company_id',
        ];

        if ($hasLastName) {
            $select[] = 'users.last_name';
        }
        if ($hasPatronymic) {
            $select[] = "users.{$patronymicCol} as patronymic";
        }

        $allBranchRecords = \Illuminate\Support\Facades\DB::table('our_company_user_role')
            ->join('users', 'users.id', '=', 'our_company_user_role.user_id')
            ->select($select)
            ->get();

        $branchRecords = $branchId
            ? $allBranchRecords->where('our_company_id', $branchId)
            : $allBranchRecords;

        // Функция форматирования полного ФИО
        $formatFio = function ($user) use ($hasLastName, $hasPatronymic) {
            $parts = [];
            if ($hasLastName && !empty($user->last_name)) {
                $parts[] = trim($user->last_name);
            }
            if (!empty($user->name)) {
                $parts[] = trim($user->name);
            }
            if ($hasPatronymic && !empty($user->patronymic)) {
                $parts[] = trim($user->patronymic);
            }

            $fio = implode(' ', $parts);
            return !empty($fio) ? $fio : ($user->name ?? "Сотрудник #{$user->id}");
        };

        // Хелпер для получения пользователей конкретной роли
        $getUsersByRole = function (int $roleId) use ($branchRecords, $allBranchRecords, $formatFio) {
            $list = $branchRecords->where('user_group_id', $roleId);
            if ($list->isEmpty()) {
                $list = $allBranchRecords->where('user_group_id', $roleId);
            }

            return $list->map(fn($u) => [
                'id' => (int) $u->id,
                'name' => $formatFio($u),
            ])->unique('id')->sortBy('name')->values();
        };

        $mrk = $getUsersByRole(5);
        $mentors = $getUsersByRole(6);
        $spm = $getUsersByRole(9);

        // Если для роли 9 пусто, отдаем пользователей с полным ФИО
        if ($spm->isEmpty()) {
            $query = \App\Models\User::query();
            $spm = $query->get()->map(fn($u) => [
                'id' => (int) $u->id,
                'name' => trim(($u->last_name ?? '') . ' ' . $u->name . ' ' . ($u->patronymic ?? $u->middle_name ?? '')),
            ])->sortBy('name')->values();
        }

        return response()->json([
            'mrk' => $mrk,
            'mentors' => $mentors,
            'spm' => $spm,
        ]);
    }
}
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ApplicationStatus;
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
     * Список дел для реестра с пагинацией и фильтрами
     */
    public function index(Request $request): JsonResponse
    {
        try {
            /** @var \App\Models\User|null $user */
            $user = $request->user();
            $tab = $request->input('tab', 'requests');

            // Безопасно формируем список связей
            $appInstance = new Application();
            $withRelations = [];

            if (method_exists($appInstance, 'status'))
                $withRelations[] = 'status';
            if (method_exists($appInstance, 'financeStatus'))
                $withRelations[] = 'financeStatus';
            if (method_exists($appInstance, 'finStatus'))
                $withRelations[] = 'finStatus';
            if (method_exists($appInstance, 'insuranceCompany'))
                $withRelations[] = 'insuranceCompany:id,name';
            if (method_exists($appInstance, 'ourCompany'))
                $withRelations[] = 'ourCompany:id,name';
            if (method_exists($appInstance, 'city'))
                $withRelations[] = 'city:id,name';
            if (method_exists($appInstance, 'mrk'))
                $withRelations[] = 'mrk:id,name,last_name,patronymic';
            if (method_exists($appInstance, 'spm'))
                $withRelations[] = 'spm:id,name,last_name,patronymic';
            if (method_exists($appInstance, 'mentor') && Schema::hasColumn('applications', 'id_mentor')) {
                $withRelations[] = 'mentor:id,name';
            }
            if (method_exists($appInstance, 'lawyer') && Schema::hasTable('lawyers')) {
                $withRelations[] = 'lawyer';
            }

            // Связь с сервисом подгружаем без глобальных скоупов
            if (method_exists($appInstance, 'service')) {
                $withRelations['service'] = fn($q) => $q->withoutGlobalScopes();
            }

            $query = Application::query()->with($withRelations);

            // 1. Фильтр вкладки по статусам
            if ($request->filled('status_ids')) {
                $statusIds = is_array($request->status_ids)
                    ? $request->status_ids
                    : explode(',', (string) $request->status_ids);
                $cleanStatusIds = array_filter(array_map('trim', $statusIds));
                if (!empty($cleanStatusIds)) {
                    $query->whereIn('id_status', $cleanStatusIds);
                }
            } elseif (isset($this->tabs[$tab])) {
                $query->whereIn('id_status', $this->tabs[$tab]);
            }

            // 1.1. Фильтр по финансовым статусам
            if ($request->filled('finance_status_ids')) {
                $finIds = is_array($request->finance_status_ids)
                    ? $request->finance_status_ids
                    : explode(',', (string) $request->finance_status_ids);
                $cleanFinIds = array_filter(array_map('trim', $finIds));
                if (!empty($cleanFinIds)) {
                    $finCol = Schema::hasColumn('applications', 'finance_status_id')
                        ? 'finance_status_id'
                        : (Schema::hasColumn('applications', 'id_finance_status') ? 'id_finance_status' : 'finance_statuses_id');

                    $query->whereIn($finCol, $cleanFinIds);
                }
            }

            // 2. Изоляция по филиалам и правам
            $stoUserLogged = false;
            $currentUserServiceId = null;

            if ($user && method_exists($user, 'isSystemAdmin') && !$user->isSystemAdmin()) {
                if (method_exists($user, 'isSto') && $user->isSto()) {
                    $stoUserLogged = true;
                    $currentUserServiceId = method_exists($user, 'getServiceId') ? $user->getServiceId() : null;

                    if ($currentUserServiceId) {
                        $serviceCol = Schema::hasColumn('applications', 'id_service') ? 'id_service' : 'service_id';
                        $query->where($serviceCol, $currentUserServiceId);
                    } else {
                        return response()->json([
                            'data' => [],
                            'total' => 0,
                            'current_page' => 1,
                            'last_page' => 1,
                        ]);
                    }
                } else {
                    $availableBranches = method_exists($user, 'getAvailableBranchIds')
                        ? $user->getAvailableBranchIds()
                        : [];

                    if (!empty($availableBranches)) {
                        $query->whereIn('our_company', $availableBranches);
                    }

                    if (method_exists($user, 'cities')) {
                        $availableCityIds = $user->cities()->pluck('cities.id')->toArray();
                        if (!empty($availableCityIds)) {
                            $query->whereIn('id_city', $availableCityIds);
                        }
                    }
                }
            }

            // 3. Фильтр по сотрудникам
            if ($request->filled('mrk_ids')) {
                $mrkIds = is_array($request->mrk_ids)
                    ? $request->mrk_ids
                    : explode(',', (string) $request->mrk_ids);
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
                    : explode(',', (string) $request->city_ids);
                $cleanCityIds = array_filter(array_map('trim', $cityIds));
                if (!empty($cleanCityIds)) {
                    $query->whereIn('id_city', $cleanCityIds);
                }
            }

            // 5. Фильтр по СК
            if ($request->filled('insurance_ids')) {
                $insIds = is_array($request->insurance_ids)
                    ? $request->insurance_ids
                    : explode(',', (string) $request->insurance_ids);
                $cleanInsIds = array_filter(array_map('trim', $insIds));
                if (!empty($cleanInsIds)) {
                    $query->whereIn('id_insuranceCompany', $cleanInsIds);
                }
            }

            // 6. Фильтр по филиалам
            if ($request->filled('branch_ids')) {
                $branchIds = is_array($request->branch_ids)
                    ? $request->branch_ids
                    : explode(',', (string) $request->branch_ids);
                $cleanBranchIds = array_filter(array_map('trim', $branchIds));
                if (!empty($cleanBranchIds)) {
                    $query->whereIn('our_company', $cleanBranchIds);
                }
            }

            // 7. Фильтр по типу убытка
            if ($request->filled('ub_types')) {
                $ubTypes = is_array($request->ub_types)
                    ? $request->ub_types
                    : explode(',', (string) $request->ub_types);
                $cleanUbTypes = array_filter($ubTypes, fn($v) => $v !== '' && $v !== null);
                if (!empty($cleanUbTypes)) {
                    $query->whereIn('ub_type', $cleanUbTypes);
                }
            }

            // 8. Текстовые поисковые фильтры
            if ($request->filled('id')) {
                $query->where('id', 'LIKE', '%' . trim((string) $request->id) . '%');
            }
            if ($request->filled('ub_number')) {
                $ub = trim((string) $request->ub_number);
                $query->where(function ($q) use ($ub) {
                    $q->where('numberInsuranceCompanyApplication', 'LIKE', "%{$ub}%")
                        ->orWhere('ub_number', 'LIKE', "%{$ub}%");
                });
            }
            if ($request->filled('ClientName')) {
                $query->where('ClientName', 'LIKE', '%' . trim((string) $request->ClientName) . '%');
            }
            if ($request->filled('ClientPhone')) {
                $query->where('ClientPhone', 'LIKE', '%' . trim((string) $request->ClientPhone) . '%');
            }
            if ($request->filled('CarModel')) {
                $query->where('CarModel', 'LIKE', '%' . trim((string) $request->CarModel) . '%');
            }
            if ($request->filled('gnz')) {
                $query->where('gnz', 'LIKE', '%' . trim((string) $request->gnz) . '%');
            }
            if ($request->filled('vin')) {
                $query->where('vin', 'LIKE', '%' . trim((string) $request->vin) . '%');
            }
            if ($request->filled('sto_name') && Schema::hasColumn('applications', 'service_name')) {
                $query->where('service_name', 'LIKE', '%' . trim((string) $request->sto_name) . '%');
            }

            // 9. Общий поиск
            if ($request->filled('search')) {
                $s = trim((string) $request->search);
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

            // ПОДГРУЖАЕМ КАРТУ СЕРВИСОВ
            $servicesMap = collect();
            if (Schema::hasTable('services')) {
                $serviceCols = ['id', 'name'];
                if (Schema::hasColumn('services', 'bg_color'))
                    $serviceCols[] = 'bg_color';
                if (Schema::hasColumn('services', 'text_color'))
                    $serviceCols[] = 'text_color';

                $servicesMap = DB::table('services')
                    ->select($serviceCols)
                    ->get()
                    ->keyBy('id');
            }

            // ПОДГРУЖАЕМ КАРТУ ФИНАНСОВЫХ СТАТУСОВ НАПРЯМУЮ
            $finStatusesMap = collect();
            if (Schema::hasTable('finance_statuses')) {
                $finColumns = Schema::getColumnListing('finance_statuses');
                $selectCols = ['id', 'name'];

                if (in_array('fin_time', $finColumns, true))
                    $selectCols[] = 'fin_time';
                if (in_array('time', $finColumns, true))
                    $selectCols[] = 'time';
                if (in_array('fin_day', $finColumns, true))
                    $selectCols[] = 'fin_day';
                if (in_array('is_day', $finColumns, true))
                    $selectCols[] = 'is_day';
                if (in_array('day', $finColumns, true))
                    $selectCols[] = 'day';

                $finStatusesMap = DB::table('finance_statuses')
                    ->select($selectCols)
                    ->get()
                    ->keyBy('id');
            }

            // Таймер-сервис (если зарегистрирован в проекте)
            $timerService = null;
            if (class_exists(\App\Services\ApplicationTimerService::class)) {
                try {
                    $timerService = app(\App\Services\ApplicationTimerService::class);
                } catch (\Throwable $e) {
                    $timerService = null;
                }
            }

            $paginator->getCollection()->transform(function ($app) use ($timerService, $servicesMap, $finStatusesMap, $stoUserLogged, $currentUserServiceId, $user) {
                // ==========================================
                // СЕРВИС / СТО
                // ==========================================
                $servId = (int) ($app->id_service ?? $app->service_id ?? 0);

                if ($servId === 0 && $stoUserLogged && $currentUserServiceId) {
                    $servId = (int) $currentUserServiceId;
                }

                $servRow = $servId > 0 ? $servicesMap->get($servId) : null;
                $servName = null;
                $servBg = '#e0f2fe';
                $servText = '#0369a1';

                if ($servRow) {
                    $servName = $servRow->name;
                    $servBg = $servRow->bg_color ?? '#e0f2fe';
                    $servText = $servRow->text_color ?? '#0369a1';
                } elseif (!empty($app->service_name) && trim($app->service_name) !== '' && $app->service_name !== 'Прочее') {
                    $servName = trim($app->service_name);
                } elseif ($app->relationLoaded('service') && $app->service) {
                    $servName = $app->service->name;
                    $servBg = $app->service->bg_color ?? '#e0f2fe';
                    $servText = $app->service->text_color ?? '#0369a1';
                } elseif ($stoUserLogged) {
                    $servName = $user->service?->name ?? $user->name ?? 'СТО';
                }

                $app->service_name = $servName ?: 'Прочее';
                $app->service_bg = $servBg;
                $app->service_text = $servText;

                $app->setRelation('service', (object) [
                    'id' => $servId,
                    'name' => $app->service_name,
                    'bg_color' => $app->service_bg,
                    'text_color' => $app->service_text,
                ]);

                // ==========================================
                // ФИНАНСОВЫЙ СТАТУС (СОГЛ / ОПЛ)
                // ==========================================
                $finId = (int) ($app->finance_status_id ?? $app->id_finance_status ?? $app->finance_statuses_id ?? 0);
                $finRow = $finId > 0 ? $finStatusesMap->get($finId) : null;

                if ((!$app->relationLoaded('financeStatus') || !$app->financeStatus) && $finRow) {
                    $app->setRelation('financeStatus', (object) (array) $finRow);
                }

                $finObj = $app->financeStatus ?? $app->finStatus ?? ($finRow ? (object) (array) $finRow : null);

                $finTime = 0;
                $finDay = 0;
                if ($finObj) {
                    $finTime = (int) ($finObj->fin_time ?? $finObj->time ?? 0);
                    $finDay = (int) ($finObj->fin_day ?? $finObj->is_day ?? $finObj->day ?? 0);
                }

                $app->fin_time = $finTime;
                $app->fin_day = $finDay;
                $app->finance_status_id = $finId;
                $app->finance_statuses_id = $finId;

                // Название для отображения в столбце и тулбаре
                $app->finance_status_name = $finObj->name ?? $app->soglas_title ?? '';
                if ($finObj && !isset($app->finance_status)) {
                    $app->finance_status = [
                        'id' => $finId,
                        'name' => $finObj->name ?? '',
                    ];
                }

                // ==========================================
                // РАСЧЕТ ТАЙМЕРОВ (ОСНОВНОЙ + ФИНАНСОВЫЙ)
                // ==========================================
                $app->time = (int) ($app->status->time ?? $app->time ?? 0);
                $app->day = (int) ($app->status->day ?? $app->status->is_day ?? $app->day ?? 0);

                if ($timerService) {
                    try {
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
                    } catch (\Throwable $te) {
                        $custom = $this->calculateCustomTimer($app);
                        $app->is_overdue = $custom['is_overdue'];
                        $app->time_status_text = $custom['text'];
                        $app->app_timer = null;
                        $app->finance_timer = null;
                    }
                } else {
                    $custom = $this->calculateCustomTimer($app);
                    $app->is_overdue = $custom['is_overdue'];
                    $app->time_status_text = $custom['text'];
                    $app->app_timer = null;
                    $app->finance_timer = null;
                }

                $soglas = $this->calculateSoglasOplTimer($app);
                $app->soglas_title = !empty($app->finance_status_name) ? $app->finance_status_name : $soglas['title'];
                $app->soglas_time_text = $soglas['time_text'];
                $app->soglas_overdue = $soglas['is_overdue'];

                // Расчёт сумм
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
                'message' => 'Ошибка загрузки списка дел: ' . $e->getMessage(),
                'debug' => config('app.debug') ? [
                    'file' => $e->getFile() . ':' . $e->getLine(),
                    'msg' => $e->getMessage(),
                ] : null,
            ], 500);
        }
    }

    /**
     * Справочники для выпадающих фильтров реестра
     */
    /**
     * Опции для выпадающих фильтров реестра дел
     */
    public function filterOptions(Request $request): JsonResponse
    {
        try {
            /** @var \App\Models\User|null $user */
            $user = $request->user();

            // 1. Статусы дел
            $statuses = ApplicationStatus::orderBy('id', 'asc')
                ->get()
                ->map(function ($st) {
                    $bg = $st->bg_color ?? $st->color ?? null;
                    $text = $st->text_color ?? null;

                    if (!$bg) {
                        $palette = [
                            1 => ['#e0f2fe', '#0369a1'],
                            2 => ['#fef3c7', '#92400e'],
                            3 => ['#fce7f3', '#9d174d'],
                            4 => ['#ede9fe', '#5b21b6'],
                            8 => ['#dcfce7', '#15803d'],
                            15 => ['#ffedd5', '#9a3412'],
                            17 => ['#e0e7ff', '#3730a3'],
                            18 => ['#ccfbf1', '#115e59'],
                            25 => ['#f1f5f9', '#475569'],
                            27 => ['#fee2e2', '#991b1b'],
                        ];
                        $bg = $palette[$st->id][0] ?? '#f1f5f9';
                        $text = $palette[$st->id][1] ?? '#0f172a';
                    }

                    return [
                        'id' => (int) $st->id,
                        'name' => $st->name ?? $st->title ?? "Статус #{$st->id}",
                        'bg_color' => $bg,
                        'text_color' => $text ?: '#0f172a',
                    ];
                });

            // 2. Финансовые статусы (Согл/Опл)
            $financeStatuses = [];
            if (Schema::hasTable('finance_statuses')) {
                $financeStatuses = DB::table('finance_statuses')
                    ->select('id', 'name')
                    ->orderBy('id', 'asc')
                    ->get()
                    ->map(fn($f) => [
                        'id' => (int) $f->id,
                        'name' => $f->name ?? "Фин. статус #{$f->id}",
                    ]);
            }

            // 3. Города
            $citiesQuery = City::select('id', 'name')->orderBy('name', 'asc');
            if ($user && method_exists($user, 'isSystemAdmin') && !$user->isSystemAdmin() && method_exists($user, 'cities')) {
                $cityIds = $user->cities()->pluck('cities.id')->toArray();
                if (!empty($cityIds)) {
                    $citiesQuery->whereIn('id', $cityIds);
                }
            }
            $cities = $citiesQuery->get();

            // 4. Страховые компании
            $insuranceCompanies = InsuranceCompany::select('id', 'name')
                ->orderBy('name', 'asc')
                ->get();

            // 5. Филиалы / СТОА
            $branchesQuery = OurCompany::query();
            $availableBranchIds = [];

            if ($user) {
                if (method_exists($user, 'isSystemAdmin') && $user->isSystemAdmin()) {
                    $availableBranchIds = OurCompany::pluck('id')->toArray();
                } elseif (method_exists($user, 'getAvailableBranchIds')) {
                    $availableBranchIds = $user->getAvailableBranchIds();
                }
            }

            if (!empty($availableBranchIds)) {
                $branchesQuery->whereIn('id', $availableBranchIds);
            }

            $branches = $branchesQuery->orderBy('name', 'asc')->get()->map(function ($b) {
                return [
                    'id' => (int) $b->id,
                    'name' => $b->name ?? $b->our_name ?? "Филиал #{$b->id}",
                ];
            });

            // 6. Сотрудники (МРК — 5, Наставник — 6, Запчастист — 9) в филиалах пользователя
            $targetRoleIds = [5, 6, 9];
            $userIds = collect();

            // А. Из таблицы связей our_company_user_role
            if (Schema::hasTable('our_company_user_role')) {
                $roleQuery = DB::table('our_company_user_role')
                    ->whereIn('user_group_id', $targetRoleIds);

                // Ограничиваем филиалами пользователя
                if (!empty($availableBranchIds)) {
                    $roleQuery->whereIn('our_company_id', $availableBranchIds);
                }

                $userIds = $userIds->merge($roleQuery->pluck('user_id'));
            }

            // Б. Если роли назначены напрямую в таблице users
            if (Schema::hasColumn('users', 'user_group_id')) {
                $directUserQuery = DB::table('users')->whereIn('user_group_id', $targetRoleIds);
                $userIds = $userIds->merge($directUserQuery->pluck('id'));
            }

            $uniqueUserIds = $userIds->unique()->filter()->values()->all();

            // В. Загрузка пользователей со склейкой ФИО
            $employees = collect();
            if (!empty($uniqueUserIds)) {
                $userCols = Schema::getColumnListing('users');
                $hasLastName = in_array('last_name', $userCols, true);
                $hasPatronymic = in_array('patronymic', $userCols, true) || in_array('middle_name', $userCols, true);
                $patronymicCol = in_array('patronymic', $userCols, true) ? 'patronymic' : 'middle_name';

                $selectCols = ['id', 'name'];
                if (in_array('email', $userCols, true))
                    $selectCols[] = 'email';
                if ($hasLastName)
                    $selectCols[] = 'last_name';
                if ($hasPatronymic)
                    $selectCols[] = "{$patronymicCol} as patronymic";

                $employees = DB::table('users')
                    ->whereIn('id', $uniqueUserIds)
                    ->select($selectCols)
                    ->get()
                    ->map(function ($u) use ($hasLastName, $hasPatronymic) {
                        $parts = [];
                        if ($hasLastName && !empty($u->last_name)) {
                            $parts[] = trim($u->last_name);
                        }
                        if (!empty($u->name)) {
                            $parts[] = trim($u->name);
                        }
                        if ($hasPatronymic && !empty($u->patronymic)) {
                            $parts[] = trim($u->patronymic);
                        }

                        $fio = implode(' ', $parts);

                        return [
                            'id' => (int) $u->id,
                            'name' => !empty($fio) ? $fio : ($u->name ?? $u->email ?? "Сотрудник #{$u->id}"),
                            'last_name' => $u->last_name ?? $u->name,
                        ];
                    })
                    ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                    ->values();
            }

            return response()->json([
                'statuses' => $statuses,
                'finance_statuses' => $financeStatuses,
                'cities' => $cities,
                'insurance_companies' => $insuranceCompanies,
                'branches' => $branches,
                'employees' => $employees,
            ]);
        } catch (\Throwable $e) {
            Log::error('Ошибка в ApplicationController::filterOptions: ' . $e->getMessage(), [
                'file' => $e->getFile() . ':' . $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'statuses' => [],
                'finance_statuses' => [],
                'cities' => [],
                'insurance_companies' => [],
                'branches' => [],
                'employees' => [],
            ]);
        }
    }
    /**
     * Счетчики бейджей для меню
     */
    public function counts(Request $request): JsonResponse
    {
        /** @var \App\Models\User|null $user */
        $user = $request->user();
        $base = Application::query();

        if ($user && method_exists($user, 'isSystemAdmin') && !$user->isSystemAdmin()) {
            if (method_exists($user, 'isSto') && $user->isSto()) {
                $serviceId = method_exists($user, 'getServiceId') ? $user->getServiceId() : null;
                if ($serviceId) {
                    $base->where('id_service', $serviceId);
                } else {
                    return response()->json([
                        'requests' => 0,
                        'applications' => 0,
                        'acceptance' => 0,
                        'archive' => 0,
                        'trash' => 0,
                    ]);
                }
            } else {
                $availableBranches = method_exists($user, 'getAvailableBranchIds')
                    ? $user->getAvailableBranchIds()
                    : [];

                if (!empty($availableBranches)) {
                    $base->whereIn('our_company', $availableBranches);
                } else {
                    return response()->json([
                        'requests' => 0,
                        'applications' => 0,
                        'acceptance' => 0,
                        'archive' => 0,
                        'trash' => 0,
                    ]);
                }

                if (method_exists($user, 'cities')) {
                    $availableCityIds = $user->cities()->pluck('cities.id')->toArray();
                    if (!empty($availableCityIds)) {
                        $base->whereIn('id_city', $availableCityIds);
                    }
                }
            }
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
     * Вспомогательный расчёт финансов дела
     */
    protected function calculateFinances(Application $app): array
    {
        $sumSk = (float) ($app->sum_sk ?? 0);
        $sumZap = (float) ($app->sum_zap ?? 0);
        $remontSum = (float) ($app->remont_sum ?? 0);
        $totalCosts = $sumZap + $remontSum;
        $remains = max(0, $sumSk - $totalCosts);

        return [
            'sum_sk' => $sumSk,
            'sum_zap' => $sumZap,
            'remont_sum' => $remontSum,
            'total_costs' => $totalCosts,
            'ic_pay' => (float) ($app->ic_pay ?? 0),
            'sto_pay' => (float) ($app->sto_pay ?? 0),
            'remains' => $remains,
            'remains_percent' => $sumSk > 0 ? (int) round(($remains / $sumSk) * 100) : 0,
        ];
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

        switch ((int) $app->id_status) {
            case 1:
            case 2:
                $startDate = $app->created_at;
                break;
            case 11:
                $startDate = $app->ic_answer_date ?? $app->updated_at ?? $app->created_at;
                break;
            case 13:
                $startDate = $app->view_date ?? $app->updated_at;
                break;
            case 15:
                $startDate = $app->sp_date ?? $app->updated_at;
                break;
            case 17:
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

        $diffHours = $start->diffInHours($now);
        $limitHours = (int) $status->time;

        if ($diffHours > $limitHours) {
            return ['is_overdue' => true, 'text' => 'просрочено'];
        }

        return ['is_overdue' => false, 'text' => "{$diffHours}часов"];
    }

    /**
     * Расчет блока «Согл/Опл»
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
     * Подсчет рабочих дней (исключая субботы, воскресенья и праздники РФ)
     */
    protected function calculateWorkingDaysBetween(Carbon $start, Carbon $end): int
    {
        if ($start->gt($end)) {
            return 0;
        }

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
            if (!$current->isWeekend() && !in_array($current->format('m-d'), $holidays, true)) {
                $workDays++;
            }
            $current->addDay();
        }

        return max(0, $workDays - 1);
    }

    /**
     * Получение данных конкретного дела
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            /** @var \App\Models\User|null $user */
            $user = $request->user();

            $app = Application::with([
                'status',
                'insuranceCompany:id,name',
                'ourCompany:id,name',
                'service:id,name',
                'city:id,name',
                'mrk:id,name',
                'spm:id,name',
                'mentor:id,name',
                'lawyer:id,name,phone,email',
            ])->findOrFail($id);

            if ($user && method_exists($user, 'isSystemAdmin') && !$user->isSystemAdmin()) {
                if (method_exists($user, 'isSto') && $user->isSto()) {
                    $serviceId = method_exists($user, 'getServiceId') ? $user->getServiceId() : null;
                    if (!$serviceId || (int) $app->id_service !== $serviceId) {
                        return response()->json(['message' => 'Доступ к данному делу ограничен'], 403);
                    }
                }
            }

            $finances = $this->calculateFinances($app);

            $data = array_merge($app->attributesToArray(), [
                'status' => $app->status,
                'insurance_company' => $app->insuranceCompany,
                'our_company_rel' => $app->ourCompany,
                'service' => $app->service,
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
            Log::error("Ошибка в ApplicationController::show({$id}): " . $e->getMessage());
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

        foreach ($input as $key => $value) {
            if ($value === '') {
                $input[$key] = null;
            }
        }

        if (array_key_exists('gender', $input) && $input['gender'] !== null) {
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

        foreach (['ClientPhone', 'pred_phone'] as $field) {
            if (!array_key_exists($field, $input) || $input[$field] === null) {
                continue;
            }

            $digits = preg_replace('/\D+/', '', (string) $input[$field]) ?? '';

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

        $rules = [
            'CarModel' => ['sometimes', 'required', 'string', 'max:255'],
            'gnz' => ['sometimes', 'nullable', 'string', 'max:255'],
            'vin' => ['sometimes', 'nullable', 'string', 'max:255'],
            'mnf_year' => ['sometimes', 'nullable', 'string', 'max:255'],
            'vehicle_passport' => ['sometimes', 'nullable', 'string', 'max:255'],
            'Color' => ['sometimes', 'nullable', 'string', 'max:255'],
            'mileage' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'ClientName' => ['sometimes', 'nullable', 'string', 'max:255'],
            'ClientPhone' => ['sometimes', 'nullable', 'string', 'max:32', 'regex:/^\+7 \(\d{3}\) \d{3}-\d{2}-\d{2}$/'],
            'gender' => ['sometimes', 'nullable', 'integer', 'in:0,1'],
            'birthday' => ['sometimes', 'nullable', 'date'],
            'pred' => ['sometimes', 'nullable', 'string', 'max:255'],
            'pred_phone' => ['sometimes', 'nullable', 'string', 'max:32', 'regex:/^\+7 \(\d{3}\) \d{3}-\d{2}-\d{2}$/'],
            'ClientPasport' => ['sometimes', 'nullable', 'string', 'max:255'],
            'ClientPasport_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'ClientPasport_struct' => ['sometimes', 'nullable', 'string', 'max:255'],
            'id_insuranceCompany' => ['sometimes', 'required', 'integer', 'min:1'],
            'numberInsuranceCompanyApplication' => ['sometimes', 'required', 'string', 'max:40'],
            'ub_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'ub_type' => ['sometimes', 'nullable', 'integer', 'in:0,1'],
            'dogovor' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email_company' => ['sometimes', 'nullable', 'email', 'max:255'],
            'our_company' => ['sometimes', 'required', 'integer', 'min:1'],
            'id_city' => ['sometimes', 'required', 'integer', 'min:0'],
            'id_service' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'id_status' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'id_MRK' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'id_spm' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'id_lawyer' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'comment_true' => ['sometimes', 'nullable', 'string', 'max:255'],
            'comment_false' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];

        foreach ($dateFields as $field) {
            $rules[$field] = ['sometimes', 'nullable', 'date'];
        }

        foreach ($dateTimeFields as $field) {
            $rules[$field] = ['sometimes', 'nullable', 'date'];
        }

        $hasMentorColumn = Schema::hasColumn('applications', 'id_mentor');
        if ($hasMentorColumn) {
            $rules['id_mentor'] = ['sometimes', 'nullable', 'integer', 'min:1'];
        }

        $validated = validator($input, $rules, [
            'ClientPhone.regex' => 'Введите телефон клиента полностью: +7 (999) 123-45-67.',
            'pred_phone.regex' => 'Введите телефон представителя полностью: +7 (999) 123-45-67.',
            'gender.in' => 'Пол должен иметь значение 1, 0 или быть не указан.',
        ])->validate();

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
                    ? Carbon::parse($validated[$field])->format('Y-m-d H:i:s')
                    : null;
            }
        }

        if (empty($validated)) {
            return response()->json([
                'message' => 'Изменений нет',
                'data' => $application,
            ]);
        }

        try {
            $application->fill($validated);
            $application->save();
        } catch (\Throwable $e) {
            Log::error("Ошибка обновления дела #{$id}", [
                'fields' => array_keys($validated),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Не удалось сохранить дело: ' . $e->getMessage(),
            ], 500);
        }

        $relations = [
            'status',
            'insuranceCompany',
            'ourCompany',
            'city',
            'mrk',
            'spm',
        ];
        if (method_exists($application, 'lawyer') && Schema::hasTable('lawyers')) {
            $relations[] = 'lawyer';
        }
        if ($hasMentorColumn) {
            $relations[] = 'mentor';
        }

        return response()->json([
            'message' => 'Дело сохранено',
            'data' => $application->fresh($relations),
        ]);
    }

    /**
     * Получить сотрудников филиала с полным ФИО
     */
    public function getBranchEmployees(Request $request): JsonResponse
    {
        try {
            $branchId = $request->query('branch_id') ? (int) $request->query('branch_id') : null;

            if (!Schema::hasTable('our_company_user_role')) {
                return response()->json(['mrk' => [], 'mentors' => [], 'spm' => []]);
            }

            $userCols = Schema::getColumnListing('users');
            $hasLastName = in_array('last_name', $userCols, true);
            $hasPatronymic = in_array('patronymic', $userCols, true) || in_array('middle_name', $userCols, true);
            $patronymicCol = in_array('patronymic', $userCols, true) ? 'patronymic' : 'middle_name';

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

            $allBranchRecords = DB::table('our_company_user_role')
                ->join('users', 'users.id', '=', 'our_company_user_role.user_id')
                ->select($select)
                ->get();

            $branchRecords = $branchId
                ? $allBranchRecords->where('our_company_id', $branchId)
                : $allBranchRecords;

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

            if ($spm->isEmpty()) {
                $spm = User::query()->get()->map(fn($u) => [
                    'id' => (int) $u->id,
                    'name' => trim(($u->last_name ?? '') . ' ' . $u->name . ' ' . ($u->patronymic ?? $u->middle_name ?? '')),
                ])->sortBy('name')->values();
            }

            return response()->json([
                'mrk' => $mrk,
                'mentors' => $mentors,
                'spm' => $spm,
            ]);
        } catch (\Throwable $e) {
            Log::error('Ошибка в getBranchEmployees: ' . $e->getMessage());
            return response()->json(['mrk' => [], 'mentors' => [], 'spm' => []]);
        }
    }
}
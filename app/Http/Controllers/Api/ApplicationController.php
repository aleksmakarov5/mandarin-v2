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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

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

            if (!$user) {
                return response()->json([
                    'message' => 'Необходима авторизация.',
                ], 401);
            }

            // Только реальные вкладки реестра из $this->tabs.
            $validated = $request->validate([
                'tab' => [
                    'sometimes',
                    'string',
                    \Illuminate\Validation\Rule::in(
                        array_keys($this->tabs)
                    ),
                ],
                'per_page' => [
                    'sometimes',
                    'integer',
                    'min:1',
                    'max:200',
                ],
                'page' => [
                    'sometimes',
                    'integer',
                    'min:1',
                ],
            ]);

            $tab = $validated['tab'] ?? 'requests';
            $perPage = (int) ($validated['per_page'] ?? 50);

            // Разрешение открыть выбранную вкладку.
            \Illuminate\Support\Facades\Gate::forUser($user)
                ->authorize('viewRegistryTab', [$tab]);

            /*
             * Фильтры идентификаторов:
             * поддерживаем массив и строку "1,2,3".
             *
             * Неправильные значения возвращают 422,
             * а не незаметно отключают фильтр.
             */
            $parseIds = static function (string $field, bool $allowZero = false) use ($request): array {
                if (!$request->filled($field)) {
                    return [];
                }

                $raw = $request->input($field);

                if (!is_array($raw) && !is_string($raw) && !is_int($raw)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        $field => 'Ожидается массив идентификаторов или строка через запятую.',
                    ]);
                }

                $values = is_array($raw)
                    ? $raw
                    : explode(',', (string) $raw);

                $ids = [];

                foreach ($values as $value) {
                    if ($value === null || $value === '') {
                        continue;
                    }

                    if (!is_string($value) && !is_int($value)) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            $field => 'Идентификаторы должны быть целыми числами.',
                        ]);
                    }

                    $value = trim((string) $value);

                    if ($value === '') {
                        continue;
                    }

                    $id = filter_var(
                        $value,
                        FILTER_VALIDATE_INT,
                        [
                            'options' => [
                                'min_range' => $allowZero ? 0 : 1,
                            ],
                        ]
                    );

                    if ($id === false) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            $field => 'Передан недопустимый идентификатор.',
                        ]);
                    }

                    $ids[] = $id;
                }

                return array_values(array_unique($ids));
            };

            $appInstance = new Application();
            $appTable = $appInstance->getTable();
            $appColumns = Schema::getColumnListing($appTable);

            /*
             * Определяем реально существующие внешние ключи.
             * Если существует несколько вариантов одновременно,
             * используется первый в указанном порядке.
             */
            $branchColumn = null;

            foreach ([
                'our_company',
                'our_company_id',
                'id_our_company',
            ] as $column) {
                if (in_array($column, $appColumns, true)) {
                    $branchColumn = $column;
                    break;
                }
            }

            $serviceColumn = null;

            foreach (['id_service', 'service_id'] as $column) {
                if (in_array($column, $appColumns, true)) {
                    $serviceColumn = $column;
                    break;
                }
            }

            $financeColumn = null;

            foreach ([
                'finance_status_id',
                'id_finance_status',
                'finance_statuses_id',
            ] as $column) {
                if (in_array($column, $appColumns, true)) {
                    $financeColumn = $column;
                    break;
                }
            }

            // Связи: не запрашиваем необязательные колонки users.
            $withRelations = [];

            foreach ([
                'status',
                'financeStatus',
                'finStatus',
                'insuranceCompany',
                'ourCompany',
                'city',
                'mrk',
                'spm',
                'service',
            ] as $relation) {
                if (method_exists($appInstance, $relation)) {
                    $withRelations[] = $relation;
                }
            }

            if (
                method_exists($appInstance, 'mentor')
                && in_array('id_mentor', $appColumns, true)
            ) {
                $withRelations[] = 'mentor';
            }

            if (
                method_exists($appInstance, 'lawyer')
                && Schema::hasTable('lawyers')
            ) {
                $withRelations[] = 'lawyer';
            }

            $query = Application::query()->with($withRelations);

            /*
             * 1. Статусы вкладки применяются ВСЕГДА.
             *
             * status_ids только сужает вкладку:
             * через tab=requests нельзя запросить статусы архива.
             */
            $query->whereIn(
                "{$appTable}.id_status",
                $this->tabs[$tab]
            );

            $statusIds = $parseIds('status_ids');

            if ($statusIds !== []) {
                $query->whereIn(
                    "{$appTable}.id_status",
                    $statusIds
                );
            }

            /*
             * 2. Область доступа.
             *
             * Не используем if (isSto()) ... else ...:
             * права сотрудника и назначенного сервиса объединяются.
             *
             * Автоматический фильтр городов не добавляем:
             * в присланной ApplicationPolicy его нет.
             */
            $isSystemAdmin = $user->isSystemAdmin();

            if (!$isSystemAdmin) {
                $isOperationalTab = in_array(
                    $tab,
                    ['requests', 'applications'],
                    true
                );

                $excludedRoleIds = $isOperationalTab
                    ? [8]
                    : [8, 9];

                $branchIds = $user->branchRoles()
                    ->where('is_active', true)
                    ->whereNotIn('user_group_id', $excludedRoleIds)
                    ->whereHas('role')
                    ->pluck('our_company_id')
                    ->map(static fn($id) => (int) $id)
                    ->filter(static fn($id) => $id > 0)
                    ->unique()
                    ->values()
                    ->all();

                // Как в ApplicationPolicy::applicationAssignedToOwnService().
                $ownServiceId = $isOperationalTab
                    ? (int) ($user->getServiceId() ?? 0)
                    : 0;

                $hasBranchAccess = $branchColumn !== null
                    && $branchIds !== [];

                $hasServiceAccess = $serviceColumn !== null
                    && $ownServiceId > 0;

                $query->where(function ($scope) use ($appTable, $branchColumn, $serviceColumn, $branchIds, $ownServiceId, $hasBranchAccess, $hasServiceAccess) {
                    if ($hasBranchAccess) {
                        $scope->whereIn(
                            "{$appTable}.{$branchColumn}",
                            $branchIds
                        );
                    }

                    if ($hasServiceAccess) {
                        if ($hasBranchAccess) {
                            $scope->orWhere(
                                "{$appTable}.{$serviceColumn}",
                                $ownServiceId
                            );
                        } else {
                            $scope->where(
                                "{$appTable}.{$serviceColumn}",
                                $ownServiceId
                            );
                        }
                    }

                    // Нет назначений — нет доступа.
                    if (!$hasBranchAccess && !$hasServiceAccess) {
                        $scope->whereRaw('1 = 0');
                    }
                });
            }

            // 3. Финансовые статусы.
            $financeIds = $parseIds('finance_status_ids');

            if ($financeIds !== []) {
                if ($financeColumn === null) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'finance_status_ids' =>
                            'В таблице дел не настроен внешний ключ финансового статуса.',
                    ]);
                }

                $query->whereIn(
                    "{$appTable}.{$financeColumn}",
                    $financeIds
                );
            }

            // 4. Сотрудники: условие OR обязательно внутри общей группы.
            $employeeIds = $parseIds('mrk_ids');

            if ($employeeIds !== []) {
                $employeeColumns = array_values(array_intersect(
                    ['id_MRK', 'id_spm', 'id_mentor'],
                    $appColumns
                ));

                if ($employeeColumns === []) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'mrk_ids' => 'В таблице дел отсутствуют поля ответственных сотрудников.',
                    ]);
                }

                $query->where(function ($scope) use ($employeeColumns, $employeeIds, $appTable) {
                    foreach ($employeeColumns as $index => $column) {
                        if ($index === 0) {
                            $scope->whereIn(
                                "{$appTable}.{$column}",
                                $employeeIds
                            );
                        } else {
                            $scope->orWhereIn(
                                "{$appTable}.{$column}",
                                $employeeIds
                            );
                        }
                    }
                });
            }

            // 5. Остальные фильтры только сужают область доступа.
            $listFilters = [
                'city_ids' => 'id_city',
                'insurance_ids' => 'id_insuranceCompany',
                'branch_ids' => $branchColumn,
                'ub_types' => 'ub_type',
            ];

            foreach ($listFilters as $field => $column) {
                $ids = $parseIds($field, $field === 'ub_types');

                if ($ids === []) {
                    continue;
                }

                if (
                    $column === null
                    || !in_array($column, $appColumns, true)
                ) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        $field => 'Этот фильтр не поддерживается текущей структурой таблицы дел.',
                    ]);
                }

                if (
                    $field === 'ub_types'
                    && array_diff($ids, [0, 1]) !== []
                ) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        $field => 'Допустимые типы убытка: 0 и 1.',
                    ]);
                }

                $query->whereIn(
                    "{$appTable}.{$column}",
                    $ids
                );
            }

            // 6. Текстовые фильтры.
            $textData = $request->validate([
                'id' => ['sometimes', 'nullable', 'string', 'max:255'],
                'ub_number' => ['sometimes', 'nullable', 'string', 'max:255'],
                'ClientName' => ['sometimes', 'nullable', 'string', 'max:255'],
                'ClientPhone' => ['sometimes', 'nullable', 'string', 'max:255'],
                'CarModel' => ['sometimes', 'nullable', 'string', 'max:255'],
                'gnz' => ['sometimes', 'nullable', 'string', 'max:255'],
                'vin' => ['sometimes', 'nullable', 'string', 'max:255'],
                'sto_name' => ['sometimes', 'nullable', 'string', 'max:255'],
                'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            ]);

            foreach ([
                'id',
                'ClientName',
                'ClientPhone',
                'CarModel',
                'gnz',
                'vin',
            ] as $column) {
                $value = trim((string) ($textData[$column] ?? ''));

                if ($value !== '') {
                    $query->where(
                        "{$appTable}.{$column}",
                        'LIKE',
                        "%{$value}%"
                    );
                }
            }

            $ubNumber = trim((string) ($textData['ub_number'] ?? ''));

            if ($ubNumber !== '') {
                $query->where(function ($scope) use ($ubNumber, $appTable) {
                    $scope
                        ->where(
                            "{$appTable}.numberInsuranceCompanyApplication",
                            'LIKE',
                            "%{$ubNumber}%"
                        )
                        ->orWhere(
                            "{$appTable}.ub_number",
                            'LIKE',
                            "%{$ubNumber}%"
                        );
                });
            }

            $stoName = trim((string) ($textData['sto_name'] ?? ''));

            if (
                $stoName !== ''
                && in_array('service_name', $appColumns, true)
            ) {
                $query->where(
                    "{$appTable}.service_name",
                    'LIKE',
                    "%{$stoName}%"
                );
            }

            $search = trim((string) ($textData['search'] ?? ''));

            if ($search !== '') {
                $searchColumns = array_values(array_intersect([
                    'id',
                    'numberInsuranceCompanyApplication',
                    'ub_number',
                    'CarModel',
                    'gnz',
                    'vin',
                    'ClientName',
                    'ClientPhone',
                ], $appColumns));

                $query->where(function ($scope) use ($searchColumns, $search, $appTable) {
                    foreach ($searchColumns as $index => $column) {
                        if ($index === 0) {
                            $scope->where(
                                "{$appTable}.{$column}",
                                'LIKE',
                                "%{$search}%"
                            );
                        } else {
                            $scope->orWhere(
                                "{$appTable}.{$column}",
                                'LIKE',
                                "%{$search}%"
                            );
                        }
                    }
                });
            }

            $paginator = $query
                ->orderBy("{$appTable}.id", 'desc')
                ->paginate($perPage);

            /*
             * Справочник СТО: только сервисы дел текущей страницы.
             * Не подставляем сервис пользователя в чужое/непривязанное дело.
             */
            $servicesMap = collect();

            if (
                $serviceColumn !== null
                && Schema::hasTable('services')
            ) {
                $serviceIds = $paginator->getCollection()
                    ->map(
                        static fn($app) =>
                            (int) ($app->getAttribute($serviceColumn) ?? 0)
                    )
                    ->filter(static fn($id) => $id > 0)
                    ->unique()
                    ->values()
                    ->all();

                if ($serviceIds !== []) {
                    $serviceColumns = ['id', 'name'];

                    foreach (['bg_color', 'text_color'] as $column) {
                        if (Schema::hasColumn('services', $column)) {
                            $serviceColumns[] = $column;
                        }
                    }

                    $servicesMap = DB::table('services')
                        ->select($serviceColumns)
                        ->whereIn('id', $serviceIds)
                        ->get()
                        ->keyBy('id');
                }
            }

            $finStatusesMap = collect();

            if (Schema::hasTable('finance_statuses')) {
                $availableColumns = Schema::getColumnListing(
                    'finance_statuses'
                );

                $selectColumns = array_values(array_intersect([
                    'id',
                    'name',
                    'fin_time',
                    'time',
                    'fin_day',
                    'is_day',
                    'day',
                    'color',
                    'bg_color',
                    'text_color',
                ], $availableColumns));

                $finStatusesMap = DB::table('finance_statuses')
                    ->select($selectColumns)
                    ->get()
                    ->keyBy('id');
            }

            $timerService = null;

            if (class_exists(\App\Services\ApplicationTimerService::class)) {
                try {
                    $timerService = app(
                        \App\Services\ApplicationTimerService::class
                    );
                } catch (\Throwable $e) {
                    Log::warning('Не удалось создать сервис таймеров.', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $paginator->getCollection()->transform(
                function ($app) use ($user, $serviceColumn, $financeColumn, $servicesMap, $finStatusesMap, $timerService) {
                    // СТО определяется по самому делу.
                    $serviceId = $serviceColumn !== null
                        ? (int) ($app->getAttribute($serviceColumn) ?? 0)
                        : 0;

                    $serviceRow = $serviceId > 0
                        ? $servicesMap->get($serviceId)
                        : null;

                    $serviceRelation = $app->relationLoaded('service')
                        ? $app->getRelation('service')
                        : null;

                    $serviceName = trim((string) (
                        $serviceRow->name
                        ?? $serviceRelation?->name
                        ?? ''
                    ));

                    if ($serviceName === '') {
                        $serviceName = trim((string) (
                            $app->getAttribute('service_name') ?? ''
                        ));
                    }

                    $serviceName = $serviceName !== ''
                        ? $serviceName
                        : 'Прочее';

                    $serviceBg = $serviceRow->bg_color
                        ?? $serviceRelation?->bg_color
                        ?? '#e0f2fe';

                    $serviceText = $serviceRow->text_color
                        ?? $serviceRelation?->text_color
                        ?? '#0369a1';

                    // Финансовый статус.
                    $financeId = $financeColumn !== null
                        ? (int) ($app->getAttribute($financeColumn) ?? 0)
                        : 0;

                    $financeObject = $financeId > 0
                        ? $finStatusesMap->get($financeId)
                        : null;

                    if (!$financeObject) {
                        foreach (['financeStatus', 'finStatus'] as $relation) {
                            if (
                                $app->relationLoaded($relation)
                                && $app->getRelation($relation)
                            ) {
                                $financeObject = $app->getRelation($relation);
                                break;
                            }
                        }
                    }

                    $app->fin_time = (int) (
                        $financeObject->fin_time
                        ?? $financeObject->time
                        ?? 0
                    );

                    $app->fin_day = (int) (
                        $financeObject->fin_day
                        ?? $financeObject->is_day
                        ?? $financeObject->day
                        ?? 0
                    );

                    $status = $app->relationLoaded('status')
                        ? $app->getRelation('status')
                        : null;

                    $app->time = (int) (
                        $status?->time ?? $app->time ?? 0
                    );

                    $app->day = (int) (
                        $status?->day
                        ?? $status?->is_day
                        ?? $app->day
                        ?? 0
                    );

                    // Таймеры.
                    $timers = [];

                    if ($timerService) {
                        try {
                            $timers = $timerService->calculate($app);
                        } catch (\Throwable $e) {
                            Log::warning('Ошибка расчёта таймеров дела.', [
                                'application_id' => $app->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }

                    $customTimer = [
                        'is_overdue' => false,
                        'text' => '',
                    ];

                    if (empty($timers['app_timer'])) {
                        $customTimer = $this->calculateCustomTimer($app);
                    }

                    $soglas = $this->calculateSoglasOplTimer($app);

                    $sumSk = (float) ($app->sum_sk ?? 0);
                    $sumZap = (float) ($app->sum_zap ?? 0);
                    $remontSum = (float) ($app->remont_sum ?? 0);
                    $totalCosts = $sumZap + $remontSum;
                    $remains = max(0, $sumSk - $totalCosts);

                    /*
                     * Формируем массив ответа.
                     *
                     * Не записываем stdClass через setRelation():
                     * Eloquent ожидает сериализуемую модель/коллекцию.
                     */
                    $data = $app->toArray();

                    $data['service_name'] = $serviceName;
                    $data['service_bg'] = $serviceBg;
                    $data['service_text'] = $serviceText;

                    $data['service'] = [
                        'id' => $serviceId > 0 ? $serviceId : null,
                        'name' => $serviceName,
                        'bg_color' => $serviceBg,
                        'text_color' => $serviceText,
                    ];

                    $financeName = (string) (
                        $financeObject->name
                        ?? $soglas['title']
                        ?? ''
                    );

                    $data['finance_status_id'] = $financeId;
                    $data['finance_statuses_id'] = $financeId;
                    $data['finance_status_name'] = $financeName;
                    $data['fin_time'] = (int) $app->fin_time;
                    $data['fin_day'] = (int) $app->fin_day;

                    // Стандартное snake_case-имя связи Eloquent.
                    $data['finance_status'] = $financeObject
                        ? array_merge((array) (
                            $financeObject instanceof
                            \Illuminate\Database\Eloquent\Model
                            ? $financeObject->toArray()
                            : $financeObject
                        ), [
                            'id' => $financeId,
                            'name' => $financeName,
                        ])
                        : null;

                    $data['app_timer'] = $timers['app_timer'] ?? null;
                    $data['finance_timer'] = $timers['finance_timer'] ?? null;

                    $data['max_hour'] =
                        $timers['app_timer']['max_hour'] ?? 0;

                    $data['count_hour'] =
                        $timers['app_timer']['count_hour'] ?? 0;

                    $data['is_overdue'] =
                        $timers['app_timer']['is_overdue']
                        ?? $customTimer['is_overdue'];

                    $data['is_day'] =
                        $timers['app_timer']['is_day'] ?? false;

                    $data['time_status_text'] =
                        $timers['app_timer']['display_text']
                        ?? $customTimer['text'];

                    $data['max_hour_f'] =
                        $timers['finance_timer']['max_hour_f'] ?? 0;

                    $data['count_hour_f'] =
                        $timers['finance_timer']['count_hour_f'] ?? 0;

                    $data['soglas_title'] = $financeName;
                    $data['soglas_time_text'] =
                        $soglas['time_text'] ?? '';

                    $data['soglas_overdue'] =
                        $soglas['is_overdue'] ?? false;

                    $data['sum_sk'] = $sumSk;
                    $data['sum_zap'] = $sumZap;
                    $data['remont_sum'] = $remontSum;
                    $data['total_costs'] = $totalCosts;
                    $data['ic_pay'] = (float) ($app->ic_pay ?? 0);
                    $data['sto_pay'] = (float) ($app->sto_pay ?? 0);
                    $data['remains'] = $remains;

                    $data['remains_percent'] = $sumSk > 0
                        ? (int) round(($remains / $sumSk) * 100)
                        : 0;

                    // Права действий — только через существующую policy.
                    $data['permissions']['status'] = [
                        'change_status' =>
                            \Illuminate\Support\Facades\Gate::forUser($user)
                                ->allows('changeStatus', $app),

                        'change_finance_status' =>
                            \Illuminate\Support\Facades\Gate::forUser($user)
                                ->allows('changeFinanceStatus', $app),
                    ];

                    return $data;
                }
            );

            return response()->json($paginator);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'message' => 'Раздел реестра недоступен для текущей роли.',
            ], 403);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Некорректные параметры фильтрации.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Ошибка в ApplicationController::index.', [
                'error' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Ошибка загрузки списка дел.',
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
        /** @var User $user */
        $user = $request->user();

        $base = Application::query();

        if (!$user->isSystemAdmin()) {
            $this->applyRegistryVisibility($base, $user);
        }

        $countFor = function (string $tab) use ($user, $base): int {
            if (
                !Gate::forUser($user)->allows(
                    'viewRegistryTab',
                    [$tab]
                )
            ) {
                return 0;
            }

            return (clone $base)
                ->whereIn(
                    'id_status',
                    $this->tabs[$tab]
                )
                ->count();
        };

        return response()->json([
            'requests' => $countFor('requests'),

            'applications' => $countFor('applications'),

            'acceptance' => $countFor('acceptance'),

            'archive' => $countFor('archive'),

            'trash' => $countFor('trash'),
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
    /**
     * Получение данных дела для карточки
     */
    public function show(int $id): JsonResponse
    {
        try {
            $application = \App\Models\Application::findOrFail($id);
            \Illuminate\Support\Facades\Gate::authorize(
                'view',
                $application
            );
            // Список желаемых связей
            $possibleRelations = [
                'status',
                'insuranceCompany',
                'ourCompany',
                'city',
                'mrk',
                'spm',
                'partsManager',
                'mentor',
                'curator',
                'lawyer',
                'service',
            ];

            // Загружаем только те связи, методы которых физически объявлены в модели Application
            $relationsToLoad = [];
            foreach ($possibleRelations as $rel) {
                if (method_exists($application, $rel)) {
                    $relationsToLoad[] = $rel;
                }
            }

            if (!empty($relationsToLoad)) {
                $application->load($relationsToLoad);
            }

            // Вычисляем финансы, если метод объявлен в контроллере
            $finances = [];
            if (method_exists($this, 'calculateFinances')) {
                $finances = $this->calculateFinances($application);
            } else {
                $sumSk = (float) ($application->sum_sk ?? 0);
                $sumZap = (float) ($application->sum_zap ?? 0);
                $remontSum = (float) ($application->remont_sum ?? 0);
                $totalCosts = $sumZap + $remontSum;
                $remains = $sumSk - $totalCosts;

                $finances = [
                    'total_calculated_costs' => $totalCosts,
                    'calculated_remains' => $remains,
                    'calculated_margin' => $sumSk > 0 ? (int) round(($remains / $sumSk) * 100) : 0,
                ];
            }

            $data = array_merge($application->toArray(), $finances);

            // Гарантируем числовые идентификаторы внешних ключей для фронтенда
            $data['our_company'] = (int) ($application->our_company ?? $application->our_company_id ?? $application->id_our_company ?? 0);
            $data['id_status'] = (int) ($application->id_status ?? $application->status_id ?? 0);
            $data['id_MRK'] = $application->id_MRK ? (int) $application->id_MRK : null;
            $data['id_mentor'] = $application->id_mentor ?? $application->mentor_id ?? $application->id_curator ?? null;
            if ($data['id_mentor'])
                $data['id_mentor'] = (int) $data['id_mentor'];
            $data['id_spm'] = $application->id_spm ?? $application->spm_id ?? $application->id_parts_manager ?? null;
            if ($data['id_spm'])
                $data['id_spm'] = (int) $data['id_spm'];
            $data['id_lawyer'] = $application->id_lawyer ? (int) $application->id_lawyer : null;

            return response()->json([
                'status' => 'ok',
                'data' => $data,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => "Дело #{$id} не найдено"], 404);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'message' => 'Нет доступа к этому делу.',
            ], 403);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Ошибка в ApplicationController::show({$id}): " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Ошибка загрузки данных дела: ' . $e->getMessage(),
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

    /**
     * Единая область видимости дел для index().
     *
     * Пользователь видит:
     * - дела филиалов, где у него есть активная роль,
     *   кроме чистой роли СТО;
     * - дела, назначенные его собственному автосервису.
     */
    protected function applyRegistryVisibility(
        Builder $query,
        User $user
    ): void {
        $branchIds = $user->branchRoles()
            ->where('is_active', true)
            ->where('user_group_id', '<>', 8)
            ->whereHas('role')
            ->pluck('our_company_id')
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $serviceId = method_exists($user, 'getServiceId')
            ? $user->getServiceId()
            : null;

        $serviceColumn = Schema::hasColumn(
            'applications',
            'id_service'
        )
            ? 'id_service'
            : (
                Schema::hasColumn('applications', 'service_id')
                ? 'service_id'
                : null
            );

        $cityIds = [];

        if ($branchIds !== [] && method_exists($user, 'cities')) {
            $cityIds = $user->cities()
                ->pluck('cities.id')
                ->map(fn($id) => (int) $id)
                ->all();
        }

        $hasBranchAccess = $branchIds !== [];
        $hasServiceAccess = $serviceId !== null
            && $serviceId > 0
            && $serviceColumn !== null;

        $query->where(function (Builder $scope) use ($branchIds, $cityIds, $serviceId, $serviceColumn, $hasBranchAccess, $hasServiceAccess) {
            if ($hasBranchAccess) {
                $scope->where(function (Builder $branchScope) use ($branchIds, $cityIds) {
                    $branchScope->whereIn(
                        'our_company',
                        $branchIds
                    );

                    if ($cityIds !== []) {
                        $branchScope->whereIn(
                            'id_city',
                            $cityIds
                        );
                    }
                });
            }

            if ($hasServiceAccess) {
                if ($hasBranchAccess) {
                    $scope->orWhere(
                        $serviceColumn,
                        $serviceId
                    );
                } else {
                    $scope->where(
                        $serviceColumn,
                        $serviceId
                    );
                }
            }

            if (!$hasBranchAccess && !$hasServiceAccess) {
                $scope->whereRaw('1 = 0');
            }
        });
    }
}
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Services\ApplicationTimerService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use app\Providers\AuthServiceProvider;
use Illuminate\Support\Facades\Gate;

class ApplicationGeneralController extends Controller
{
    private const DATE_FIELDS = [
        'birthday',
        'ClientPasport_date',
        'DTPdate',
        'napr_date',
        'start_date',
        'sp_date',
        'end_date',
    ];

    private const DATETIME_FIELDS = [
        'view_date',
        'check_date',
    ];

    private const PHONE_FIELDS = [
        'ClientPhone',
        'pred_phone',
    ];

    public function show(int $id, ApplicationTimerService $timerService): JsonResponse
    {
        $app = Application::with([
            'status',
            'financeStatus',
            'insuranceCompany:id,name,bg_color,text_color',
            'ourCompany:id,name',
            'city:id,name',
            'mrk:id,name,last_name,patronymic',
            'spm:id,name,last_name,patronymic',
            'mentor:id,name,last_name,patronymic',
            'lawyer:id,name,phone,email,is_cooperating',
        ])->findOrFail($id);
        Gate::authorize('view', $app);
        // Передаем временные параметры из связанных моделей в объект дела
        $app->time = $app->status->time ?? $app->time ?? 0;
        $app->day = $app->status->day ?? $app->status->is_day ?? $app->day ?? 0;

        if ($app->financeStatus) {
            $app->fin_time = $app->financeStatus->fin_time ?? $app->financeStatus->time ?? 0;
            $app->fin_day = $app->financeStatus->fin_day ?? $app->financeStatus->is_day ?? 0;
        }

        // Единый расчет таймеров через сервис
        $timers = $timerService->calculate($app);

        $statusId = (int) ($app->id_status ?? $app->status_id ?? 0);
        $finStatusId = (int) ($app->finance_status_id ?? $app->id_finance_status ?? 0);

        // Расчет финансовых показателей для карточки
        $sumSk = (float) ($app->sum_sk ?? 0);
        $sumZap = (float) ($app->sum_zap ?? 0);
        $remontSum = (float) ($app->remont_sum ?? 0);
        $totalCosts = $sumZap + $remontSum;
        $remains = max(0, $sumSk - $totalCosts);

        // Получаем чистый нормализованный массив заявки
        $normalizedData = $this->normalizeForResponse($app);

        $result = array_merge($normalizedData, [
            'id_status' => $statusId,
            'finance_status_id' => $finStatusId,

            // Структуры таймеров
            'app_timer' => $timers['app_timer'] ?? null,
            'finance_timer' => $timers['finance_timer'] ?? null,

            // Плоские поля таймера
            'max_hour' => $timers['app_timer']['max_hour'] ?? 0,
            'count_hour' => $timers['app_timer']['count_hour'] ?? 0,
            'is_overdue' => $timers['app_timer']['is_overdue'] ?? false,
            'is_day' => $timers['app_timer']['is_day'] ?? false,
            'time_status_text' => $timers['app_timer']['display_text'] ?? '',

            'max_hour_f' => $timers['finance_timer']['max_hour_f'] ?? 0,
            'count_hour_f' => $timers['finance_timer']['count_hour_f'] ?? 0,

            // Финансы
            'sum_sk' => $sumSk,
            'sum_zap' => $sumZap,
            'remont_sum' => $remontSum,
            'total_costs' => $totalCosts,
            'ic_pay' => (float) ($app->ic_pay ?? 0),
            'sto_pay' => (float) ($app->sto_pay ?? 0),
            'remains' => $remains,
            'remains_percent' => $sumSk > 0 ? (int) round(($remains / $sumSk) * 100) : 0,
        ]);

        return response()->json([
            'data' => $result,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $application = Application::query()->findOrFail($id);

        $data = $request->validate([
            // Автомобиль
            'CarModel' => ['sometimes', 'nullable', 'string', 'max:191'],
            'gnz' => ['sometimes', 'nullable', 'string', 'max:32'],
            'vin' => ['sometimes', 'nullable', 'string', 'max:32'],
            'mnf_year' => ['sometimes', 'nullable', 'string', 'max:10'],
            'color' => ['sometimes', 'nullable', 'string', 'max:100'],
            'Color' => ['sometimes', 'nullable', 'string', 'max:100'],
            'mileage' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'vehicle_passport' => ['sometimes', 'nullable', 'string', 'max:100'],

            // Клиент
            'ClientName' => ['sometimes', 'nullable', 'string', 'max:191'],
            'gender' => ['sometimes', 'nullable', 'integer', 'in:0,1'],
            'ClientPhone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'pred' => ['sometimes', 'nullable', 'string', 'max:191'],
            'pred_phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'birthday' => ['sometimes', 'nullable', 'date'],

            // Паспорт
            'ClientPasport' => ['sometimes', 'nullable', 'string', 'max:50'],
            'ClientPasport_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'ClientPasport_date' => ['sometimes', 'nullable', 'date'],
            'ClientPasport_struct' => ['sometimes', 'nullable', 'string', 'max:2000'],

            // Страховая компания
            'id_insuranceCompany' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'ub_type' => ['sometimes', 'nullable', 'integer', 'in:0,1'],
            'numberInsuranceCompanyApplication' => ['sometimes', 'nullable', 'string', 'max:191'],
            'DTPdate' => ['sometimes', 'nullable', 'date'],
            'napr_date' => ['sometimes', 'nullable', 'date'],
            'ub_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'dogovor' => ['sometimes', 'nullable', 'string', 'max:100'],

            // Филиал, СТО и сроки
            'our_company' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'our_company_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'id_city' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'id_service' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'view_date' => ['sometimes', 'nullable'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'sp_date' => ['sometimes', 'nullable', 'date'],

            // Ответственные
            'id_MRK' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'id_mentor' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'id_spm' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'id_lawyer' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'comment_true' => ['sometimes', 'nullable', 'string', 'max:10000'],
        ]);

        foreach (self::PHONE_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = $this->normalizePhone($data[$field], $field);
            }
        }

        if (array_key_exists('vehicle_passport', $data) && $data['vehicle_passport'] !== null) {
            $data['vehicle_passport'] = mb_strtoupper(trim($data['vehicle_passport']));
        }

        // Синхронизация филиала в обе колонки (если они обе есть)
        if (array_key_exists('our_company', $data) || array_key_exists('our_company_id', $data)) {
            $bId = $data['our_company'] ?? $data['our_company_id'] ?? null;
            $data['our_company'] = $bId ? (int) $bId : null;
            $data['our_company_id'] = $bId ? (int) $bId : null;
        }

        $columns = Schema::getColumnListing($application->getTable());

        // Расчет даты окончания ремонта
        if (array_key_exists('start_date', $data) && in_array('end_date', $columns, true)) {
            $data['end_date'] = $data['start_date']
                ? $this->addWorkingDays($data['start_date'], 30)
                : null;
        }

        // Фильтруем данные строго по существующим колонкам в таблице applications
        $saveData = array_intersect_key($data, array_flip($columns));

        DB::transaction(function () use ($application, $saveData): void {
            if (isset($saveData['view_date'])) {
                Log::info('Saving view_date:', ['value' => $saveData['view_date']]);
            }
            $application->forceFill($saveData)->save();
        });

        return response()->json([
            'message' => 'Основные данные сохранены',
            'saved_fields' => array_keys($saveData),
            'data' => $this->normalizeForResponse($application->fresh()),
        ]);
    }

    public function options(): JsonResponse
    {
        $branchTable = Schema::hasTable('our_companies')
            ? 'our_companies'
            : (Schema::hasTable('branches') ? 'branches' : null);

        $serviceTable = Schema::hasTable('services')
            ? 'services'
            : (Schema::hasTable('service') ? 'service' : null);

        $statusTable = Schema::hasTable('application_statuses')
            ? 'application_statuses'
            : (Schema::hasTable('statuses') ? 'statuses' : null);

        $statuses = $statusTable ? DB::table($statusTable)
            ->select([
                'id',
                'name',
                'bg_color',
                'text_color',
                'color',
                'time',
                'is_day',
                'type',
                'user_group_id',
            ])
            ->orderBy('id')
            ->get()
            ->map(static fn($s) => [
                'id' => (int) $s->id,
                'name' => $s->name,
                'bg_color' => $s->bg_color ?: ($s->color ?: '#e2e8f0'),
                'text_color' => $s->text_color ?: '#1e293b',
                'time' => $s->time !== null ? (int) $s->time : null,
                'is_day' => (bool) $s->is_day,
                'type' => $s->type,
                'user_group_id' => $s->user_group_id,
            ]) : [];

        $financeStatuses = Schema::hasTable('finance_statuses')
            ? DB::table('finance_statuses')
                ->select([
                    'id',
                    'name',
                    'color',
                    'bg_color',
                    'text_color',
                    'time',
                    'is_day',
                ])
                ->orderBy('id')
                ->get()
                ->map(static fn($fs) => [
                    'id' => (int) $fs->id,
                    'name' => $fs->name,
                    'bg_color' => $fs->bg_color ?: ($fs->color ?: '#dbeafe'),
                    'text_color' => $fs->text_color ?: '#1e40af',
                    'time' => $fs->time !== null ? (int) $fs->time : null,
                    'is_day' => (bool) ($fs->is_day ?? true),
                ])
            : [];

        return response()->json([
            'statuses' => $statuses,
            'finance_statuses' => $financeStatuses,
            'cities' => $this->tableOptions('cities'),
            'branches' => $this->tableOptions($branchTable),
            'insurance_companies' => $this->tableOptions(
                'insurance_companies',
                ['bg_color', 'text_color']
            ),
            'services' => $this->tableOptions($serviceTable),
        ]);
    }

    public function branchEmployees(Request $request): JsonResponse
    {
        $branchId = $request->filled('branch_id')
            ? (int) $request->input('branch_id')
            : null;

        return response()->json([
            'mrk' => $this->staffByRole(5, $branchId),
            'mentors' => $this->staffByRole(6, $branchId),
            'spm' => $this->staffByRole(9, $branchId),
        ]);
    }

    public function lawyers(): JsonResponse
    {
        if (!Schema::hasTable('lawyers')) {
            return response()->json([]);
        }

        $columns = Schema::getColumnListing('lawyers');
        $nameColumn = $this->firstExistingColumn(
            $columns,
            ['name', 'fio', 'FIO']
        );

        if (!$nameColumn || !in_array('id', $columns, true)) {
            return response()->json([]);
        }

        $select = ['id', $nameColumn . ' as name'];

        foreach ([
            'phone',
            'email',
            'comment',
            'is_cooperating',
            'theme'
        ] as $field) {
            if (in_array($field, $columns, true)) {
                $select[] = $field;
            }
        }

        $lawyers = DB::table('lawyers')
            ->select($select)
            ->orderBy($nameColumn)
            ->get()
            ->map(static fn($row) => (array) $row)
            ->values();

        return response()->json($lawyers);
    }

    public function storeLawyer(Request $request): JsonResponse
    {
        if (!Schema::hasTable('lawyers')) {
            throw ValidationException::withMessages([
                'lawyer' => ['Таблица lawyers не найдена'],
            ]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:191'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'theme' => ['nullable', 'string', 'max:2000'],
            'is_cooperating' => ['nullable', 'boolean'],
        ]);

        $columns = Schema::getColumnListing('lawyers');
        $nameColumn = $this->firstExistingColumn(
            $columns,
            ['name', 'fio', 'FIO']
        );

        if (!$nameColumn) {
            throw ValidationException::withMessages([
                'lawyer' => ['В таблице lawyers не найдена колонка ФИО'],
            ]);
        }

        $insert = [
            $nameColumn => trim($validated['name']),
        ];

        if (in_array('phone', $columns, true)) {
            $insert['phone'] = $this->normalizePhone(
                $validated['phone'] ?? null,
                'phone'
            );
        }
        if (in_array('is_cooperating', $columns, true)) {
            $insert['is_cooperating'] = (int) ($validated['is_cooperating'] ?? 1);
        }

        $commentVal = $validated['comment'] ?? $validated['theme'] ?? null;
        if (in_array('theme', $columns, true)) {
            $insert['theme'] = $commentVal;
        } elseif (in_array('comment', $columns, true)) {
            $insert['comment'] = $commentVal;
        }

        if (in_array('email', $columns, true)) {
            $insert['email'] = $validated['email'] ?? null;
        }

        $now = now();
        if (in_array('created_at', $columns, true)) {
            $insert['created_at'] = $now;
        }
        if (in_array('updated_at', $columns, true)) {
            $insert['updated_at'] = $now;
        }

        $id = DB::table('lawyers')->insertGetId($insert);

        return response()->json([
            'data' => [
                'id' => $id,
                'name' => $insert[$nameColumn],
                'phone' => $insert['phone'] ?? null,
                'email' => $insert['email'] ?? null,
                'comment' => $commentVal,
                'is_cooperating' => (bool) ($insert['is_cooperating'] ?? 1),
            ],
        ], 201);
    }

    private function normalizeForResponse(Application $application): array
    {
        $data = $application->toArray();

        // 1. Форматирование дат (Y-m-d)
        foreach (self::DATE_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = $this->dateOnly($data[$field]);
            }
        }

        // 2. Форматирование даты и времени для <input type="datetime-local">
        foreach (self::DATETIME_FIELDS as $field) {
            if (!empty($data[$field])) {
                $data[$field] = str_replace(' ', 'T', substr((string) $data[$field], 0, 16));
            } else {
                $data[$field] = null;
            }
        }

        // 3. Извлекаем чистый числовой ID филиала напрямую из атрибутов БД
        // (чтобы связанный объект ourCompany не подменял числовое значение)
        $rawBranch = $application->getRawOriginal('our_company')
            ?? $application->getRawOriginal('our_company_id')
            ?? (is_numeric($data['our_company'] ?? null) ? $data['our_company'] : null)
            ?? (is_numeric($data['our_company_id'] ?? null) ? $data['our_company_id'] : null);

        $branchId = is_numeric($rawBranch) ? (int) $rawBranch : null;

        $data['our_company'] = $branchId;
        $data['our_company_id'] = $branchId;

        return $data;
    }

    private function dateOnly(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return substr((string) $value, 0, 10);
    }

    private function normalizePhone(?string $value, string $field): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if (strlen($digits) === 10) {
            $digits = '7' . $digits;
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '8')) {
            $digits = '7' . substr($digits, 1);
        }

        if (!preg_match('/^7\d{10}$/', $digits)) {
            throw ValidationException::withMessages([
                $field => ['Введите телефон в формате +7 (999) 123-45-67'],
            ]);
        }

        return sprintf(
            '+7 (%s) %s-%s-%s',
            substr($digits, 1, 3),
            substr($digits, 4, 3),
            substr($digits, 7, 2),
            substr($digits, 9, 2)
        );
    }

    private function addWorkingDays(string $date, int $days): string
    {
        $result = Carbon::parse($date)->startOfDay();
        $added = 0;

        while ($added < $days) {
            $result->addDay();

            if (!$result->isWeekend()) {
                $added++;
            }
        }

        return $result->toDateString();
    }

    private function tableOptions(?string $table, array $extraFields = []): array
    {
        if (!$table || !Schema::hasTable($table)) {
            return [];
        }

        $columns = Schema::getColumnListing($table);
        $nameColumn = $this->firstExistingColumn(
            $columns,
            ['name', 'title', 'Name']
        );

        if (!$nameColumn || !in_array('id', $columns, true)) {
            return [];
        }

        $select = ['id', $nameColumn . ' as name'];

        foreach ($extraFields as $field) {
            if (in_array($field, $columns, true)) {
                $select[] = $field;
            }
        }

        return DB::table($table)
            ->select($select)
            ->orderBy($nameColumn)
            ->get()
            ->map(static fn($row) => (array) $row)
            ->values()
            ->all();
    }

    private function staffByRole(int $roleId, ?int $branchId): array
    {
        $pivotTable = 'our_company_user_role';

        if (!Schema::hasTable($pivotTable) || !Schema::hasTable('users')) {
            return [];
        }

        $query = DB::table('users')
            ->join($pivotTable, 'users.id', '=', "{$pivotTable}.user_id")
            ->select([
                'users.id',
                'users.last_name',
                'users.name',
                'users.patronymic'
            ])
            ->where("{$pivotTable}.user_group_id", $roleId);

        if ($branchId) {
            $query->where("{$pivotTable}.our_company_id", $branchId);
        }

        return $query
            ->distinct()
            ->orderBy('users.last_name')
            ->get()
            ->map(function ($row) {
                $fio = trim($row->last_name . ' ' . $row->name . ' ' . $row->patronymic);
                return [
                    'id' => (int) $row->id,
                    'name' => $fio ?: ($row->name ?: 'Без имени')
                ];
            })
            ->values()
            ->all();
    }

    private function firstExistingColumn(array $columns, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (in_array($candidate, $columns, true)) {
                return $candidate;
            }
        }

        return null;
    }
}
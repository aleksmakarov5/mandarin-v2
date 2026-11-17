<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ApplicationGeneralController extends Controller
{
    private const DATE_FIELDS = [
        'birthday',
        'ClientPasport_date',
        'DTPdate',
        'napr_date',
        'view_date',
        'start_date',
        'sp_date',
        'end_date',
    ];

    private const PHONE_FIELDS = [
        'ClientPhone',
        'pred_phone',
    ];


    public function show(int $id): JsonResponse
    {
        $application = Application::query()->findOrFail($id);

        return response()->json([
            'data' => $this->normalizeForResponse($application),
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

        $columns = Schema::getColumnListing($application->getTable());

        // Сохраняем старое правило: дата окончания — start_date + 30 рабочих дней.
        if (array_key_exists('start_date', $data) && in_array('end_date', $columns, true)) {
            $data['end_date'] = $data['start_date']
                ? $this->addWorkingDays($data['start_date'], 30)
                : null;
        }

        $unknownFields = array_values(array_diff(array_keys($data), $columns));

        if ($unknownFields) {
            throw ValidationException::withMessages([
                'schema' => [
                    'В таблице applications отсутствуют колонки: ' . implode(', ', $unknownFields),
                ],
            ]);
        }

        DB::transaction(function () use ($application, $data): void {
            // Данные уже прошли строгий whitelist и проверку колонок.
            \Log::info('Saving view_date:', ['value' => $data['view_date'] ?? 'NOT SET']);
            // forceFill исключает тихую потерю полей из-за незаполненного $fillable.
            $application->forceFill($data)->save();
        });

        return response()->json([
            'message' => 'Основные данные сохранены',
            'saved_fields' => array_keys($data),
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

        // Точная таблица статусов
        $statusTable = Schema::hasTable('application_statuses')
            ? 'application_statuses'
            : (Schema::hasTable('statuses') ? 'statuses' : null);

        // Загружаем основные статусы дел с полями цвета и таймера
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

        // Загружаем финансовые статусы (если есть таблица)
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
                    // Если bg_color еще не заполнен, берем старый color, иначе дефолтный синий
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
            'insurance_companies' => $this->tableOptions('insurance_companies'),
            'services' => $this->tableOptions($serviceTable),
        ]);
    }
    public function branchEmployees(Request $request): JsonResponse
    {
        $branchId = $request->filled('branch_id')
            ? (int) $request->input('branch_id')
            : null;

        return response()->json([
            // МРК — роль 5, наставник — роль 6, запчастист — роль 9.
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

        foreach (['phone', 'email', 'comment', 'is_cooperating'] as $field) {
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
            'is_cooperating' => ['nullable', 'boolean'], // <--- Добавь это
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
            $insert['is_cooperating'] = (int) ($validated['is_cooperating'] ?? 0);
        }

        foreach (['email', 'comment'] as $field) {
            if (in_array($field, $columns, true)) {
                $insert[$field] = $validated[$field] ?? null;
            }
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
                'comment' => $insert['comment'] ?? null,
                'is_cooperating' => (bool) ($insert['is_cooperating'] ?? 0),
            ],
        ], 201);
    }

    private function normalizeForResponse(Application $application): array
    {
        $data = $application->toArray();

        foreach (self::DATE_FIELDS as $field) {
            if (array_key_exists($field, $data) && $field !== 'view_date') {
                $data[$field] = $this->dateOnly($data[$field]);
            }
        }

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

        // 1. Просто выбираем нужные колонки
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

        // 2. Получаем результат и склеиваем в PHP
        return $query
            ->distinct()
            ->orderBy('users.last_name')
            ->get()
            ->map(function ($row) {
                // Склеиваем ФИО прямо здесь
                $fio = trim($row->last_name . ' ' . $row->name . ' ' . $row->patronymic);
                return [
                    'id' => (int) $row->id,
                    'name' => $fio ?: 'Без имени' // Если пустое - напишет "Без имени"
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
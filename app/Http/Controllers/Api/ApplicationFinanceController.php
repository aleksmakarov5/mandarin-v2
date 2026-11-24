<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Estimate;
use App\Services\ApplicationFinanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Models\User;

class ApplicationFinanceController extends Controller
{
    public function __construct(protected ApplicationFinanceService $financeService)
    {
    }

    // Получить полную сводку для шапки
    public function summary(
        int $id,
        Request $request
    ): JsonResponse {
        $application = Application::findOrFail($id);

        Gate::authorize('viewFinance', $application);

        return $this->financeResponse(
            $application,
            $request
        );
    }

    /**
     * Устанавливаем конкретный режим, а не слепо инвертируем значение.
     *
     * included = сумма уже включает НДС
     * added    = к сумме нужно прибавить НДС
     */
    public function updateVatMode(
        int $id,
        Request $request
    ): JsonResponse {
        $validated = $request->validate([
            'vat_mode' => [
                'required',
                'in:added,included',
            ],
        ]);

        $application = DB::transaction(function () use ($id, $validated) {
            $application = Application::query()
                ->lockForUpdate()
                ->findOrFail($id);

            Gate::authorize('viewFinance', $application);
            Gate::authorize('changeFinanceVat', $application);

            /*
             * Только меняем режим НДС.
             * Суммы estimate здесь не обновляются.
             */
            $application->nds_mode =
                $validated['vat_mode'] === 'added'
                ? 1
                : 0;

            $application->save();

            return $application->fresh();
        });

        return $this->financeResponse(
            $application,
            $request
        );
    }

    /**
     * Единый ответ для загрузки и изменения режима.
     */
    private function financeResponse(
        Application $application,
        Request $request
    ): JsonResponse {
        $result = $this->financeService->calculate(
            $application->fresh(),
            $request->user()
        );

        $summary = is_array($result['data'] ?? null)
            ? $result['data']
            : $result;

        $summary['permissions'] = array_merge(
            $summary['permissions'] ?? [],
            [
                'view_finance' => Gate::allows(
                    'viewFinance',
                    $application
                ),

                'edit_vat' => Gate::allows(
                    'changeFinanceVat',
                    $application
                ),

                'edit_approved' => Gate::allows(
                    'changeFinanceApproved',
                    $application
                ),

                'edit_cash' => Gate::allows(
                    'changeFinanceCash',
                    $application
                ),

                'manage_salary_rates' => Gate::allows(
                    'manageFinanceSalaryRates',
                    $application
                ),
            ]
        );

        return response()
            ->json([
                'data' => $summary,
                'warnings' => $result['warnings'] ?? [],
            ])
            ->header('Cache-Control', 'private, no-store');
    }

    // Переключение режима НДС (включая / плюс)
    public function toggleNdsMode(int $id): JsonResponse
    {
        $app = Application::findOrFail($id);
        $app->nds_mode = ($app->nds_mode == 1) ? 0 : 1;
        $app->save();

        return response()->json([
            'success' => true,
            'nds_mode' => $app->nds_mode,
            'summary' => $this->financeService->calculate($app),
        ]);
    }

    // Быстрое редактирование суммы СК (на лету)
    public function updateApprovedSum(
        int $id,
        Request $request
    ): JsonResponse {
        $application = Application::findOrFail($id);

        Gate::authorize('viewFinance', $application);
        Gate::authorize('changeFinanceApproved', $application);

        /*
         * Запрос содержит целые копейки, как и финансовая сводка.
         * repair и defect обязательны только в раздельном режиме.
         */
        $validated = $request->validate([
            'entered' => [
                'required',
                'integer',
                'min:0',
                'max:999999999999',
            ],
            'repair' => [
                'sometimes',
                'required',
                'integer',
                'min:0',
                'max:999999999999',
            ],
            'defect' => [
                'sometimes',
                'required',
                'integer',
                'min:0',
                'max:999999999999',
            ],
        ]);

        DB::transaction(function () use ($application, $validated) {
            $lockedApplication = Application::query()
                ->whereKey($application->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Повторная проверка после получения блокировки дела.
            Gate::authorize(
                'changeFinanceApproved',
                $lockedApplication
            );

            $lockedApplication->load('insuranceCompany');

            $isSplit = (bool) (
                $lockedApplication->insuranceCompany?->split_ic_total
                ?? false
            );

            $isCasco = (int) $lockedApplication->ub_type === 1;

            $estimate = Estimate::firstOrCreate([
                'application_id' => $lockedApplication->id,
            ]);

            $estimate = Estimate::query()
                ->whereKey($estimate->id)
                ->lockForUpdate()
                ->firstOrFail();

            $total = (int) $validated['entered'];

            if ($isSplit) {
                if (
                    !array_key_exists('repair', $validated)
                    || !array_key_exists('defect', $validated)
                ) {
                    throw ValidationException::withMessages([
                        'entered' =>
                            'Для этой страховой укажите ремонт и дефектовку.',
                    ]);
                }

                $repair = (int) $validated['repair'];
                $defect = (int) $validated['defect'];

                if ($total !== $repair + $defect) {
                    throw ValidationException::withMessages([
                        'entered' =>
                            'Итог должен быть равен ремонту плюс дефектовка.',
                    ]);
                }

                $estimate->ic_repair_sum = $this->financeRubles($repair);
                $estimate->ic_defect_sum = $this->financeRubles($defect);

                /*
                 * Итоговое поле калькуляции синхронизируется
                 * с введённой согласованной суммой.
                 */
                $field = $isCasco
                    ? 'price_repair_half'
                    : 'price_repair_full';

                $estimate->{$field} = $this->financeRubles($total);
            } else {
                if (
                    array_key_exists('repair', $validated)
                    || array_key_exists('defect', $validated)
                ) {
                    throw ValidationException::withMessages([
                        'entered' =>
                            'У этой страховой используется единая сумма. Обновите сводку.',
                    ]);
                }

                /*
                 * Текущий сборщик в обычном режиме считает:
                 * итог калькуляции + add_pay.
                 *
                 * Поэтому из введённого общего итога вычитаем
                 * существующую доплату при сохранении базы.
                 * Саму доплату здесь не меняем.
                 */
                $addPay = $this->financeKopecks(
                    $estimate->getRawOriginal('add_pay') ?? '0'
                );

                if ($total < $addPay) {
                    throw ValidationException::withMessages([
                        'entered' =>
                            'Итоговая сумма не может быть меньше существующей доплаты.',
                    ]);
                }

                $field = $isCasco
                    ? 'price_repair_half'
                    : 'price_repair_full';

                $estimate->{$field} = $this->financeRubles(
                    $total - $addPay
                );
            }

            $estimate->save();
        });

        return $this->financeResponse(
            Application::findOrFail($id),
            $request
        );
    }

    private function financeRubles(int $kopecks): string
    {
        return intdiv($kopecks, 100)
            . '.'
            . str_pad(
                (string) ($kopecks % 100),
                2,
                '0',
                STR_PAD_LEFT
            );
    }

    private function financeKopecks(mixed $rubles): int
    {
        $text = trim((string) $rubles);

        if (!preg_match('/^\d+(?:\.\d{1,2})?$/D', $text)) {
            throw ValidationException::withMessages([
                'entered' =>
                    'В смете некорректно заполнена сумма доплаты.',
            ]);
        }

        [$whole, $fraction] = array_pad(
            explode('.', $text, 2),
            2,
            ''
        );

        return (int) $whole * 100
            + (int) str_pad($fraction, 2, '0');
    }

    // Обновление параметров ДДС (доход СК, даты, факт. расчет СТО)
    public function updateDds(
        int $id,
        Request $request
    ): JsonResponse {
        $application = Application::findOrFail($id);

        Gate::authorize('viewFinance', $application);
        Gate::authorize('changeFinanceCash', $application);

        /*
         * Все суммы запроса — целые копейки.
         * is_split передаётся для защиты от изменения настройки
         * страховой, пока окно редактирования открыто.
         */
        $validated = $request->validate([
            'is_split' => ['required', 'boolean'],

            'income' => [
                'sometimes',
                'required',
                'integer',
                'min:0',
                'max:999999999999',
            ],
            'income_date' => [
                'sometimes',
                'nullable',
                'date_format:Y-m-d',
            ],

            'repair_income' => [
                'sometimes',
                'required',
                'integer',
                'min:0',
                'max:999999999999',
            ],
            'repair_income_date' => [
                'sometimes',
                'nullable',
                'date_format:Y-m-d',
            ],

            'defect_income' => [
                'sometimes',
                'required',
                'integer',
                'min:0',
                'max:999999999999',
            ],
            'defect_income_date' => [
                'sometimes',
                'nullable',
                'date_format:Y-m-d',
            ],

            'sto_payment_date' => [
                'sometimes',
                'nullable',
                'date_format:Y-m-d',
            ],
        ]);

        DB::transaction(function () use ($id, $validated) {
            $application = Application::query()
                ->lockForUpdate()
                ->findOrFail($id);

            Gate::authorize('viewFinance', $application);
            Gate::authorize('changeFinanceCash', $application);

            $application->load('insuranceCompany');

            $isSplit = (bool) (
                $application->insuranceCompany?->split_ic_total
                ?? false
            );

            if ((bool) $validated['is_split'] !== $isSplit) {
                throw ValidationException::withMessages([
                    'is_split' =>
                        'Режим страховой изменился. Обновите сводку и откройте ДДС заново.',
                ]);
            }

            $inactiveFields = $isSplit
                ? ['income', 'income_date']
                : [
                    'repair_income',
                    'repair_income_date',
                    'defect_income',
                    'defect_income_date',
                ];

            foreach ($inactiveFields as $field) {
                if (array_key_exists($field, $validated)) {
                    throw ValidationException::withMessages([
                        $field => 'Поле недоступно в текущем режиме страховой.',
                    ]);
                }
            }

            $estimates = Estimate::query()
                ->where('application_id', $id)
                ->lockForUpdate()
                ->limit(2)
                ->get();

            if ($estimates->count() > 1) {
                throw ValidationException::withMessages([
                    'income' =>
                        'У дела несколько записей сметы. Сначала необходимо устранить дубли.',
                ]);
            }

            $estimate = $estimates->first();

            if (!$estimate) {
                $estimate = new Estimate();
                $estimate->application_id = $id;
            }

            $pairs = $isSplit
                ? [
                    [
                        'repair_income',
                        'repair_income_date',
                        'dds_repair_income_sum',
                        'dds_repair_income_date',
                    ],
                    [
                        'defect_income',
                        'defect_income_date',
                        'dds_defect_income_sum',
                        'dds_defect_income_date',
                    ],
                ]
                : [
                    [
                        'income',
                        'income_date',
                        'dds_income_sum',
                        'dds_income_date',
                    ],
                ];

            foreach ($pairs as [
                $amountKey,
                $dateKey,
                $amountColumn,
                $dateColumn
            ]) {
                $amountChanged = array_key_exists($amountKey, $validated);
                $dateChanged = array_key_exists($dateKey, $validated);

                /*
                 * Изменение одной даты оплаты СТО не переписывает
                 * и не обнуляет поступления.
                 */
                if (!$amountChanged && !$dateChanged) {
                    continue;
                }

                $amount = $amountChanged
                    ? (int) $validated[$amountKey]
                    : $this->cashKopecks(
                        $estimate->getRawOriginal($amountColumn) ?? '0'
                    );

                $date = $dateChanged
                    ? $validated[$dateKey]
                    : $estimate->getRawOriginal($dateColumn);

                if ($amount > 0 && !$date) {
                    throw ValidationException::withMessages([
                        $dateKey =>
                            'Для положительной суммы укажите дату поступления.',
                    ]);
                }

                if ($amountChanged) {
                    $estimate->{$amountColumn} = $this->financeRubles($amount);
                }

                if ($dateChanged) {
                    $estimate->{$dateColumn} = $date;
                }
            }

            if (array_key_exists('sto_payment_date', $validated)) {
                $estimate->dds_sto_pay_date =
                    $validated['sto_payment_date'];

                /*
                 * Сохраняем прежнюю синхронизацию с карточкой дела.
                 * В финансовой сводке источником будет estimates.
                 */
                $application->sto_pay_date =
                    $validated['sto_payment_date'];

                $application->save();
            }

            $estimate->save();
        });

        return $this->financeResponse(
            Application::findOrFail($id),
            $request
        );
    }

    private function cashKopecks(mixed $value): int
    {
        $text = trim((string) $value);

        if (!preg_match('/^\d+(?:\.\d{1,2})?$/D', $text)) {
            throw ValidationException::withMessages([
                'income' => 'В БД некорректно заполнена сумма поступления.',
            ]);
        }

        [$whole, $fraction] = array_pad(
            explode('.', $text, 2),
            2,
            ''
        );

        return (int) $whole * 100
            + (int) str_pad($fraction, 2, '0');
    }

    // Установка процентов ЗП для дела (только владелец / админ)
    public function updateZpPercentages(
        int $id,
        Request $request
    ): JsonResponse {
        $application = Application::findOrFail($id);

        Gate::authorize('viewFinance', $application);
        Gate::authorize('manageFinanceSalaryRates', $application);

        /*
         * API: целые сотые доли процента.
         * null — стандартное правило.
         * 0 — индивидуальная ставка 0%.
         */
        $validated = $request->validate([
            'mrk_proc' => [
                'present',
                'nullable',
                'integer',
                'min:0',
                'max:10000',
            ],
            'mentor_proc' => [
                'present',
                'nullable',
                'integer',
                'min:0',
                'max:10000',
            ],
        ]);

        DB::transaction(function () use ($id, $validated) {
            $application = Application::query()
                ->lockForUpdate()
                ->findOrFail($id);

            Gate::authorize('viewFinance', $application);
            Gate::authorize('manageFinanceSalaryRates', $application);

            $application->mrk_proc_custom =
                $validated['mrk_proc'] !== null
                ? $this->financeRubles(
                    (int) $validated['mrk_proc']
                )
                : null;

            $application->mentor_proc_custom =
                $validated['mentor_proc'] !== null
                ? $this->financeRubles(
                    (int) $validated['mentor_proc']
                )
                : null;

            // Не обновляем updated_at при отсутствии изменений.
            if (
                $application->isDirty([
                    'mrk_proc_custom',
                    'mentor_proc_custom',
                ])
            ) {
                $application->save();
            }
        });

        return $this->financeResponse(
            Application::findOrFail($id),
            $request
        );
    }

    public function salaryRates(
        int $id,
        Request $request
    ): JsonResponse {
        $application = Application::findOrFail($id);

        Gate::authorize('viewFinance', $application);
        Gate::authorize('manageFinanceSalaryRates', $application);

        $mrkId = (int) $application->id_MRK;

        $mentorId = (int) (
            $application->id_mentor
            ?? $application->mentor_id
            ?? 0
        );

        $users = User::query()
            ->whereIn('id', array_filter([$mrkId, $mentorId]))
            ->get()
            ->keyBy('id');

        $mrk = $users->get($mrkId);
        $mentor = $users->get($mentorId);

        $employeeData = function (?User $employee, mixed $customRate, mixed $defaultRate): ?array {
            if (!$employee) {
                return null;
            }

            $name = trim(implode(' ', array_filter([
                $employee->last_name,
                $employee->name,
                $employee->patronymic ?? $employee->middle_name,
            ])));

            return [
                'id' => (int) $employee->id,
                'name' => $name !== ''
                    ? $name
                    : "Сотрудник #{$employee->id}",

                // Сотые доли процента: 1400 = 14%.
                'custom_rate' => $customRate !== null
                    ? $this->financeKopecks($customRate)
                    : null,

                'default_rate' => $defaultRate !== null
                    ? $this->financeKopecks($defaultRate)
                    : null,
            ];
        };

        return response()->json([
            'data' => [
                'application_id' => (int) $application->id,

                'mrk' => $employeeData(
                    $mrk,
                    $application->mrk_proc_custom,
                    $mentorId > 0
                    ? $mrk?->def_mrk_proc
                    : '14.00'
                ),

                'mentor' => $employeeData(
                    $mentor,
                    $application->mentor_proc_custom,
                    $mentor?->def_mrk_proc
                ),
            ],
        ])->header('Cache-Control', 'private, no-store');
    }

}
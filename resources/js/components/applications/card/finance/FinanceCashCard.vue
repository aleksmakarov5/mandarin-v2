<template>
    <FinanceTooltip class="cash-card" :class="{ 'cash-card-expanded': expanded }" :aria-busy="saving"
        heading="Движение денежных средств" :rows="tooltipRows"
        note="Прибыль = доход − расходы ДДС − НДС − налог с дохода. Процент прибыли рассчитывается от фактического дохода. Работы включаются в расход ДДС при наличии даты оплаты СТО."
        @click="handleCardClick">
        <!-- Заголовок -->
        <div class="cash-heading">
            <button type="button" class="cash-toggle" :aria-expanded="expanded" :disabled="busy || saving"
                @click.stop="expanded = !expanded">
                <strong>ДДС</strong>

                <span aria-hidden="true">
                    {{ expanded ? '⌃' : '⌄' }}
                </span>
            </button>

            <span class="legal-name">
                {{ summary.legal_entity?.name || '' }}
            </span>

            <button v-if="editable" type="button" class="cash-edit"
                aria-label="Редактировать поступления и дату оплаты СТО" :disabled="busy || saving"
                @click.stop="openModal">
                ✎
            </button>
        </div>

        <!-- Компактный режим -->
        <div v-if="!expanded" class="cash-compact-grid">
            <div>
                <span>Доход</span>

                <b class="cash-income">
                    {{ money(cash.income) }}
                </b>
            </div>

            <div>
                <span>Расход</span>

                <b class="cash-expense">
                    {{ money(cash.expense) }}
                </b>
            </div>
        </div>

        <!-- Полный режим -->
        <template v-else>
            <template v-if="cash.is_split">
                <div class="income-row">
                    <span>Ремонт</span>

                    <b class="cash-income">
                        {{ money(cash.repair_income) }}
                    </b>

                    <small>
                        {{ date(cash.repair_income_date) }}
                    </small>
                </div>

                <div class="income-row">
                    <span>Дефектовка</span>

                    <b class="cash-income">
                        {{ money(cash.defect_income) }}
                    </b>

                    <small>
                        {{ date(cash.defect_income_date) }}
                    </small>
                </div>

                <div class="cash-row income-total">
                    <span>Всего доход</span>

                    <b class="cash-income">
                        {{ money(cash.income) }}
                    </b>
                </div>
            </template>

            <div v-else class="income-row">
                <span>Доход СК</span>

                <b class="cash-income">
                    {{ money(cash.income) }}
                </b>

                <small>
                    {{ date(cash.income_date) }}
                </small>
            </div>

            <div class="cash-row">
                <span>Расход</span>

                <b class="cash-expense">
                    {{ money(cash.expense) }}
                </b>
            </div>

            <div class="cash-row muted">
                <span>Дата оплаты СТО</span>
                <span>{{ date(cash.sto_payment_date) }}</span>
            </div>

            <div class="cash-taxes">
                <span v-if="showVat">
                    НДС {{ money(cash.vat) }}
                </span>

                <span>
                    Налог {{ money(cash.income_tax) }}
                </span>
            </div>
        </template>

        <!-- Прибыль видна в обоих режимах -->
        <FinanceDifference class="cash-profit-indicator" label="Факт. прибыль" :value="cash.profit"
            :base="cash.income" />
    </FinanceTooltip>

    <!-- Редактирование ДДС -->
    <Teleport to="body">
        <div v-if="opened" class="cash-overlay" @click.self="closeModal" @keydown.esc.stop.prevent="closeModal">
            <form ref="dialog" class="cash-modal" role="dialog" aria-modal="true" :aria-labelledby="modalHeadingId"
                :aria-busy="saving" tabindex="-1" @submit.prevent="submit" @keydown.tab="trapFocus">
                <div class="modal-heading">
                    <h3 :id="modalHeadingId">
                        Поступления и оплата СТО
                    </h3>

                    <button type="button" class="close-button" :disabled="saving" aria-label="Закрыть"
                        @click="closeModal">
                        ×
                    </button>
                </div>

                <div class="modal-body">
                    <!-- Два поступления -->
                    <template v-if="form.is_split">
                        <div class="payment-title">
                            Поступление за ремонт
                        </div>

                        <div class="payment-fields">
                            <label>
                                Сумма, руб.

                                <input v-model="form.repair" type="text" inputmode="decimal" :disabled="saving"
                                    autocomplete="off" />
                            </label>

                            <label>
                                Дата поступления

                                <input v-model="form.repair_date" type="date" :disabled="saving" />
                            </label>
                        </div>

                        <div class="payment-title">
                            Поступление за дефектовку
                        </div>

                        <div class="payment-fields">
                            <label>
                                Сумма, руб.

                                <input v-model="form.defect" type="text" inputmode="decimal" :disabled="saving"
                                    autocomplete="off" />
                            </label>

                            <label>
                                Дата поступления

                                <input v-model="form.defect_date" type="date" :disabled="saving" />
                            </label>
                        </div>
                    </template>

                    <!-- Единое поступление -->
                    <template v-else>
                        <div class="payment-title">
                            Поступление от СК
                        </div>

                        <div class="payment-fields">
                            <label>
                                Сумма, руб.

                                <input v-model="form.income" type="text" inputmode="decimal" :disabled="saving"
                                    autocomplete="off" />
                            </label>

                            <label>
                                Дата поступления

                                <input v-model="form.income_date" type="date" :disabled="saving" />
                            </label>
                        </div>
                    </template>

                    <label class="sto-date">
                        Дата оплаты СТО

                        <input v-model="form.sto_date" type="date" :disabled="saving" />
                    </label>

                    <p class="form-note">
                        Пустая дата оплаты СТО исключает фактические
                        работы из расхода ДДС. Стоимость самих работ
                        не изменяется.
                    </p>

                    <div v-if="localError || error" class="cash-error" role="alert">
                        {{ localError || error }}
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" :disabled="saving" @click="closeModal">
                        Отмена
                    </button>

                    <button type="submit" class="submit-button" :disabled="busy || saving">
                        {{ saving ? 'Сохранение…' : 'Сохранить' }}
                    </button>
                </div>
            </form>
        </div>
    </Teleport>
</template>

<script setup>
import {
    computed,
    nextTick,
    onBeforeUnmount,
    reactive,
    ref,
    watch,
} from 'vue';

import FinanceTooltip from './FinanceTooltip.vue';
import FinanceDifference from './FinanceDifference.vue';

import {
    date,
    isAvailable,
    money,
    percent,
} from './financeFormatters';

const props = defineProps({
    summary: {
        type: Object,
        required: true,
    },

    saving: {
        type: Boolean,
        default: false,
    },

    busy: {
        type: Boolean,
        default: false,
    },

    error: {
        type: String,
        default: '',
    },

    /*
     * Асинхронное сохранение из useApplicationFinance.
     * Возвращает true после успешного сохранения
     * и получения обновлённой сводки.
     */
    saveCash: {
        type: Function,
        required: true,
    },
});

const cash = computed(() => props.summary.cash ?? {});

const editable = computed(() => {
    return props.summary.permissions?.edit_cash === true;
});

const showVat = computed(() => {
    return isAvailable(cash.value.vat_rate)
        && Number(cash.value.vat_rate) > 0;
});

const expanded = ref(false);
const opened = ref(false);
const dialog = ref(null);
const localError = ref('');

const modalHeadingId = `cash-modal-${Math.random()
    .toString(36)
    .slice(2)}`;

let previousFocus = null;
let formVersion = 0;
let destroyed = false;

const form = reactive({
    is_split: false,

    income: '',
    income_date: '',

    repair: '',
    repair_date: '',

    defect: '',
    defect_date: '',

    sto_date: '',
});

function handleCardClick(event) {
    if (
        event.target.closest(
            'button, input, textarea, select, a, [contenteditable], [role="textbox"]'
        )
    ) {
        return;
    }

    // Выделение текста не должно сворачивать плитку.
    if (window.getSelection()?.toString()) return;

    if (props.busy || props.saving) return;

    expanded.value = !expanded.value;
}

function inputMoney(value) {
    if (!isAvailable(value)) return '';

    const amount = Number(value);

    if (amount < 0) return '';

    return `${Math.floor(amount / 100)}.${String(amount % 100)
        .padStart(2, '0')}`;
}

function inputDate(value) {
    if (!value) return '';

    const match = String(value).match(/^\d{4}-\d{2}-\d{2}/);

    return match ? match[0] : '';
}

async function openModal() {
    if (!editable.value || props.busy || props.saving) return;

    const version = ++formVersion;

    previousFocus = document.activeElement;

    Object.assign(form, {
        is_split: cash.value.is_split === true,

        income: inputMoney(cash.value.income),
        income_date: inputDate(cash.value.income_date),

        repair: inputMoney(cash.value.repair_income),
        repair_date: inputDate(cash.value.repair_income_date),

        defect: inputMoney(cash.value.defect_income),
        defect_date: inputDate(cash.value.defect_income_date),

        sto_date: inputDate(cash.value.sto_payment_date),
    });

    localError.value = '';
    opened.value = true;

    await nextTick();

    if (
        !destroyed
        && version === formVersion
        && opened.value
    ) {
        dialog.value?.querySelector('input')?.focus();
    }
}

function restoreFocus() {
    if (
        previousFocus instanceof HTMLElement
        && previousFocus.isConnected
    ) {
        previousFocus.focus();
    }
}

function closeModal() {
    if (props.saving) return;

    formVersion += 1;
    opened.value = false;

    restoreFocus();
}

function parseAmount(value, label) {
    const text = String(value)
        .replace(/[\s\u00a0\u202f]/g, '')
        .replace(',', '.');

    if (!/^\d+(?:\.\d{1,2})?$/.test(text)) {
        throw new Error(
            `${label}: укажите сумму от нуля с точностью до копеек.`
        );
    }

    const [whole, fraction = ''] = text.split('.');

    const result = Number(whole) * 100
        + Number(fraction.padEnd(2, '0'));

    if (
        !Number.isSafeInteger(result)
        || result > 999999999999
    ) {
        throw new Error(`${label}: сумма слишком большая.`);
    }

    return result;
}

function checkDate(amount, value, label) {
    if (amount > 0 && !value) {
        throw new Error(`${label}: укажите дату поступления.`);
    }
}

async function submit() {
    if (
        props.busy
        || props.saving
        || !editable.value
    ) {
        return;
    }

    localError.value = '';

    const version = formVersion;

    try {
        const payload = {
            is_split: form.is_split,
            sto_payment_date: form.sto_date || null,
        };

        if (form.is_split) {
            payload.repair_income = parseAmount(
                form.repair,
                'Ремонт'
            );

            payload.repair_income_date =
                form.repair_date || null;

            payload.defect_income = parseAmount(
                form.defect,
                'Дефектовка'
            );

            payload.defect_income_date =
                form.defect_date || null;

            checkDate(
                payload.repair_income,
                form.repair_date,
                'Ремонт'
            );

            checkDate(
                payload.defect_income,
                form.defect_date,
                'Дефектовка'
            );
        } else {
            payload.income = parseAmount(
                form.income,
                'Доход СК'
            );

            payload.income_date =
                form.income_date || null;

            checkDate(
                payload.income,
                form.income_date,
                'Доход СК'
            );
        }

        const saved = await props.saveCash(payload);

        /*
         * Не закрываем другое окно, если пользователь
         * уже перешёл к другому делу.
         */
        if (
            destroyed
            || version !== formVersion
            || !opened.value
        ) {
            return;
        }

        if (saved) {
            formVersion += 1;
            opened.value = false;

            restoreFocus();
        }
    } catch (error) {
        if (destroyed || version !== formVersion) return;

        localError.value =
            error.message || 'Не удалось сохранить ДДС.';
    }
}

function trapFocus(event) {
    const elements = Array.from(
        dialog.value?.querySelectorAll(
            'button:not(:disabled), input:not(:disabled), [tabindex="0"]'
        ) ?? []
    );

    if (!elements.length) {
        event.preventDefault();
        dialog.value?.focus();
        return;
    }

    const first = elements[0];
    const last = elements[elements.length - 1];
    const active = document.activeElement;

    if (!dialog.value?.contains(active) || active === dialog.value) {
        event.preventDefault();

        (event.shiftKey ? last : first).focus();
        return;
    }

    if (event.shiftKey && active === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && active === last) {
        event.preventDefault();
        first.focus();
    }
}

watch(
    () => props.summary.application_id,
    () => {
        formVersion += 1;
        opened.value = false;
        expanded.value = false;
        localError.value = '';
    }
);

watch(editable, value => {
    if (!value) {
        formVersion += 1;

        const wasOpened = opened.value;
        opened.value = false;

        if (wasOpened) restoreFocus();
    }
});

onBeforeUnmount(() => {
    destroyed = true;
    formVersion += 1;

    if (opened.value) restoreFocus();
});

const tooltipRows = computed(() => {
    const c = cash.value;

    let profitPercent = '—';

    if (
        isAvailable(c.profit)
        && isAvailable(c.income)
        && Number(c.income) > 0
    ) {
        profitPercent = new Intl.NumberFormat('ru-RU', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(
            Number(c.profit) / Number(c.income) * 100
        ) + '%';
    }

    return [
        {
            label: 'Юридическое лицо',
            value: props.summary.legal_entity?.name || '—',
        },

        ...(c.is_split
            ? [
                {
                    label: 'Поступление за ремонт',
                    value: money(c.repair_income),
                },
                {
                    label: 'Дата за ремонт',
                    value: date(c.repair_income_date),
                },
                {
                    label: 'Поступление за дефектовку',
                    value: money(c.defect_income),
                },
                {
                    label: 'Дата за дефектовку',
                    value: date(c.defect_income_date),
                },
            ]
            : [
                {
                    label: 'Дата поступления',
                    value: date(c.income_date),
                },
            ]),

        {
            label: 'Всего доход',
            value: money(c.income),
        },
        {
            label: 'Заказанные запчасти',
            value: money(c.ordered_parts),
        },
        {
            label: 'Дополнительные расходы',
            value: money(c.extra_expenses),
        },
        {
            label: 'Оплаченные работы',
            value: money(c.paid_works),
        },
        {
            label: 'Дата оплаты СТО',
            value: date(c.sto_payment_date),
        },
        {
            label: 'Всего расход ДДС',
            value: money(c.expense),
        },
        {
            label: `НДС ${percent(c.vat_rate)}%`,
            value: money(c.vat),
        },
        {
            label: `Налог с дохода ${percent(c.income_tax_rate)}%`,
            value: money(c.income_tax),
        },
        {
            label: 'Фактическая прибыль',
            value: money(c.profit),
            total: true,
        },
        {
            label: 'Прибыль от дохода',
            value: profitPercent,
        },
    ];
});
</script>

<style scoped>
/* =========================================================
   ПЛИТКА ДДС
   ========================================================= */

.cash-card {
    display: flex;
    flex: 0 0 auto;
    flex-direction: column;
    align-self: stretch;
    box-sizing: border-box;
    width: 255px;
    min-width: 255px;
    padding: 4px 6px;
    gap: 4px;
    border: 1px solid #bae6fd;
    border-radius: 5px;
    background: #f0f9ff;
    font-variant-numeric: tabular-nums;
    cursor: pointer;
}

.cash-card-expanded {
    width: 325px;
    min-width: 325px;
}

.cash-heading {
    display: flex;
    align-items: center;
    gap: 6px;
    min-width: 0;
    color: #475569;
    font-size: 9px;
}

.cash-toggle {
    display: inline-flex;
    align-items: center;
    flex-shrink: 0;
    gap: 7px;
    padding: 0;
    border: 0;
    background: transparent;
    color: #475569;
    font: inherit;
    font-size: 9px;
    cursor: pointer;
}

.cash-toggle>span {
    color: #64748b;
    font-size: 12px;
}

.legal-name {
    flex: 1 1 auto;
    min-width: 0;
    margin-left: auto;
    overflow: hidden;
    color: #64748b;
    font-size: 8px;
    text-align: right;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.cash-edit {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    min-width: 22px;
    min-height: 22px;
    padding: 0 4px;
    border: 1px solid #bae6fd;
    border-radius: 4px;
    background: #ffffff;
    color: #0369a1;
    font-size: 12px;
    cursor: pointer;
}

.cash-edit:hover:not(:disabled) {
    background: #e0f2fe;
    border-color: #38bdf8;
}

/* Компактный режим */

.cash-compact-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    gap: 10px;
}

.cash-compact-grid>div {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}

.cash-compact-grid span {
    color: #64748b;
    font-size: 8px;
    line-height: 1.1;
}

.cash-compact-grid b {
    font-size: 11px;
    line-height: 1.2;
    white-space: nowrap;
}

/* Полный режим */

.income-row {
    display: grid;
    grid-template-columns: 65px minmax(0, 1fr) 72px;
    gap: 8px;
    align-items: baseline;
    color: #475569;
    font-size: 10px;
}

.income-row b,
.income-row small {
    text-align: right;
    white-space: nowrap;
}

.income-row small {
    color: #64748b;
    font-size: 9px;
}

.cash-row {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 9px;
    color: #475569;
    font-size: 10px;
}

.cash-row b {
    white-space: nowrap;
}

.income-total {
    padding-top: 3px;
    border-top: 1px solid #d8eaf3;
}

.muted {
    color: #64748b;
    font-size: 9px;
}

.cash-taxes {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 3px 10px;
    color: #64748b;
    font-size: 8px;
}

/* Доход и расход */

.cash-card .cash-income {
    color: var(--bs-success, #198754);
}

.cash-card .cash-expense {
    color: var(--bs-danger, #dc3545);
}

/* Прибыль с процентом */

.cash-profit-indicator {
    margin-top: 2px;
    padding: 3px 5px;
    gap: 8px;
    font-size: 9px;
}

.cash-profit-indicator :deep(.difference-value) {
    font-size: 11px;
}

.cash-profit-indicator :deep(.difference-percent) {
    font-size: 9px;
}

/* =========================================================
   ОКНО РЕДАКТИРОВАНИЯ ДДС
   ========================================================= */

.cash-overlay {
    position: fixed;
    inset: 0;
    z-index: 12000;
    display: flex;
    align-items: center;
    justify-content: center;
    box-sizing: border-box;
    padding: 16px;
    background: rgb(15 23 42 / 45%);
}

.cash-modal {
    width: 460px;
    max-width: 100%;
    max-height: calc(100dvh - 32px);
    margin: 0;
    overflow-y: auto;
    border-radius: 9px;
    background: #ffffff;
    box-shadow: 0 18px 50px rgb(15 23 42 / 25%);
}

.modal-heading,
.modal-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 10px 12px;
    background: #f8fafc;
}

.modal-heading {
    border-bottom: 1px solid #e2e8f0;
}

.modal-heading h3 {
    margin: 0;
    color: #0f172a;
    font-size: 14px;
    font-weight: 700;
}

.close-button {
    padding: 0 3px;
    border: 0;
    background: transparent;
    color: #64748b;
    font-size: 23px;
    line-height: 1;
    cursor: pointer;
}

.modal-body {
    padding: 12px;
}

.payment-title {
    margin-bottom: 6px;
    color: #334155;
    font-size: 12px;
    font-weight: 700;
}

.payment-fields {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    gap: 10px;
    margin-bottom: 14px;
}

.cash-modal label {
    display: flex;
    flex-direction: column;
    gap: 4px;
    color: #475569;
    font-size: 11px;
}

.cash-modal input {
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
    padding: 7px 8px;
    border: 1px solid #cbd5e1;
    border-radius: 5px;
    background: #ffffff;
    color: #0f172a;
    font: inherit;
    font-size: 13px;
}

.cash-modal input:disabled {
    background: #f1f5f9;
}

.form-note {
    margin: 8px 0 0;
    color: #64748b;
    font-size: 10px;
    line-height: 1.4;
}

.cash-error {
    margin-top: 10px;
    padding: 7px 9px;
    border-radius: 5px;
    background: #fef2f2;
    color: #b91c1c;
    font-size: 12px;
    line-height: 1.4;
}

.modal-footer {
    justify-content: flex-end;
    border-top: 1px solid #e2e8f0;
}

.modal-footer button {
    padding: 7px 12px;
    border: 1px solid #cbd5e1;
    border-radius: 5px;
    background: #ffffff;
    color: #334155;
    font-size: 12px;
    cursor: pointer;
}

.modal-footer button:hover:not(:disabled) {
    background: #f1f5f9;
}

.modal-footer .submit-button {
    border-color: #0284c7;
    background: #0284c7;
    color: #ffffff;
}

.modal-footer .submit-button:hover:not(:disabled) {
    border-color: #0369a1;
    background: #0369a1;
}

button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

input:focus-visible,
button:focus-visible {
    outline: 2px solid #38bdf8;
    outline-offset: 2px;
}

@media (pointer: coarse) {

    .cash-toggle,
    .cash-edit {
        min-height: 30px;
    }

    .cash-edit {
        min-width: 30px;
    }
}

@media (max-width: 420px) {
    .payment-fields {
        grid-template-columns: 1fr;
    }
}

/* Прибыль прижата к нижнему краю плитки */
.cash-profit-indicator {
    margin-top: auto;
}
</style>
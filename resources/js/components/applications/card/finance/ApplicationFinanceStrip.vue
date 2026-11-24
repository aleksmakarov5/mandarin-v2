<template>
    <div class="finance-scroll" role="region" aria-label="Финансовые показатели дела" tabindex="0" :aria-busy="loading">
        <div class="finance-strip">
            <FinanceApprovedCard :summary="summary" :loading="loading || cashSaving" :saving="approvedSaving"
                :error="approvedError" :vat-saving="vatSaving" :vat-error="vatError" :can-toggle-vat="canToggleVat"
                :vat-button-title="vatButtonTitle" @toggle-vat="emit('toggle-vat')"
                @save-approved="emit('save-approved', $event)" />

            <FinanceTooltip class="finance-cell cell-planned" heading="Плановая сумма и прибыль" :rows="plannedRows"
                note="Разница рассчитывается за вычетом налогов. В режиме «+НДС» налог добавляется к базе ПЗН; в режиме «включая НДС» выделяется из неё. Процент разницы — от полной плановой суммы с НДС.">
                <div class="cell-heading">Сумма плановая</div>

                <strong class="cell-amount">
                    {{ money(summary.planned?.gross) }}
                </strong>

                <div class="cell-detail">
                    ПЗН {{ money(summary.planned?.total) }}
                    · налоги {{ money(summary.planned?.taxes_total) }}
                </div>

                <FinanceDifference :value="summary.planned?.difference" :base="summary.planned?.gross" />
            </FinanceTooltip>

            <FinanceTooltip class="finance-cell cell-expenses" heading="Плановые расходы" :rows="plannedExpenseRows"
                note="Выбранные цены запчастей, плановые работы и расходные материалы.">
                <div class="cell-heading">Расходы план</div>

                <strong class="cell-amount">
                    {{ money(summary.planned_expenses?.total) }}
                </strong>

                <div class="expense-breakdown">
                    <span>
                        ЗЧ <b>{{ money(summary.planned_expenses?.parts, false) }}</b>
                    </span>
                    <span>
                        Раб <b>{{ money(summary.planned_expenses?.works, false) }}</b>
                    </span>
                    <span>
                        ЛКМ <b>{{ money(summary.planned_expenses?.materials, false) }}</b>
                    </span>
                </div>
            </FinanceTooltip>

            <FinanceTooltip class="finance-cell cell-expenses" heading="Фактические расходы" :rows="actualExpenseRows"
                note="Запчасти после стадии «Заказана», фактические работы и дополнительные расходы. Расходы факта отличаются от расхода ДДС.">
                <div class="cell-heading">Расходы факт</div>

                <strong class="cell-amount">
                    {{ money(summary.actual_expenses?.total) }}
                </strong>

                <div class="expense-breakdown">
                    <span>
                        ЗЧ <b>{{ money(summary.actual_expenses?.parts, false) }}</b>
                    </span>
                    <span>
                        Раб <b>{{ money(summary.actual_expenses?.works, false) }}</b>
                    </span>
                    <span>
                        Доп <b>{{ money(summary.actual_expenses?.extra_expenses, false) }}</b>
                    </span>
                </div>
            </FinanceTooltip>

            <FinanceCashCard :summary="summary" :saving="cashSaving"
                :busy="loading || vatSaving || approvedSaving || cashSaving" :error="cashError" :save-cash="saveCash" />

            <FinanceTooltip v-for="salary in salaryItems" :key="salary.role" class="finance-cell cell-salary"
                heading="Собственная расчётная зарплата" :rows="[
                    {
                        label: 'Фактическая прибыль',
                        value: money(summary.cash?.profit),
                    },
                    {
                        label: 'Процент',
                        value: percent(salary.rate) + '%',
                    },
                    {
                        label: 'Расчётная ЗП',
                        value: money(salary.amount),
                        total: true,
                    },
                ]"
                note="Показана только зарплата, которую сервер разрешил текущему пользователю. При убытке расчётная зарплата равна нулю.">
                <div class="cell-heading">
                    ЗП · {{ salary.role === 'mentor' ? 'Наставник' : 'МРК' }}
                </div>

                <strong class="cell-amount amount-positive">
                    {{ money(salary.amount) }}
                </strong>

                <div class="cell-detail">
                    {{ percent(salary.rate) }}% от факт. прибыли
                </div>
            </FinanceTooltip>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

import {
    amountClass,
    date,
    isAvailable,
    money,
    percent,
} from './financeFormatters';

import FinanceApprovedCard from './FinanceApprovedCard.vue';
import FinanceDifference from './FinanceDifference.vue';
import FinanceTooltip from './FinanceTooltip.vue';
import FinanceCashCard from './FinanceCashCard.vue';

const props = defineProps({
    summary: {
        type: Object,
        required: true,
    },
    loading: {
        type: Boolean,
        default: false,
    },
    vatSaving: {
        type: Boolean,
        default: false,
    },
    vatError: {
        type: String,
        default: '',
    },
    canToggleVat: {
        type: Boolean,
        default: false,
    },
    vatButtonTitle: {
        type: String,
        default: '',
    },
    approvedSaving: {
        type: Boolean,
        default: false,
    },
    approvedError: {
        type: String,
        default: '',
    },
    cashSaving: {
        type: Boolean,
        default: false,
    },
    cashError: {
        type: String,
        default: '',
    },
    saveCash: {
        type: Function,
        required: true,
    },
});

const emit = defineEmits(['toggle-vat', 'save-approved']);

const vatRate = computed(() => {
    return props.summary.cash?.vat_rate
        ?? props.summary.approved?.vat_rate
        ?? null;
});

const showCashVat = computed(() => {
    return isAvailable(vatRate.value) && Number(vatRate.value) > 0;
});

const salaryItems = computed(() => {
    const source = Array.isArray(props.summary.salary_items)
        ? props.summary.salary_items
        : props.summary.salary;

    if (!source) return [];

    return (Array.isArray(source) ? source : [source])
        .filter(item => {
            return item
                && ['mrk', 'mentor'].includes(item.role)
                && item.visible !== false;
        });
});

const plannedRows = computed(() => {
    const p = props.summary.planned ?? {};

    return [
        { label: 'Запчасти ПЗН', value: money(p.parts_pzn) },
        { label: 'Ремонтные работы ПЗН', value: money(p.repair_pzn) },
        { label: 'Окрасочные работы ПЗН', value: money(p.paint_pzn) },
        { label: 'Расходные материалы', value: money(p.materials) },
        { label: 'Cумма ПЗН', value: money(p.total) },
        { label: 'Сумма с НДС', value: money(p.gross) },
        {
            label: 'Плановые расходы',
            value: money(props.summary.planned_expenses?.total),
        },
        { label: 'НДС', value: money(p.vat) },
        { label: 'налог на прибыль', value: money(p.income_tax) },
        {
            label: 'Плановая прибыль',
            value: money(p.difference),
            total: true,
        },
    ];
});

const plannedExpenseRows = computed(() => {
    const p = props.summary.planned_expenses ?? {};

    return [
        { label: 'Выбранные запчасти', value: money(p.parts) },
        { label: 'Принятые плановые работы', value: money(p.works) },
        { label: 'Расходные материалы', value: money(p.materials) },
        { label: 'Всего', value: money(p.total), total: true },
    ];
});

const actualExpenseRows = computed(() => {
    const p = props.summary.actual_expenses ?? {};

    return [
        { label: 'Запчасти после заказа', value: money(p.parts) },
        { label: 'Фактические работы', value: money(p.works) },
        { label: 'Дополнительные расходы', value: money(p.extra_expenses) },
        { label: 'Всего', value: money(p.total), total: true },
    ];
});

const cashRows = computed(() => {
    const c = props.summary.cash ?? {};

    return [
        { label: 'Юридическое лицо', value: props.summary.legal_entity?.name || '—' },
        { label: 'Поступления', value: money(c.income) },
        { label: 'Дата поступления', value: date(c.income_date) },
        { label: 'Заказанные запчасти', value: money(c.ordered_parts) },
        { label: 'Дополнительные расходы', value: money(c.extra_expenses) },
        { label: 'Оплаченные работы', value: money(c.paid_works) },
        { label: 'Расчёт с СТО', value: date(c.sto_payment_date) },
        { label: 'Расход ДДС', value: money(c.expense) },
        { label: 'НДС', value: money(c.vat) },
        { label: 'налог на прибыль', value: money(c.income_tax) },
        { label: 'Фактическая прибыль', value: money(c.profit), total: true },
    ];
});
</script>

<style scoped>
.finance-scroll {
    flex: 1 1 0%;
    min-width: 0;
    overflow-x: auto;
    padding-bottom: 4px;
    overscroll-behavior-x: contain;
    scrollbar-width: thin;
}

.finance-scroll:focus-visible {
    outline: 2px solid #299bd1;
    outline-offset: 2px;
}

.finance-strip {
    display: flex;
    align-items: flex-start;
    gap: 6px;
    width: max-content;
    min-width: 100%;
}

.finance-cell {
    display: flex;
    flex: 0 0 auto;
    flex-direction: column;
    gap: 6px;
    min-height: 102px;
    padding: 7px 9px;
    border: 1px solid #dbe4ee;
    border-radius: 7px;
    background: #f8fafc;
}

.cell-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding-right: 23px;
    color: #475569;
    font-size: 10px;
    font-weight: 750;
    line-height: 1.2;
    white-space: nowrap;
}

.cell-amount {
    color: #0f172a;
    font-size: 18px;
    font-weight: 800;
    line-height: 1.2;
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
}

.cell-detail {
    color: #64748b;
    font-size: 9px;
    white-space: nowrap;
}

.cell-planned {
    min-width: 280px;
}

.cell-expenses {
    min-width: 225px;
}

.expense-breakdown {
    display: grid;
    grid-template-columns: auto auto;
    gap: 3px 12px;
    color: #64748b;
    font-size: 9px;
    white-space: nowrap;
}

.expense-breakdown b {
    color: #334155;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
}

.cell-cash {
    min-width: 345px;
    background: #f0f9ff;
    border-color: #bae6fd;
}

.heading-note {
    max-width: 200px;
    overflow: hidden;
    color: #64748b;
    font-size: 9px;
    font-weight: 400;
    text-overflow: ellipsis;
}

.dds-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 4px 18px;
}

.dds-item {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 8px;
    color: #0f172a;
    font-size: 11px;
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
}

.dds-item>span:first-child {
    color: #64748b;
    font-size: 9px;
}

.dds-secondary {
    color: #475569;
    font-size: 9px;
}

.dds-profit {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 12px;
    margin-top: auto;
    padding-top: 5px;
    border-top: 1px solid #cfe4ee;
    color: #475569;
    font-size: 10px;
}

.dds-profit strong {
    font-size: 14px;
    font-variant-numeric: tabular-nums;
}

.cell-salary {
    min-width: 180px;
    background: #ecfdf5;
    border-color: #bbf7d0;
}

.amount-positive {
    color: #15803d;
}

.amount-negative {
    color: #b91c1c;
}

.amount-unavailable {
    color: #94a3b8;
}

@media (max-width: 640px) {
    .finance-scroll {
        order: 3;
        flex: 0 0 100%;
    }

    .cell-cash {
        min-width: 325px;
    }
}

/* =========================================================
   КОМПАКТНЫЙ РЕЖИМ ФИНАНСОВЫХ ПЛИТОК
   ========================================================= */

.finance-scroll {
    padding-bottom: 2px;
}

.finance-strip {
    align-items: flex-start;
    gap: 4px;
}

.finance-cell {
    min-width: 158px;
    min-height: 78px;
    padding: 4px 6px;
    gap: 2px;
    border-radius: 5px;
}

.cell-heading {
    padding-right: 18px;
    font-size: 9px;
    line-height: 1.1;
}

.cell-amount {
    font-size: 13px;
    line-height: 1.1;
    letter-spacing: -0.2px;
}

.cell-detail {
    font-size: 8px;
    line-height: 1.1;
}

.cell-planned {
    min-width: 190px;
}

.cell-expenses {
    min-width: 175px;
}

.expense-breakdown {
    grid-template-columns: auto auto;
    gap: 2px 8px;
    font-size: 8px;
    line-height: 1.1;
}

.expense-breakdown b {
    font-size: 8px;
}

.tile-bottom-line {
    gap: 8px;
    margin-top: 2px;
    padding-top: 3px;
    font-size: 8px;
    line-height: 1.1;
}

.tile-bottom-line strong {
    font-size: 10px;
}

.cell-cash {
    min-width: 270px;
    padding: 4px 7px;
}

.heading-note {
    max-width: 145px;
    font-size: 8px;
}

.dds-grid {
    column-gap: 10px;
    row-gap: 2px;
}

.dds-item {
    gap: 5px;
    font-size: 8px;
    line-height: 1.1;
}

.dds-item>span:first-child {
    font-size: 8px;
}

.dds-secondary {
    font-size: 8px;
}

.dds-profit {
    gap: 8px;
    margin-top: 2px;
    padding-top: 3px;
    font-size: 8px;
}

.dds-profit strong {
    font-size: 11px;
}

.cell-salary {
    min-width: 155px;
}

.finance-info-button {
    top: 2px;
    right: 3px;
    width: 17px;
    height: 17px;
}

/* Оставляем место под кнопку ⓘ в заголовках */
.finance-cell .cell-heading {
    min-width: 0;
    padding-right: 25px;
}

/* Название юридического лица не доходит до значка */
.finance-cell .heading-note {
    min-width: 0;
    max-width: calc(100% - 28px);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* В плитке ДДС заголовок и название юрлица не перекрываются */


/* В плитке «Одобрено СК» место под значок резервируется отдельно */
.approved-card .card-heading {
    min-width: 0;
    padding-right: 27px !important;
}

.approved-card .expand-button {
    min-width: 0;
    padding-right: 2px;
}

.approved-card .expand-button span:first-child {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* Больше не резервируем место под значок информации */
.finance-cell .cell-heading,
.cell-cash .cell-heading {
    padding-right: 0;
}

.finance-cell .heading-note {
    max-width: 180px;
}

/* Все плитки растягиваются до высоты самой высокой в ряду */
.finance-strip {
    display: flex;
    align-items: stretch;
}

.finance-strip>* {
    align-self: stretch;
    height: auto;
    box-sizing: border-box;
}
</style>
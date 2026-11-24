<template>
    <FinanceTooltip class="approved-card" :class="{ 'approved-card-expanded': expanded }" :aria-busy="saving"
        heading="Одобренная сумма" :rows="tooltipRows"
        note="Разница = сумма с НДС − плановые расходы − НДС − налог с дохода. Нажмите на плитку для раскрытия. В полном режиме суммы редактируются: Enter — сохранить, Escape — отменить."
        @click="handleCardClick">
        <div class="card-heading">
            <button type="button" class="expand-button" :aria-expanded="expanded" :disabled="saving || vatSaving"
                @click.stop="expanded = !expanded">
                <span>Одобрено СК</span>
                <span aria-hidden="true">{{ expanded ? '⌃' : '⌄' }}</span>
            </button>
        </div>

        <!-- Компактный режим -->
        <template v-if="!expanded">
            <div class="compact-total">
                {{ money(approved.gross) }}
            </div>

            <div class="compact-note">
                {{ vatLabel }}
                <template v-if="hasVat">
                    · {{ money(approved.vat) }}
                </template>
            </div>
        </template>

        <!-- Полный режим -->
        <template v-else>
            <template v-if="isSplit">
                <div class="amount-line">
                    <span class="amount-label">Ремонт</span>

                    <span :key="`repair-${revision}`" class="editable-amount" :class="{ 'is-editable': canEditNow }"
                        :contenteditable="canEditNow ? 'plaintext-only' : 'false'" :tabindex="canEditNow ? 0 : -1"
                        :role="canEditNow ? 'textbox' : undefined" aria-label="Оплата ремонта"
                        :aria-readonly="!canEditNow" inputmode="decimal" spellcheck="false"
                        @blur="commit('repair', $event)" @keydown="handleKeydown" @paste="pastePlainText">{{
                            money(draft.repair, false) }}</span>
                </div>

                <div class="amount-line">
                    <span class="amount-label">Дефектовка</span>

                    <span :key="`defect-${revision}`" class="editable-amount" :class="{ 'is-editable': canEditNow }"
                        :contenteditable="canEditNow ? 'plaintext-only' : 'false'" :tabindex="canEditNow ? 0 : -1"
                        :role="canEditNow ? 'textbox' : undefined" aria-label="Оплата дефектовки"
                        :aria-readonly="!canEditNow" inputmode="decimal" spellcheck="false"
                        @blur="commit('defect', $event)" @keydown="handleKeydown" @paste="pastePlainText">{{
                            money(draft.defect, false) }}</span>
                </div>
            </template>

            <div class="total-line" :class="{ 'split-total': isSplit }">
                <span class="total-label">{{ totalLabel }}</span>

                <strong :key="`total-${revision}`" class="editable-amount total-amount"
                    :class="{ 'is-editable': canEditNow }" :contenteditable="canEditNow ? 'plaintext-only' : 'false'"
                    :tabindex="canEditNow ? 0 : -1" :role="canEditNow ? 'textbox' : undefined"
                    aria-label="Согласованная итоговая сумма" :aria-readonly="!canEditNow" inputmode="decimal"
                    spellcheck="false" @blur="commit('entered', $event)" @keydown="handleKeydown"
                    @paste="pastePlainText">{{ money(draft.entered) }}</strong>
            </div>

            <div class="vat-mode-line">
                <button v-if="approvedPermissions.edit_vat === true" type="button" class="vat-mode-button"
                    :class="{ 'vat-mode-added': vatMode === 'added' }" :disabled="!canToggleVat || saving"
                    :aria-label="vatButtonTitle" :aria-busy="vatSaving" @click.stop="emit('toggle-vat')">
                    <span v-if="vatSaving" class="saving-indicator" aria-hidden="true"></span>

                    {{ vatLabel }}

                    <span v-if="canToggleVat && !saving" aria-hidden="true">
                        ⇄
                    </span>
                </button>

                <span v-else class="vat-label">{{ vatLabel }}</span>
            </div>

            <div v-if="hasVat" class="tax-line">
                <span>Сумма НДС</span>
                <b>{{ money(preview.vat) }}</b>
            </div>

            <div class="tax-line">
                <span>Налог {{ percent(approved.income_tax_rate) }}%</span>
                <b>{{ money(approved.income_tax) }}</b>
            </div>

            <div v-if="hasVat && vatMode === 'added'" class="payable-line">
                <span>К оплате с НДС</span>
                <strong>{{ money(preview.gross) }}</strong>
            </div>
        </template>

        <!-- Одинаковая разница в компактном и полном режиме -->
        <FinanceDifference :value="summary.approved_difference" :base="approved.gross" />

        <div v-if="saving" class="save-status" role="status">
            Сохранение…
        </div>

        <div v-if="validationError" class="card-error" role="alert">
            {{ validationError }}
        </div>

        <div v-if="error" class="card-error" role="alert">
            {{ error }}

            <button v-if="dirty && !saving" type="button" class="retry-button" @click.stop="submit">
                Повторить
            </button>
        </div>

        <div v-if="vatError" class="card-error" role="alert">
            {{ vatError }}
        </div>
    </FinanceTooltip>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import FinanceTooltip from './FinanceTooltip.vue';
import FinanceDifference from './FinanceDifference.vue';

import {
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
    error: {
        type: String,
        default: '',
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
});

const emit = defineEmits([
    'save-approved',
    'toggle-vat',
]);

const draft = reactive({
    entered: null,
    repair: null,
    defect: null,
});

const revision = ref(0);
const validationError = ref('');
const dirty = ref(false);
const expanded = ref(false);

function handleCardClick(event) {
    if (
        event.target.closest(
            'button, input, a, [contenteditable], [role="textbox"]'
        )
    ) {
        return;
    }

    if (props.saving || props.vatSaving) return;

    expanded.value = !expanded.value;
}

const tooltipRows = computed(() => {
    const data = props.summary;

    return [
        {
            label: 'Одобренная сумма',
            value: money(data.approved?.entered),
        },
        ...(isSplit.value
            ? [
                {
                    label: 'Ремонт',
                    value: money(data.approved?.repair),
                },
                {
                    label: 'Дефектовка',
                    value: money(data.approved?.defect),
                },
            ]
            : []),
        {
            label: 'Сумма с НДС',
            value: money(data.approved?.gross),
        },
        {
            label: 'Плановые расходы',
            value: money(data.planned_expenses?.total),
        },
        {
            label: 'НДС',
            value: money(data.approved?.vat),
        },
        {
            label: 'Налог на прибыль',
            value: money(data.approved?.income_tax),
        },
        {
            label: 'Разница после налогов',
            value: money(data.approved_difference),
            total: true,
        },
    ];
});

watch(
    () => props.summary.application_id,
    () => {
        expanded.value = false;
    }
);
// Escape отменяет ввод без сохранения на последующем blur.
const cancelledElements = new WeakSet();

const approved = computed(() => props.summary.approved ?? {});

const approvedPermissions = computed(() => {
    return props.summary.permissions ?? {};
});

const isSplit = computed(() => {
    return approved.value.is_split === true;
});

const editable = computed(() => {
    return approvedPermissions.value.edit_approved === true;
});

const canEditNow = computed(() => {
    return editable.value
        && !props.loading
        && !props.saving
        && !props.vatSaving;
});

const vatMode = computed(() => approved.value.vat_mode);

const vatRate = computed(() => approved.value.vat_rate ?? null);

const hasVat = computed(() => {
    return isAvailable(vatRate.value)
        && Number(vatRate.value) > 0;
});

const totalLabel = computed(() => {
    if (!hasVat.value) return 'Итого';

    return vatMode.value === 'added'
        ? 'Итого без НДС'
        : 'Итого с НДС';
});

const vatLabel = computed(() => {
    if (!isAvailable(vatRate.value)) {
        return 'НДС не настроен';
    }

    if (Number(vatRate.value) === 0) {
        return 'Без НДС';
    }

    const rate = percent(vatRate.value);

    if (vatMode.value === 'added') return `+НДС ${rate}%`;
    if (vatMode.value === 'included') return `В т. ч. НДС ${rate}%`;

    return `НДС ${rate}% · режим не настроен`;
});

/*
 * Только предварительное отображение внутри плитки.
 * Полную финансовую сводку пересчитывает Laravel.
 */
const preview = computed(() => {
    const amount = draft.entered;

    if (!isAvailable(amount)) {
        return { vat: null, gross: null };
    }

    if (!isAvailable(vatRate.value)) {
        return {
            vat: null,
            gross: vatMode.value === 'included' ? amount : null,
        };
    }

    const rate = Number(vatRate.value);

    if (rate === 0) {
        return { vat: 0, gross: amount };
    }

    const product = amount * rate;

    if (!Number.isSafeInteger(product)) {
        return { vat: null, gross: null };
    }

    if (vatMode.value === 'added') {
        const vat = Math.floor((product + 5000) / 10000);
        const gross = amount + vat;

        return {
            vat,
            gross: Number.isSafeInteger(gross) ? gross : null,
        };
    }

    if (vatMode.value === 'included') {
        const divisor = 10000 + rate;
        const vat = Math.floor(
            (product + Math.floor(divisor / 2)) / divisor
        );

        return { vat, gross: amount };
    }

    return { vat: null, gross: null };
});

watch(
    () => props.summary,
    () => {
        draft.entered = availableOrNull(approved.value.entered);
        draft.repair = availableOrNull(approved.value.repair);
        draft.defect = availableOrNull(approved.value.defect);

        validationError.value = '';
        dirty.value = false;
        revision.value += 1;
    },
    { immediate: true }
);

function availableOrNull(value) {
    return isAvailable(value) ? Number(value) : null;
}

/**
 * Текст в рублях → целые копейки.
 * Неверный ввод не превращается молча в ноль.
 */
function parseMoney(raw) {
    const text = String(raw)
        .replace(/[\s\u00a0\u202f]/g, '')
        .replace(/₽/g, '')
        .replace(',', '.');

    if (!/^\d+(?:\.\d{1,2})?$/.test(text)) {
        throw new Error(
            'Введите неотрицательную сумму с точностью до двух знаков.'
        );
    }

    const [whole, fraction = ''] = text.split('.');

    const amount = Number(whole) * 100
        + Number(fraction.padEnd(2, '0'));

    if (!Number.isSafeInteger(amount)) {
        throw new Error('Сумма слишком большая.');
    }

    return amount;
}

function commit(field, event) {
    const element = event.currentTarget;

    if (cancelledElements.has(element)) {
        cancelledElements.delete(element);
        revision.value += 1;
        return;
    }

    if (!canEditNow.value) return;

    validationError.value = '';

    try {
        const value = parseMoney(element.innerText);

        const next = {
            entered: draft.entered,
            repair: draft.repair,
            defect: draft.defect,
        };

        if (isSplit.value) {
            if (
                !isAvailable(next.repair)
                || !isAvailable(next.defect)
            ) {
                throw new Error(
                    'Не получены исходные суммы ремонта и дефектовки.'
                );
            }

            if (field === 'repair') {
                next.repair = value;
                next.entered = next.repair + next.defect;
            } else if (field === 'defect') {
                next.defect = value;
                next.entered = next.repair + next.defect;
            } else {
                if (value < next.repair) {
                    throw new Error(
                        'Итоговая сумма не может быть меньше оплаты ремонта.'
                    );
                }

                next.entered = value;
                next.defect = next.entered - next.repair;
            }
        } else {
            next.entered = value;
        }

        if (!Number.isSafeInteger(next.entered)) {
            throw new Error('Итоговая сумма слишком большая.');
        }

        const changed = next.entered !== draft.entered
            || next.repair !== draft.repair
            || next.defect !== draft.defect;

        Object.assign(draft, next);
        revision.value += 1;

        if (changed) {
            dirty.value = true;
            submit();
        }
    } catch (error) {
        validationError.value = error.message;
        revision.value += 1;
    }
}

function submit() {
    if (!canEditNow.value || !dirty.value) return;

    emit('save-approved', isSplit.value
        ? {
            entered: draft.entered,
            repair: draft.repair,
            defect: draft.defect,
        }
        : {
            entered: draft.entered,
        }
    );
}

function handleKeydown(event) {
    if (event.isComposing) return;

    if (event.key === 'Enter') {
        event.preventDefault();
        event.currentTarget.blur();
    }

    if (event.key === 'Escape') {
        event.preventDefault();

        cancelledElements.add(event.currentTarget);
        event.currentTarget.blur();
    }
}

function pastePlainText(event) {
    event.preventDefault();

    const text = event.clipboardData?.getData('text/plain') ?? '';
    const selection = window.getSelection();

    if (!selection || selection.rangeCount === 0) return;

    const range = selection.getRangeAt(0);

    if (!event.currentTarget.contains(range.commonAncestorContainer)) {
        return;
    }

    range.deleteContents();

    const node = document.createTextNode(
        text.replace(/[\r\n]+/g, ' ')
    );

    range.insertNode(node);
    range.setStartAfter(node);
    range.collapse(true);

    selection.removeAllRanges();
    selection.addRange(range);
}
</script>

<style scoped>
.approved-card {
    display: flex;
    flex: 0 0 auto;
    flex-direction: column;
    gap: 5px;
    align-self: flex-start;
    width: 280px;
    padding: 7px 9px;
    border: 1px solid #b9dff3;
    border-radius: 7px;
    background: #eff8ff;
    cursor: pointer;
}

.approved-card-expanded {
    width: 310px;
}

.card-heading {
    padding-right: 23px;
}

.expand-button {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    width: 100%;
    padding: 0;
    border: 0;
    background: transparent;
    color: #475569;
    font: inherit;
    font-size: 10px;
    font-weight: 750;
    text-align: left;
    cursor: pointer;
}

.expand-button:disabled {
    opacity: 0.65;
    cursor: default;
}

.compact-total {
    color: #0f172a;
    font-size: 18px;
    font-weight: 800;
    line-height: 1.2;
    font-variant-numeric: tabular-nums;
}

.compact-note {
    margin-bottom: 3px;
    color: #64748b;
    font-size: 10px;
}

.amount-line,
.total-line,
.tax-line,
.payable-line {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 12px;
    font-variant-numeric: tabular-nums;
}

.amount-label,
.total-label {
    color: #475569;
    font-size: 10px;
    white-space: nowrap;
}

.editable-amount {
    display: inline-block;
    min-width: 64px;
    padding: 1px 3px;
    border-radius: 3px;
    color: #0f172a;
    font-size: 12px;
    text-align: right;
    white-space: nowrap;
    outline: none;
}

.is-editable {
    border-bottom: 1px dashed #7db6d2;
    cursor: text;
}

.is-editable:hover {
    background: #e0f2fe;
}

.is-editable:focus {
    background: #ffffff;
    box-shadow: 0 0 0 2px #7dd3fc;
}

.total-amount {
    font-size: 14px;
    font-weight: 800;
}

.split-total {
    padding-top: 4px;
    border-top: 1px solid #cfe4ee;
}

.vat-mode-button {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    min-height: 22px;
    padding: 2px 6px;
    border: 1px solid #bae6fd;
    border-radius: 4px;
    background: #f0f9ff;
    color: #0369a1;
    font: inherit;
    font-size: 10px;
    font-weight: 700;
    cursor: pointer;
}

.vat-mode-button:hover:not(:disabled) {
    background: #e0f2fe;
    border-color: #38bdf8;
}

.vat-mode-added {
    background: #fff7ed;
    border-color: #fed7aa;
    color: #c2410c;
}

.vat-mode-button:disabled {
    opacity: 0.65;
    cursor: default;
}

.vat-label,
.tax-line {
    color: #64748b;
    font-size: 10px;
}

.tax-line b {
    color: #334155;
}

.payable-line {
    padding-top: 4px;
    border-top: 1px solid #cfe4ee;
    color: #0369a1;
    font-size: 10px;
}

.payable-line strong {
    font-size: 12px;
}

.card-error {
    color: #b91c1c;
    font-size: 10px;
    line-height: 1.35;
    white-space: normal;
    cursor: default;
}

.save-status {
    color: #0369a1;
    font-size: 10px;
}

.retry-button {
    margin-left: 4px;
    padding: 1px 4px;
    border: 1px solid currentColor;
    border-radius: 3px;
    background: transparent;
    color: inherit;
    font: inherit;
    cursor: pointer;
}

.saving-indicator {
    width: 10px;
    height: 10px;
    box-sizing: border-box;
    border: 2px solid currentColor;
    border-right-color: transparent;
    border-radius: 50%;
    animation: approved-card-spin 0.7s linear infinite;
}

@keyframes approved-card-spin {
    to {
        transform: rotate(360deg);
    }
}

@media (pointer: coarse) {

    .expand-button,
    .editable-amount,
    .vat-mode-button {
        min-height: 30px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .saving-indicator {
        animation: none;
    }
}

/* =========================================================
   КОМПАКТНАЯ ПЛИТКА «ОДОБРЕНО СК»
   ========================================================= */

.approved-card {
    width: 225px;
    min-width: 225px;
    padding: 4px 6px;
    gap: 2px;
    border-radius: 5px;
}

.approved-card-expanded {
    width: 270px;
    min-width: 270px;
}

.card-heading {
    padding-right: 18px;
    font-size: 9px;
    line-height: 1.1;
}

.expand-button {
    gap: 6px;
    font-size: 9px;
}

.compact-total {
    font-size: 14px;
    line-height: 1.1;
}

.compact-note {
    margin-bottom: 1px;
    font-size: 8px;
    line-height: 1.1;
}

.amount-line,
.total-line,
.tax-line,
.payable-line {
    gap: 8px;
    min-height: 14px;
    line-height: 1.1;
}

.amount-label,
.total-label {
    font-size: 9px;
}

.editable-amount {
    min-width: 52px;
    padding: 1px 2px;
    font-size: 10px;
}

.total-amount {
    font-size: 12px;
}

.split-total {
    padding-top: 2px;
}

.vat-mode-line {
    margin-top: 1px;
}

.vat-mode-button {
    min-height: 18px;
    padding: 1px 4px;
    font-size: 8px;
}

.vat-label,
.tax-line {
    font-size: 8px;
}

.payable-line {
    padding-top: 2px;
}

.payable-line strong {
    font-size: 10px;
}

/* Разница в одобренной плитке */
.approved-card :deep(.finance-difference) {
    gap: 7px;
    margin-top: 2px;
    padding: 3px 5px;
    font-size: 8px;
    line-height: 1.1;
}

.approved-card :deep(.difference-value) {
    font-size: 10px;
}

.approved-card :deep(.difference-percent) {
    font-size: 8px;
}

.finance-info-button {
    top: 2px;
    right: 3px;
    width: 17px;
    height: 17px;
    font-size: 11px;
}

.approved-card .card-heading {
    padding-right: 0 !important;
}
</style>
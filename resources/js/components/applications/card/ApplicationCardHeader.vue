<template>
    <header class="card-header">
        <!-- Стрелка назад и номер заявки -->
        <div class="header-identity">
            <button type="button" class="icon-button back-button" title="Назад к реестру" aria-label="Назад к реестру"
                @click="emit('back')">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M19 12H5" />
                    <path d="m12 19-7-7 7-7" />
                </svg>
            </button>

            <div class="application-number">
                <span>Заявка</span>
                <strong>№ {{ displayedNumber }}</strong>
            </div>
        </div>

        <!-- Финансовая информация -->
        <div class="finance-scroll" role="region" aria-label="Финансовые показатели заявки" tabindex="0">
            <div v-for="item in financialItems" :key="item.key" class="finance-item" :class="{
                'finance-item--positive': item.color === 'positive',
                'finance-item--negative': item.color === 'negative',
            }">
                <span class="finance-label">{{ item.label }}</span>
                <span class="finance-value">{{ formatMoney(item.value) }}</span>
            </div>
        </div>

        <!-- Пересчёт и сохранение активного модуля -->
        <div class="header-actions">
            <button type="button" class="icon-button recalculate-button" :disabled="loading || !hasApplicationId"
                :aria-busy="loading" title="Пересчитать финансовые показатели"
                aria-label="Пересчитать финансовые показатели" @click="loadFinancialSummary">
                <svg :class="{ spinning: loading }" width="18" height="18" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                    aria-hidden="true">
                    <path d="M20 7v5h-5" />
                    <path d="M4 17v-5h5" />
                    <path d="M5.5 9A7 7 0 0 1 18 6l2 6" />
                    <path d="M18.5 15A7 7 0 0 1 6 18l-2-6" />
                </svg>
            </button>

            <button v-if="showSave" type="button" class="icon-button save-button" :disabled="saving" :aria-busy="saving"
                :title="saving ? 'Сохранение…' : 'Сохранить'" :aria-label="saving ? 'Сохранение' : 'Сохранить'"
                @click="emit('save')">
                <svg v-if="saving" class="spinning" width="18" height="18" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="9" opacity="0.25" />
                    <path d="M12 3a9 9 0 0 1 9 9" />
                </svg>

                <svg v-else width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z" />
                    <path d="M17 21v-8H7v8" />
                    <path d="M7 3v5h8" />
                </svg>
            </button>
        </div>

        <div v-if="errorMessage" class="header-error" role="alert">
            {{ errorMessage }}
        </div>
    </header>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
    applicationId: {
        type: [Number, String],
        default: null,
    },
    application: {
        type: Object,
        default: () => ({}),
    },
    // Оставляем для совместимости с ApplicationShow.
    status: {
        type: [Object, String, Number],
        default: null,
    },
    saving: {
        type: Boolean,
        default: false,
    },
    showSave: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['back', 'save']);

/*
 * ПРИМЕР контракта API — не утверждение о существующих полях.
 * После знакомства с backend заменим ключи на реальные.
 */
const FINANCIAL_FIELDS = [
    { key: 'approved_total', label: 'Одобрено СК' },
    { key: 'approved_difference', label: 'Разница одобр.' },
    { key: 'planned_total', label: 'Сумма план' },
    { key: 'planned_difference', label: 'Разница план' },
    { key: 'planned_expenses', label: 'Расходы план' },
    { key: 'actual_expenses', label: 'Расходы факт' },
    { key: 'cash_balance', label: 'ДДС' },
    { key: 'salary', label: 'ЗП' },
];

const DIFFERENCE_FIELDS = new Set([
    'approved_difference',
    'planned_difference',
    'cash_balance',
]);

const summary = ref(null);
const loading = ref(false);
const errorMessage = ref('');

let requestController = null;
let requestVersion = 0;

const hasApplicationId = computed(() => {
    return props.applicationId !== null
        && props.applicationId !== undefined
        && String(props.applicationId).trim() !== '';
});

const displayedNumber = computed(() => {
    return props.application?.number
        ?? props.application?.app_number
        ?? props.applicationId
        ?? '—';
});

function asNumber(value) {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const number = Number(value);
    return Number.isFinite(number) ? number : null;
}

const financialItems = computed(() => {
    return FINANCIAL_FIELDS.map((field) => {
        const value = asNumber(summary.value?.[field.key]);

        let color = null;
        if (value !== null && DIFFERENCE_FIELDS.has(field.key)) {
            color = value < 0 ? 'negative' : 'positive';
        }

        return {
            ...field,
            value,
            color,
        };
    });
});

const moneyFormatter = new Intl.NumberFormat('ru-RU', {
    style: 'currency',
    currency: 'RUB',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

function formatMoney(value) {
    return value === null ? '—' : moneyFormatter.format(value);
}

/*
 * Пример URL. Заменить на реальный маршрут финансовой сводки.
 * Ожидается объект с полями FINANCIAL_FIELDS — непосредственно
 * в ответе либо внутри response.data.data.
 */
async function loadFinancialSummary() {
    if (!hasApplicationId.value || loading.value) {
        return;
    }

    const applicationId = props.applicationId;
    const version = ++requestVersion;

    requestController = new AbortController();
    loading.value = true;
    errorMessage.value = '';

    try {
        const response = await axios.get(
            `/api/applications/${encodeURIComponent(applicationId)}/financial-summary`,
            { signal: requestController.signal }
        );

        if (version !== requestVersion) {
            return;
        }

        const data = response.data?.data ?? response.data;

        if (!data || typeof data !== 'object' || Array.isArray(data)) {
            throw new Error('Некорректный ответ финансовой сводки');
        }

        summary.value = data;
    } catch (error) {
        if (version !== requestVersion) {
            return;
        }

        if (error.code !== 'ERR_CANCELED') {
            console.error('Не удалось обновить финансовую сводку:', error);
            errorMessage.value = 'Не удалось обновить показатели. Попробуйте ещё раз.';
        }
    } finally {
        if (version === requestVersion) {
            loading.value = false;
            requestController = null;
        }
    }
}

/*
 * Загрузка при открытии или смене заявки.
 * Изменения внутри сметы автоматически запрос не запускают.
 */
watch(
    () => props.applicationId,
    () => {
        requestVersion += 1;
        requestController?.abort();
        requestController = null;

        summary.value = null;
        loading.value = false;
        errorMessage.value = '';

        if (hasApplicationId.value) {
            loadFinancialSummary();
        }
    },
    { immediate: true }
);

onBeforeUnmount(() => {
    requestVersion += 1;
    requestController?.abort();
});
</script>

<style scoped>
.card-header {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    min-width: 0;
    min-height: 54px;
    padding: 6px 12px;
    background: #fff;
    border-bottom: 1px solid #e2e8f0;
    box-shadow: 0 1px 3px rgb(15 23 42 / 5%);
}

.header-identity,
.header-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
}

.application-number {
    display: flex;
    flex-direction: column;
    line-height: 1.15;
    white-space: nowrap;
}

.application-number span {
    color: #64748b;
    font-size: 10px;
    font-weight: 600;
}

.application-number strong {
    color: #0f172a;
    font-size: 14px;
}

.icon-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    width: 36px;
    height: 36px;
    padding: 0;
    border: 1px solid #cbd5e1;
    border-radius: 7px;
    background: #fff;
    color: #475569;
    cursor: pointer;
}

.icon-button:hover:not(:disabled) {
    background: #f1f5f9;
    color: #0f172a;
}

.icon-button:focus-visible,
.finance-scroll:focus-visible {
    outline: 2px solid #f97316;
    outline-offset: 2px;
}

.icon-button:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

.save-button {
    border-color: #ea580c;
    background: #ea580c;
    color: #fff;
}

.save-button:hover:not(:disabled) {
    border-color: #c2410c;
    background: #c2410c;
    color: #fff;
}

.finance-scroll {
    display: flex;
    flex: 1 1 0;
    align-items: stretch;
    gap: 5px;
    min-width: 0;
    overflow-x: auto;
    overscroll-behavior-x: contain;
    -webkit-overflow-scrolling: touch;
}

.finance-item {
    display: flex;
    flex: 0 0 auto;
    flex-direction: column;
    justify-content: center;
    gap: 2px;
    min-width: 105px;
    padding: 4px 8px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    background: #f8fafc;
}

.finance-label {
    color: #64748b;
    font-size: 10px;
    font-weight: 600;
    white-space: nowrap;
}

.finance-value {
    color: #0f172a;
    font-size: 12px;
    font-weight: 700;
    white-space: nowrap;
}

.finance-item--positive .finance-value {
    color: #15803d;
}

.finance-item--negative .finance-value {
    color: #dc2626;
}

.header-error {
    flex: 0 0 100%;
    color: #b91c1c;
    font-size: 12px;
}

.spinning {
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

@media (max-width: 600px) {
    .card-header {
        padding: 6px 8px;
    }

    .icon-button {
        width: 40px;
        height: 40px;
    }

    .finance-scroll {
        order: 3;
        flex: 1 0 100%;
    }

    .header-actions {
        margin-left: auto;
    }
}

@media (prefers-reduced-motion: reduce) {
    .spinning {
        animation-duration: 2s;
    }
}
</style>
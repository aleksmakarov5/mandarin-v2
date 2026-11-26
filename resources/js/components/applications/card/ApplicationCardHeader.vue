<template>
    <header class="application-finance-header">
        <div class="header-identity">
            <span class="identity-label">Дело</span>

            <strong class="identity-number">
                #{{ validApplicationId || '—' }}
            </strong>
        </div>

        <ApplicationFinanceStrip v-if="canViewFinance && summary" :summary="summary"
            :loading="loading || salaryRatesBusy" :approved-saving="approvedSaving" :approved-error="approvedError"
            :vat-saving="vatSaving" :vat-error="vatError" :can-toggle-vat="canToggleVat"
            :vat-button-title="vatButtonTitle" :cash-saving="cashSaving" :cash-error="cashError" :save-cash="saveCash"
            @toggle-vat="toggleVatMode" @save-approved="saveApproved" />

        <div v-if="canViewFinance" class="header-actions">
            <button v-if="canManageSalaryRates" type="button" class="salary-rates-button"
                title="Настроить проценты сотрудников по делу" :disabled="loading
                    || vatSaving
                    || approvedSaving
                    || cashSaving
                    || salaryRatesBusy
                    " @click="showSalaryRates = true">
                % сотрудников
            </button>

            <button type="button" class="refresh-button" :disabled="loading
                || vatSaving
                || approvedSaving
                || cashSaving
                || salaryRatesBusy
                || !validApplicationId
                " :aria-busy="loading" :title="refreshTitle" aria-label="Пересчитать финансовые показатели"
                @click="loadSummary">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round" :class="{ rotating: loading }" aria-hidden="true">
                    <path d="M20 7v5h-5" />
                    <path d="M4 17v-5h5" />
                    <path d="M6 7a7 7 0 0 1 12-1l2 3" />
                    <path d="M18 17a7 7 0 0 1-12 1l-2-3" />
                </svg>
            </button>

            <span class="refresh-caption">
                {{ loading ? 'Расчёт…' : updatedAtLabel }}
            </span>
        </div>

        <div v-if="loading && !summary" class="header-status" role="status">
            Загрузка…
        </div>

        <div v-if="errorMessage" class="header-message error-message" role="alert">
            {{ errorMessage }}

            <span v-if="canViewFinance && summary">
                Показаны данные предыдущего успешного расчёта.
            </span>

            <button v-if="
                !loading
                && !vatSaving
                && !approvedSaving
                && !cashSaving
                && !salaryRatesBusy
                && validApplicationId
            " type="button" class="retry-button" @click="loadSummary">
                Повторить
            </button>
        </div>

        <!-- <div v-if="canViewFinance && warnings.length" class="header-message warning-message" role="status">
            {{ warnings.join(' · ') }}
        </div> -->
    </header>

    <FinanceSalaryRatesModal v-if="canManageSalaryRates" v-model="showSalaryRates" :application-id="applicationId"
        @busy-change="salaryRatesBusy = $event" @saved="onSalaryRatesSaved" />
</template>

<script setup>
import { computed, ref, watch } from 'vue';

import ApplicationFinanceStrip from './finance/ApplicationFinanceStrip.vue';
import FinanceSalaryRatesModal from './finance/FinanceSalaryRatesModal.vue';
import { useApplicationFinance } from './finance/useApplicationFinance';

defineOptions({
    inheritAttrs: false,
});

const props = defineProps({
    applicationId: {
        type: [Number, String],
        required: true,
    },
});

const showSalaryRates = ref(false);
const salaryRatesBusy = ref(false);

const {
    summary,
    warnings,
    loading,
    errorMessage,

    validApplicationId,
    canViewFinance,

    vatSaving,
    vatError,
    canToggleVat,
    vatButtonTitle,

    approvedSaving,
    approvedError,

    cashSaving,
    cashError,

    updatedAtLabel,
    refreshTitle,

    loadSummary,
    toggleVatMode,
    saveApproved,
    saveCash,
} = useApplicationFinance(() => props.applicationId);

const canManageSalaryRates = computed(() => {
    return summary.value?.permissions?.manage_salary_rates === true;
});

function onSalaryRatesSaved(applicationId) {
    if (
        Number(applicationId)
        !== Number(props.applicationId)
    ) {
        return;
    }

    loadSummary();
}

watch(
    () => props.applicationId,
    () => {
        showSalaryRates.value = false;
        salaryRatesBusy.value = false;
    }
);

watch(
    canManageSalaryRates,
    (allowed) => {
        if (!allowed) {
            showSalaryRates.value = false;
        }
    }
);
</script>

<style scoped>
.application-finance-header {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
    width: 100%;
    min-width: 0;
    padding: 7px 10px;
    box-sizing: border-box;
    background: #ffffff;
    border-bottom: 1px solid #dbe4ee;
}

.header-identity {
    display: flex;
    flex: 0 0 auto;
    flex-direction: column;
    min-width: 62px;
    padding-right: 10px;
}

.identity-label {
    color: #64748b;
    font-size: 10px;
    line-height: 1.2;
}

.identity-number {
    color: #0f172a;
    font-size: 16px;
    font-weight: 800;
    font-variant-numeric: tabular-nums;
}

.header-actions {
    display: flex;
    flex: 0 0 auto;
    flex-direction: column;
    align-items: center;
    gap: 3px;
    min-width: 52px;
}

.salary-rates-button {
    padding: 5px 8px;
    border: 1px solid #bae6fd;
    border-radius: 5px;
    background: #f0f9ff;
    color: #0369a1;
    font-size: 10px;
    font-weight: 700;
    white-space: nowrap;
    cursor: pointer;
}

.salary-rates-button:hover:not(:disabled) {
    background: #e0f2fe;
    border-color: #38bdf8;
}

.salary-rates-button:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

.refresh-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    padding: 0;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    background: #ffffff;
    color: #475569;
    cursor: pointer;
}

.refresh-button:hover:not(:disabled) {
    background: #f0f9ff;
    border-color: #7dd3fc;
    color: #0369a1;
}

.refresh-button:focus-visible,
.salary-rates-button:focus-visible,
.retry-button:focus-visible {
    outline: 2px solid #0284c7;
    outline-offset: 2px;
}

.refresh-button:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

.refresh-caption {
    color: #64748b;
    font-size: 9px;
    white-space: nowrap;
}

.header-status {
    color: #64748b;
    font-size: 11px;
}

.header-message {
    flex: 0 0 100%;
    box-sizing: border-box;
    padding: 6px 8px;
    border-radius: 4px;
    font-size: 11px;
    line-height: 1.4;
}

.error-message {
    color: #991b1b;
    background: #fef2f2;
}

.warning-message {
    color: #075985;
    background: #f0f9ff;
}

.retry-button {
    margin-left: 6px;
    padding: 2px 5px;
    border: 1px solid currentColor;
    border-radius: 3px;
    background: transparent;
    color: inherit;
    font: inherit;
    cursor: pointer;
}

.rotating {
    animation: finance-header-spin 0.8s linear infinite;
}

@keyframes finance-header-spin {
    to {
        transform: rotate(360deg);
    }
}

@media (max-width: 640px) {
    .application-finance-header {
        gap: 6px;
        padding: 7px 8px;
    }

    .header-actions {
        flex-direction: row;
        gap: 6px;
        margin-left: auto;
    }

    .refresh-button {
        width: 44px;
        height: 44px;
    }

    .salary-rates-button {
        min-height: 44px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .rotating {
        animation: none;
    }
}
</style>
<template>
    <div class="finance-difference border rounded" :class="variantClass">
        <span class="difference-label">Прибыль</span>

        <strong class="difference-value">
            {{ money(value) }}
            <span class="difference-percent">({{ percentageLabel }})</span>
        </strong>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { isAvailable, money } from './financeFormatters';

const props = defineProps({
    value: {
        type: [Number, String],
        default: null,
    },
    base: {
        type: [Number, String],
        default: null,
    },
});

const ratio = computed(() => {
    if (
        !isAvailable(props.value)
        || !isAvailable(props.base)
        || Number(props.base) <= 0
    ) {
        return null;
    }

    return Number(props.value) / Number(props.base) * 100;
});

const formatter = new Intl.NumberFormat('ru-RU', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

const percentageLabel = computed(() => {
    return ratio.value === null
        ? '—'
        : `${formatter.format(ratio.value)}%`;
});

const variantClass = computed(() => {
    if (ratio.value === null) {
        return 'difference-neutral bg-body-tertiary text-secondary';
    }

    return ratio.value < 40
        ? 'difference-danger bg-danger-subtle text-danger-emphasis border-danger-subtle'
        : 'difference-success bg-success-subtle text-success-emphasis border-success-subtle';
});
</script>

<style scoped>
.finance-difference {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 12px;
    margin-top: auto;
    padding: 5px 7px;
    font-size: 10px;
    line-height: 1.3;
    white-space: nowrap;
}

.difference-value {
    font-size: 12px;
    font-weight: 750;
    font-variant-numeric: tabular-nums;
}

.difference-percent {
    font-size: 10px;
    font-weight: 650;
}

/* Запасное оформление, если Bootstrap ниже версии 5.3. */
.difference-danger {
    background-color: var(--bs-danger-bg-subtle, #f8d7da) !important;
    color: var(--bs-danger-text-emphasis, #842029) !important;
    border-color: var(--bs-danger-border-subtle, #f1aeb5) !important;
}

.difference-success {
    background-color: var(--bs-success-bg-subtle, #d1e7dd) !important;
    color: var(--bs-success-text-emphasis, #0f5132) !important;
    border-color: var(--bs-success-border-subtle, #a3cfbb) !important;
}

.difference-neutral {
    background-color: #f1f5f9;
    border-color: #e2e8f0 !important;
    color: #64748b;
}
</style>
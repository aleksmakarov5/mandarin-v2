<template>
    <div v-if="hasTimer" class="status-progress-track" :title="tooltipText">
        <div class="status-progress-bar" :class="{ 'status-progress-bar--overdue': isOverdue }" :style="barStyle"></div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    timer: {
        type: Object,
        default: () => ({}),
    },
    barColor: {
        type: String,
        default: '#0284c7',
    },
});

const hasTimer = computed(() => {
    return Boolean(props.timer?.has_timer || props.timer?.hasTimer || (props.timer?.max_hour > 0) || (props.timer?.maxHour > 0));
});

const isOverdue = computed(() => {
    return Boolean(props.timer?.is_overdue || props.timer?.isOverdue);
});

const barPercent = computed(() => {
    if (isOverdue.value) return 100;
    const p = Number(props.timer?.percent ?? 0);
    return Math.min(100, Math.max(0, p));
});

const barStyle = computed(() => {
    if (isOverdue.value) {
        return {
            width: '100%',
            backgroundColor: '#dc2626',
        };
    }

    return {
        width: `${barPercent.value}%`,
        backgroundColor: props.barColor || '#0284c7',
    };
});

const tooltipText = computed(() => {
    const count = props.timer?.count_hour ?? props.timer?.countHour ?? 0;
    const max = props.timer?.max_hour ?? props.timer?.maxHour ?? 0;
    const unit = (props.timer?.is_day || props.timer?.isDay) ? 'дн.' : 'ч.';
    return isOverdue.value
        ? `Просрочено! Прошло: ${count} из ${max} ${unit}`
        : `Прошло: ${count} из ${max} ${unit} (${barPercent.value}%)`;
});
</script>

<style scoped>
.status-progress-track {
    width: 100%;
    height: 3px;
    background-color: #e2e8f0;
    border-radius: 2px;
    overflow: hidden;
    margin-top: 3px;
}

.status-progress-bar {
    height: 100%;
    border-radius: 2px;
    transition: width 0.3s ease, background-color 0.3s ease;
}

.status-progress-bar--overdue {
    background-color: #dc2626 !important;
}
</style>
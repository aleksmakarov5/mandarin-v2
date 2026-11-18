<template>
    <div class="row-timer-bg-container" aria-hidden="true">
        <!-- ВЕРХНИЙ ТАЙМЕР: СТАТУС ДЕЛА (50% высоты строки) -->
        <div class="timer-lane timer-lane-top">
            <div class="timer-bar" :class="{
                'bar-animated': isAppUrgent,
                'bar-overdue': isAppOverdue,
                'bar-complete': isAppArchive,
                'bar-normal': !isAppOverdue && !isAppArchive
            }" :style="{ width: appProgressWidth }"></div>
        </div>

        <!-- НИЖНИЙ ТАЙМЕР: ФИНАНСОВЫЙ СТАТУС (50% высоты строки) -->
        <div class="timer-lane timer-lane-bottom">
            <div class="timer-bar" :class="{
                'bar-animated': isFinUrgent,
                'bar-overdue': isFinOverdue,
                'bar-complete': isFinPaid,
                'bar-fin-normal': !isFinOverdue && !isFinPaid
            }" :style="{ width: finProgressWidth }"></div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    item: {
        type: Object,
        required: true,
    },
});

// --- СТАТУС ПРИЛОЖЕНИЯ ---
const isAppArchive = computed(() => {
    const stId = Number(props.item.id_status || props.item.status_id || props.item.status?.id || 0);
    const stName = String(props.item.status?.name || props.item.StatusName || '').toLowerCase();
    return stId === 25 || stName.includes('архив');
});

const isAppOverdue = computed(() => {
    if (isAppArchive.value) return false;
    return Boolean(
        props.item.is_overdue ||
        props.item.app_timer?.is_overdue ||
        (props.item.count_hour !== undefined && Number(props.item.count_hour) < 0)
    );
});

const isAppUrgent = computed(() => {
    if (isAppOverdue.value || isAppArchive.value) return false;
    const count = Number(props.item.app_timer?.count_hour ?? props.item.count_hour ?? 999);
    const isDay = props.item.app_timer?.is_day || props.item.day || false;
    // Меньше 24 часов (1 дня)
    return isDay ? count <= 1 : count <= 24;
});

const appProgressWidth = computed(() => {
    if (isAppArchive.value || isAppOverdue.value) return '100%';
    const max = Number(props.item.app_timer?.max_hour ?? props.item.max_hour ?? props.item.status?.time ?? 0);
    const count = Number(props.item.app_timer?.count_hour ?? props.item.count_hour ?? 0);
    if (max <= 0) return '0%';
    const percent = Math.min(100, Math.max(0, Math.round(((max - count) / max) * 100)));
    return `${percent}%`;
});

// --- ФИНАНСОВЫЙ СТАТУС ---
const isFinPaid = computed(() => {
    const finId = Number(props.item.finance_status_id || props.item.finance_statuses_id || 0);
    const title = String(props.item.soglas_title || props.item.FinStatusName || '').toLowerCase();
    return finId === 7 || title.includes('оплата получена') || title.includes('оплачен');
});

const isFinOverdue = computed(() => {
    if (isFinPaid.value) return false;
    return Boolean(
        props.item.soglas_overdue ||
        props.item.finance_timer?.is_overdue ||
        (props.item.count_hour_f !== undefined && Number(props.item.count_hour_f) < 0)
    );
});

const isFinUrgent = computed(() => {
    if (isFinOverdue.value || isFinPaid.value) return false;
    const countF = Number(props.item.finance_timer?.count_hour_f ?? props.item.count_hour_f ?? 999);
    return countF <= 24 && countF > 0;
});

const finProgressWidth = computed(() => {
    if (isFinPaid.value || isFinOverdue.value) return '100%';
    const maxF = Number(props.item.finance_timer?.max_hour_f ?? props.item.max_hour_f ?? 0);
    const countF = Number(props.item.finance_timer?.count_hour_f ?? props.item.count_hour_f ?? 0);
    if (maxF <= 0) return '0%';
    const percent = Math.min(100, Math.max(0, Math.round(((maxF - countF) / maxF) * 100)));
    return `${percent}%`;
});
</script>

<style scoped>
.row-timer-bg-container {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    z-index: 1;
    display: flex;
    flex-direction: column;
}

.timer-lane {
    width: 100%;
    height: 50%;
    position: relative;
    overflow: hidden;
    background: transparent;
}

.timer-lane-top {
    border-bottom: 1px dashed rgba(0, 0, 0, 0.04);
}

.timer-bar {
    height: 100%;
    transition: width 0.3s ease;
    opacity: 0.22;
    /* Деликатная прозрачность: текст на 100% читаем */
}

/* Цвета полос */
.bar-normal {
    background-color: #0284c7;
    /* Синий статус */
}

.bar-fin-normal {
    background-color: #10b981;
    /* Зеленый фин. статус */
}

.bar-complete {
    background-color: #22c55e !important;
    /* Полный зеленый (Архив / Оплата получена) */
    opacity: 0.28 !important;
}

.bar-overdue {
    background-color: #ef4444 !important;
    /* Красный при просрочке */
    opacity: 0.30 !important;
}

/* Анимированная штриховка, если осталось меньше 24 часов */
.bar-animated {
    background-color: #f59e0b !important;
    /* Янтарный предупреждающий */
    opacity: 0.35 !important;
    background-image: repeating-linear-gradient(-45deg,
            rgba(255, 255, 255, 0.5),
            rgba(255, 255, 255, 0.5) 8px,
            transparent 8px,
            transparent 16px);
    background-size: 32px 32px;
    animation: timerStripeMove 1.2s linear infinite;
}

@keyframes timerStripeMove {
    0% {
        background-position: 0 0;
    }

    100% {
        background-position: 32px 0;
    }
}
</style>
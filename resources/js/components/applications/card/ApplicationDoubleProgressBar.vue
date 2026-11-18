<template>
    <div v-if="hasAnyTimer" class="timers-progress-container" @mouseenter="showTooltip = true"
        @mouseleave="showTooltip = false" @mousemove="updateTooltipPosition">
        <!-- 1. ПОЛОСА ОСНОВНОГО СТАТУСА (СИНЯЯ) -->
        <div v-if="hasAppTimer" class="timer-track app-track">
            <div class="timer-bar timer-bar--blue" :class="{
                'timer-bar--overdue': isAppOverdue,
                'timer-bar--hours-mode': !isAppOverdue && isAppHoursMode,
            }" :style="{
                width: isAppOverdue ? '100%' : `${appPercent}%`,
            }"></div>
        </div>

        <!-- 2. ПОЛОСА ФИНАНСОВОГО СТАТУСА (ЗЕЛЕНАЯ) -->
        <div v-if="hasFinanceTimer" class="timer-track finance-track">
            <div class="timer-bar timer-bar--green" :class="{
                'timer-bar--overdue': isFinOverdue,
                'timer-bar--hours-mode': !isFinOverdue && isFinHoursMode,
            }" :style="{
                width: isFinOverdue ? '100%' : `${finPercent}%`,
            }"></div>
        </div>

        <!-- ВСПЛЫВАЮЩЕЕ ОКНО (TOOLTIP) -->
        <Teleport to="body">
            <div v-if="showTooltip" class="timer-custom-tooltip shadow-lg" :style="{
                top: `${tooltipY}px`,
                left: `${tooltipX}px`,
            }">
                <!-- Статус дела -->
                <div v-if="hasAppTimer" class="tooltip-row">
                    <span class="tooltip-dot" :class="{
                        'bg-danger': isAppOverdue,
                        'bg-blue animate-pulse': !isAppOverdue && isAppHoursMode,
                        'bg-blue': !isAppOverdue && !isAppHoursMode,
                    }"></span>
                    <span class="tooltip-label">Статус:</span>
                    <span class="tooltip-value" :class="{
                        'text-danger fw-bold': isAppOverdue,
                        'text-blue-light fw-bold': !isAppOverdue && isAppHoursMode,
                    }">
                        {{ appTooltipText }}
                    </span>
                    <span v-if="!isAppOverdue && isAppHoursMode" class="badge-urgent badge-urgent--blue ms-1">
                        ⚡ &lt;24ч
                    </span>
                </div>

                <!-- Финансовый статус -->
                <div v-if="hasFinanceTimer" class="tooltip-row mt-1">
                    <span class="tooltip-dot" :class="{
                        'bg-danger': isFinOverdue,
                        'bg-green animate-pulse': !isFinOverdue && isFinHoursMode,
                        'bg-green': !isFinOverdue && !isFinHoursMode,
                    }"></span>
                    <span class="tooltip-label">Фин. статус:</span>
                    <span class="tooltip-value" :class="{
                        'text-danger fw-bold': isFinOverdue,
                        'text-green-light fw-bold': !isFinOverdue && isFinHoursMode,
                    }">
                        {{ finTooltipText }}
                    </span>
                    <span v-if="!isFinOverdue && isFinHoursMode" class="badge-urgent badge-urgent--green ms-1">
                        ⚡ &lt;24ч
                    </span>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    appTimer: {
        type: Object,
        default: () => ({}),
    },
    financeTimer: {
        type: Object,
        default: () => ({}),
    },
    financeStatusId: {
        type: [Number, String],
        default: null,
    },
});

const showTooltip = ref(false);
const tooltipX = ref(0);
const tooltipY = ref(0);

function updateTooltipPosition(event) {
    tooltipX.value = event.clientX + 12;
    tooltipY.value = event.clientY + 14;
}

// ================= ОСНОВНОЙ ТАЙМЕР =================
const hasAppTimer = computed(() => {
    const max = Number(props.appTimer?.max_hour ?? 0);
    const count = Number(props.appTimer?.count_hour ?? 0);
    return max > 0 || count !== 0 || Boolean(props.appTimer?.is_overdue);
});

const isAppOverdue = computed(() => {
    const count = Number(props.appTimer?.count_hour ?? 0);
    return Boolean(props.appTimer?.is_overdue || count < 0);
});

// Режим часов (меньше 24ч)
const isAppHoursMode = computed(() => {
    return Number(props.appTimer?.is_day) === 0 || props.appTimer?.is_day === false;
});

const appPercent = computed(() => {
    if (isAppOverdue.value) return 100;
    if (props.appTimer?.percent !== undefined && props.appTimer?.percent !== null) {
        return Math.min(100, Math.max(0, Number(props.appTimer.percent)));
    }
    const max = Number(props.appTimer?.max_hour ?? 0);
    const count = Number(props.appTimer?.count_hour ?? 0);
    if (max <= 0) return 0;
    return Math.min(100, Math.max(0, Number(((count / max) * 100).toFixed(1))));
});

const appTooltipText = computed(() => {
    const count = Number(props.appTimer?.count_hour ?? 0);
    const max = Number(props.appTimer?.max_hour ?? 0);
    const isDay = !isAppHoursMode.value;
    const unit = isDay ? 'дней' : 'часов';

    if (isAppOverdue.value) {
        return 'просрочено';
    }
    if (max > 0) {
        return `${count} из ${max} ${unit}`;
    }
    return `${count} ${unit}`;
});

// ================= ФИНАНСОВЫЙ ТАЙМЕР =================
const hasFinanceTimer = computed(() => {
    const fId = Number(props.financeStatusId || 0);
    if (fId <= 1) return false;

    const max = Number(props.financeTimer?.max_hour_f ?? props.financeTimer?.max_hour ?? 0);
    const count = Number(props.financeTimer?.count_hour_f ?? props.financeTimer?.count_hour ?? 0);
    return max > 0 || count !== 0 || Boolean(props.financeTimer?.is_overdue);
});

const isFinOverdue = computed(() => {
    const count = Number(props.financeTimer?.count_hour_f ?? props.financeTimer?.count_hour ?? 0);
    return Boolean(props.financeTimer?.is_overdue || count < 0);
});

// Режим часов (меньше 24ч)
const isFinHoursMode = computed(() => {
    return Number(props.financeTimer?.is_day) === 0 || props.financeTimer?.is_day === false;
});

const finPercent = computed(() => {
    if (isFinOverdue.value) return 100;
    if (props.financeTimer?.percent !== undefined && props.financeTimer?.percent !== null) {
        return Math.min(100, Math.max(0, Number(props.financeTimer.percent)));
    }
    const max = Number(props.financeTimer?.max_hour_f ?? props.financeTimer?.max_hour ?? 0);
    const count = Number(props.financeTimer?.count_hour_f ?? props.financeTimer?.count_hour ?? 0);
    if (max <= 0) return 0;
    return Math.min(100, Math.max(0, Number(((count / max) * 100).toFixed(1))));
});

const finTooltipText = computed(() => {
    const count = Number(props.financeTimer?.count_hour_f ?? props.financeTimer?.count_hour ?? 0);
    const max = Number(props.financeTimer?.max_hour_f ?? props.financeTimer?.max_hour ?? 0);
    const isDay = !isFinHoursMode.value;
    const unit = isDay ? 'дней' : 'часов';

    if (isFinOverdue.value) {
        return 'просрочено';
    }
    if (max > 0) {
        return `${count} из ${max} ${unit}`;
    }
    return `${count} ${unit}`;
});

const hasAnyTimer = computed(() => hasAppTimer.value || hasFinanceTimer.value);
</script>

<style scoped>
.timers-progress-container {
    width: 100%;
    display: flex;
    flex-direction: column;
    gap: 1.5px;
    cursor: pointer;
    margin: 0 0 12px 0;
}

.timer-track {
    width: 100%;
    height: 2.2mm;
    background-color: #e2e8f0;
    border-radius: 1px;
    overflow: hidden;
}

.timer-bar {
    height: 100%;
    border-radius: 1px;
    transition: width 0.3s ease, background-color 0.3s ease;
}

/* 1. БАЗОВЫЕ ЦВЕТА (Режим Дней) */
.timer-bar--blue {
    background-color: #2563eb;
}

.timer-bar--green {
    background-color: #16a34a;
}

/* 2. РЕЖИМ ЧАСОВ (< 24 ч): Анимированная штриховка + пульсирующее свечение в СВОЕМ цвете */
.timer-bar--hours-mode {
    background-image: linear-gradient(45deg,
            rgba(255, 255, 255, 0.4) 25%,
            transparent 25%,
            transparent 50%,
            rgba(255, 255, 255, 0.4) 50%,
            rgba(255, 255, 255, 0.4) 75%,
            transparent 75%,
            transparent);
    background-size: 1rem 1rem;
    animation: progress-stripes 1s linear infinite;
}

/* Синяя пульсация */
.timer-bar--blue.timer-bar--hours-mode {
    animation: progress-stripes 1s linear infinite, pulse-blue 1.5s ease-in-out infinite;
}

/* Зеленая пульсация */
.timer-bar--green.timer-bar--hours-mode {
    animation: progress-stripes 1s linear infinite, pulse-green 1.5s ease-in-out infinite;
}

@keyframes progress-stripes {
    0% {
        background-position: 1rem 0;
    }

    100% {
        background-position: 0 0;
    }
}

@keyframes pulse-blue {

    0%,
    100% {
        box-shadow: 0 0 2px rgba(37, 99, 235, 0.4);
        filter: brightness(1);
    }

    50% {
        box-shadow: 0 0 7px rgba(59, 130, 246, 0.9);
        filter: brightness(1.25);
    }
}

@keyframes pulse-green {

    0%,
    100% {
        box-shadow: 0 0 2px rgba(22, 163, 74, 0.4);
        filter: brightness(1);
    }

    50% {
        box-shadow: 0 0 7px rgba(34, 197, 94, 0.9);
        filter: brightness(1.25);
    }
}

/* 3. РЕЖИМ ПРОСРОЧКИ: Красный */
.timer-bar--overdue {
    background-color: #dc2626 !important;
    background-image: none !important;
    box-shadow: 0 0 6px rgba(220, 38, 38, 0.8) !important;
    animation: none !important;
}

/* Всплывающий тултип */
.timer-custom-tooltip {
    position: fixed;
    z-index: 1060;
    pointer-events: none;
    background: rgba(15, 23, 42, 0.94);
    backdrop-filter: blur(4px);
    color: #f8fafc;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 0.8rem;
    line-height: 1.35;
    white-space: nowrap;
    border: 1px solid rgba(255, 255, 255, 0.12);
}

.tooltip-row {
    display: flex;
    align-items: center;
    gap: 6px;
}

.tooltip-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    display: inline-block;
    flex-shrink: 0;
}

.bg-blue {
    background-color: #3b82f6;
}

.bg-green {
    background-color: #22c55e;
}

.bg-danger {
    background-color: #dc2626;
}

.text-blue-light {
    color: #93c5fd;
}

.text-green-light {
    color: #86efac;
}

.animate-pulse {
    animation: pulse-dot 1.2s ease-in-out infinite;
}

@keyframes pulse-dot {

    0%,
    100% {
        opacity: 1;
        transform: scale(1);
    }

    50% {
        opacity: 0.4;
        transform: scale(1.3);
    }
}

.tooltip-label {
    color: #94a3b8;
    font-weight: 500;
}

.tooltip-value {
    color: #ffffff;
    font-weight: 600;
}

/* Бейджи <24ч */
.badge-urgent {
    padding: 1px 5px;
    border-radius: 4px;
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.2px;
}

.badge-urgent--blue {
    background: rgba(59, 130, 246, 0.2);
    color: #bfdbfe;
    border: 1px solid rgba(59, 130, 246, 0.4);
}

.badge-urgent--green {
    background: rgba(34, 197, 94, 0.2);
    color: #bbf7d0;
    border: 1px solid rgba(34, 197, 94, 0.4);
}
</style>
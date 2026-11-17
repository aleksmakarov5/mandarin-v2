<template>
    <div class="status-changers-wrapper d-flex align-items-center gap-2 flex-wrap flex-lg-nowrap">
        <!-- 1. Основной статус заявки (всегда) -->
        <div class="status-widget-block flex-shrink-0">
            <div class="dropdown">
                <button class="status-btn dropdown-toggle d-flex align-items-center justify-content-between shadow-sm"
                    type="button" data-bs-toggle="dropdown" aria-expanded="false" :style="{
                        backgroundColor: formatColor(currentStatus?.bg_color, '#e2e8f0'),
                        color: formatColor(currentStatus?.text_color, '#1e293b'),
                        borderColor: formatColor(currentStatus?.bg_color, '#cbd5e1'),
                    }">
                    <span class="status-title text-truncate me-2">
                        {{ currentStatus?.name || 'Без статуса' }}
                    </span>

                    <span v-if="appDaysCount !== null" class="status-badge">
                        {{ appDaysCount }}
                    </span>
                </button>

                <ul class="dropdown-menu dropdown-menu-end shadow border-0 py-1 status-dropdown-menu">
                    <li v-for="status in statuses" :key="status.id">
                        <button class="dropdown-item d-flex align-items-center justify-content-between py-1.5 px-3"
                            type="button" @click="requestStatusChange(status)">
                            <span class="d-flex align-items-center text-truncate me-2">
                                <span class="status-dot me-2 flex-shrink-0"
                                    :style="{ backgroundColor: formatColor(status.bg_color, '#94a3b8') }"></span>
                                <span class="text-truncate">{{ status.name }}</span>
                            </span>

                            <span v-if="status.time" class="badge bg-light text-muted border font-monospace ms-auto"
                                style="font-size: 0.7rem;">
                                {{ status.time }} {{ status.is_day ? 'дн.' : 'ч.' }}
                            </span>
                        </button>
                    </li>
                </ul>
            </div>

            <!-- Полоса остатка времени для основного статуса -->
            <div class="status-progress-track mt-1" :title="`Остаток времени: ${appProgressPercent}%`">
                <div class="status-progress-bar" :style="{
                    width: `${appProgressPercent}%`,
                    backgroundColor: formatColor(currentStatus?.bg_color, '#3b82f6'),
                }"></div>
            </div>
        </div>

        <!-- 2. Финансовый статус (отображается только если ID > 1) -->
        <div v-if="isFinanceStatusVisible" class="status-widget-block flex-shrink-0">
            <div class="dropdown">
                <button class="status-btn dropdown-toggle d-flex align-items-center justify-content-between shadow-sm"
                    type="button" data-bs-toggle="dropdown" aria-expanded="false" :style="{
                        backgroundColor: formatColor(currentFinanceStatus?.bg_color, '#0d6efd'),
                        color: formatColor(currentFinanceStatus?.text_color, '#ffffff'),
                        borderColor: formatColor(currentFinanceStatus?.bg_color, '#0b5ed7'),
                    }">
                    <span class="status-title text-truncate me-2">
                        {{ currentFinanceStatus?.name || 'АСП согласован' }}
                    </span>

                    <span v-if="financeDaysCount !== null" class="status-badge">
                        {{ financeDaysCount }}
                    </span>
                </button>

                <ul class="dropdown-menu dropdown-menu-end shadow border-0 py-1 status-dropdown-menu">
                    <li v-for="fStatus in financeStatuses" :key="fStatus.id">
                        <button class="dropdown-item d-flex align-items-center justify-content-between py-1.5 px-3"
                            type="button" @click="requestFinanceStatusChange(fStatus)">
                            <span class="d-flex align-items-center text-truncate me-2">
                                <span class="status-dot me-2 flex-shrink-0"
                                    :style="{ backgroundColor: formatColor(fStatus.bg_color, '#94a3b8') }"></span>
                                <span class="text-truncate">{{ fStatus.name }}</span>
                            </span>

                            <span v-if="fStatus.time" class="badge bg-light text-muted border font-monospace ms-auto"
                                style="font-size: 0.7rem;">
                                {{ fStatus.time }} {{ fStatus.is_day ? 'дн.' : 'ч.' }}
                            </span>
                        </button>
                    </li>
                </ul>
            </div>

            <!-- Полоса остатка времени для финансового статуса -->
            <div class="status-progress-track mt-1" :title="`Остаток фин. времени: ${financeProgressPercent}%`">
                <div class="status-progress-bar status-progress-bar--finance" :style="{
                    width: `${financeProgressPercent}%`,
                    backgroundColor: formatColor(currentFinanceStatus?.bg_color, '#2563eb'),
                }"></div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    // Основной статус
    currentStatus: { type: Object, default: null },
    statuses: { type: Array, default: () => [] },
    appProgressPercent: { type: Number, default: 70 }, // Задел под прогресс (0-100%)
    appDaysCount: { type: [Number, String], default: null },

    // Финансовый статус
    financeStatusId: { type: [Number, String], default: null },
    currentFinanceStatus: { type: Object, default: null },
    financeStatuses: { type: Array, default: () => [] },
    financeProgressPercent: { type: Number, default: 85 }, // Задел под прогресс (0-100%)
    financeDaysCount: { type: [Number, String], default: null },
});

const emit = defineEmits(['change', 'change-finance']);

// Отображать финансовый статус только при finance_status_id > 1
const isFinanceStatusVisible = computed(() => {
    const fId = Number(props.financeStatusId || props.currentFinanceStatus?.id || 0);
    return fId > 1;
});

function formatColor(color, fallback) {
    if (!color) return fallback;
    const trimmed = String(color).trim();
    return trimmed.startsWith('#') || trimmed.startsWith('rgb') ? trimmed : `#${trimmed}`;
}

function requestStatusChange(status) {
    emit('change', status);
}

function requestFinanceStatusChange(status) {
    emit('change-finance', status);
}
</script>

<style scoped>
.status-changers-wrapper {
    min-height: 42px;
}

.status-widget-block {
    min-width: 140px;
    max-width: 210px;
}

.status-btn {
    width: 100%;
    height: 32px;
    border-radius: 16px;
    padding: 0 12px;
    font-size: 0.82rem;
    font-weight: 700;
    border: 1px solid transparent;
    transition: all 0.15s ease-in-out;
}

.status-btn:hover,
.status-btn:focus {
    filter: brightness(0.95);
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.12) !important;
}

.status-btn::after {
    margin-left: 0.4rem;
    vertical-align: middle;
}

.status-title {
    letter-spacing: 0.1px;
}

.status-badge {
    background: rgba(0, 0, 0, 0.2);
    color: inherit;
    padding: 1px 6px;
    border-radius: 8px;
    font-size: 0.72rem;
    font-weight: 700;
    line-height: 1.2;
}

.status-dot {
    width: 9px;
    height: 9px;
    border-radius: 50%;
    display: inline-block;
}

.status-dropdown-menu {
    max-height: 320px;
    overflow-y: auto;
    min-width: 200px;
    font-size: 0.85rem;
}

/* Дорожка и полоса прогресса (таймера) */
.status-progress-track {
    width: 100%;
    height: 4px;
    background-color: #e2e8f0;
    border-radius: 2px;
    overflow: hidden;
}

.status-progress-bar {
    height: 100%;
    border-radius: 2px;
    transition: width 0.3s ease;
    filter: saturate(1.2);
}
</style>
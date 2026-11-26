<template>
    <div class="status-changers-wrapper w-100 d-flex align-items-center gap-1">
        <!-- 1. ОСНОВНОЙ СТАТУС ДЕЛА -->
        <div class="status-col flex-grow-1">
            <div v-if="canChangeStatus" class="dropdown w-100">
                <button
                    class="status-pill-btn dropdown-toggle d-flex align-items-center justify-content-between shadow-sm w-100"
                    type="button" data-bs-toggle="dropdown" aria-expanded="false"
                    :title="currentStatus?.name || 'Без статуса'" :style="{
                        backgroundColor: formatColor(
                            currentStatus?.bg_color,
                            '#bae6fd'
                        ),
                        color: formatColor(
                            currentStatus?.text_color,
                            '#0369a1'
                        ),
                        borderColor: formatColor(
                            currentStatus?.bg_color,
                            '#7dd3fc'
                        ),
                    }">
                    <span class="status-title-text">
                        {{ currentStatus?.name || 'Без статуса' }}
                    </span>
                </button>

                <ul class="dropdown-menu dropdown-menu-end shadow border-0 py-1 status-dropdown-menu">
                    <li v-for="status in statuses" :key="status.id" class="status-dropdown-li">
                        <button
                            class="dropdown-item status-touch-item d-flex align-items-center justify-content-between"
                            type="button" :title="status.name" @click="$emit('change', status)">
                            <span class="d-flex align-items-center text-truncate me-2">
                                <span class="status-dot me-2 flex-shrink-0" :style="{
                                    backgroundColor: formatColor(
                                        status.bg_color,
                                        '#94a3b8'
                                    ),
                                }"></span>

                                <span class="text-truncate status-item-name">
                                    {{ status.name }}
                                </span>
                            </span>

                            <span v-if="status.time"
                                class="badge bg-light text-muted border font-monospace ms-auto flex-shrink-0 status-time-badge">
                                {{ status.time }} дн.
                            </span>
                        </button>
                    </li>
                </ul>
            </div>

            <!-- Только просмотр -->
            <div v-else class="status-readonly-pill" :title="currentStatus?.name || 'Без статуса'" :style="{
                backgroundColor: formatColor(
                    currentStatus?.bg_color,
                    '#f1f5f9'
                ),
                color: formatColor(
                    currentStatus?.text_color,
                    '#475569'
                ),
                borderColor: formatColor(
                    currentStatus?.bg_color,
                    '#cbd5e1'
                ),
            }">
                {{ currentStatus?.name || 'Без статуса' }}
            </div>
        </div>

        <!-- 2. ФИНАНСОВЫЙ СТАТУС (Если ID > 1) -->
        <div v-if="isFinanceStatusVisible" class="status-col flex-grow-1">
            <div v-if="canChangeFinanceStatus" class="dropdown w-100">
                <div class="dropdown w-100">
                    <button
                        class="status-pill-btn dropdown-toggle d-flex align-items-center justify-content-between shadow-sm w-100"
                        type="button" data-bs-toggle="dropdown" aria-expanded="false"
                        :title="currentFinanceStatus?.name || 'Фин. статус'" :style="{
                            backgroundColor: formatColor(currentFinanceStatus?.bg_color, '#e0e7ff'),
                            color: formatColor(currentFinanceStatus?.text_color, '#4338ca'),
                            borderColor: formatColor(currentFinanceStatus?.bg_color, '#c7d2fe'),
                        }">
                        <span class="status-title-text">
                            {{ currentFinanceStatus?.name || 'Фин. статус' }}
                        </span>
                    </button>

                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 py-1 status-dropdown-menu">
                        <li v-for="fStatus in financeStatuses" :key="fStatus.id" class="status-dropdown-li">
                            <button
                                class="dropdown-item status-touch-item d-flex align-items-center justify-content-between"
                                type="button" :title="fStatus.name" @click="$emit('change-finance', fStatus)">
                                <span class="d-flex align-items-center text-truncate me-2">
                                    <span class="status-dot me-2 flex-shrink-0"
                                        :style="{ backgroundColor: formatColor(fStatus.bg_color, '#94a3b8') }"></span>
                                    <span class="text-truncate status-item-name">{{ fStatus.name }}</span>
                                </span>

                                <span v-if="fStatus.time || fStatus.fin_time"
                                    class="badge bg-light text-muted border font-monospace ms-auto flex-shrink-0 status-time-badge">
                                    {{ fStatus.time || fStatus.fin_time }} дн.
                                </span>
                            </button>
                        </li>
                    </ul>
                </div>
            </div>

            <div v-else class="status-readonly-pill" :title="currentFinanceStatus?.name || 'Фин. статус'" :style="{
                backgroundColor: formatColor(
                    currentFinanceStatus?.bg_color,
                    '#e0e7ff'
                ),
                color: formatColor(
                    currentFinanceStatus?.text_color,
                    '#4338ca'
                ),
                borderColor: formatColor(
                    currentFinanceStatus?.bg_color,
                    '#c7d2fe'
                ),
            }">
                {{ currentFinanceStatus?.name || 'Фин. статус' }}
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    currentStatus: { type: Object, default: null },
    statuses: { type: Array, default: () => [] },

    financeStatusId: { type: [Number, String], default: null },
    currentFinanceStatus: { type: Object, default: null },
    financeStatuses: { type: Array, default: () => [] },
    canChangeStatus: {
        type: Boolean,
        default: false,
    },

    canChangeFinanceStatus: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['change', 'change-finance']);

const isFinanceStatusVisible = computed(() => {
    const fId = Number(props.financeStatusId || props.currentFinanceStatus?.id || 0);
    return fId > 1;
});

function formatColor(color, fallback) {
    if (!color) return fallback;
    const trimmed = String(color).trim();
    return trimmed.startsWith('#') || trimmed.startsWith('rgb') ? trimmed : `#${trimmed}`;
}
</script>

<style scoped>
.status-changers-wrapper {
    min-width: 0;
    max-width: 100%;
}

.status-col {
    min-width: 0;
    flex: 1 1 0;
}

/* Компактная кнопка статуса */
.status-pill-btn {
    height: 26px;
    border-radius: 13px;
    padding: 0 6px 0 8px;
    font-size: 0.72rem;
    font-weight: 700;
    border: 1px solid transparent;
    transition: all 0.15s ease-in-out;
    min-width: 0;
}

.status-pill-btn:hover,
.status-pill-btn:focus {
    filter: brightness(0.95);
}

.status-pill-btn::after {
    margin-left: 3px;
    font-size: 0.65rem;
    flex-shrink: 0;
}

.status-title-text {
    display: block;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    line-height: 1;
    text-align: left;
}

.status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
}

/* Общие стили выпадающего меню */
.status-dropdown-menu {
    max-height: 320px;
    overflow-y: auto;
    min-width: 230px;
    z-index: 1060;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.2), 0 8px 10px -6px rgba(0, 0, 0, 0.1) !important;
    border-radius: 8px;
}

.status-touch-item {
    transition: background-color 0.12s ease;
}

.status-time-badge {
    font-size: 0.68rem;
    padding: 2px 5px;
    border-radius: 4px;
}

/* ========================================================= */
/* ДЕСКТОП: КОМПАКТНЫЙ РЕЖИМ (>= 768px)                      */
/* ========================================================= */
@media (min-width: 768px) {
    .status-touch-item {
        padding: 5px 10px;
        font-size: 0.80rem;
        min-height: 30px;
    }
}

/* ========================================================= */
/* МОБИЛЬНЫЙ ВИД: СЕНСОРНЫЙ ТАЧ-РЕЖИМ (< 768px)             */
/* ========================================================= */
@media (max-width: 767.98px) {
    .status-dropdown-menu {
        min-width: 260px;
        max-height: 60vh;
        /* Занимает до 60% высоты экрана смартфона */
        border-radius: 12px;
        padding: 4px 0;
    }

    .status-dropdown-li:not(:last-child) {
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        /* Тонкий разделитель между пунктами */
    }

    /* Высота пункта под палец >= 46px */
    .status-touch-item {
        min-height: 46px;
        padding: 10px 14px;
        font-size: 0.88rem;
        font-weight: 500;
    }

    .status-touch-item:active {
        background-color: #f1f5f9;
    }

    .status-dot {
        width: 10px;
        height: 10px;
    }

    .status-time-badge {
        font-size: 0.72rem;
        padding: 3px 6px;
    }
}

.status-readonly-pill {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    min-width: 0;
    height: 26px;
    padding: 0 8px;
    overflow: hidden;
    border: 1px solid transparent;
    border-radius: 13px;
    font-size: 0.72rem;
    font-weight: 700;
    line-height: 1;
    text-overflow: ellipsis;
    white-space: nowrap;
}
</style>
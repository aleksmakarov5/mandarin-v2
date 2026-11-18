<template>
    <div class="mob-deal-card mb-2 bg-white rounded shadow-sm p-2 position-relative overflow-hidden" :class="{
        'mob-row-overdue': item.is_overdue || item.app_timer?.is_overdue,
        'mob-row-urgent': isItemUrgent(item),
    }" :style="getRowBackgroundStyle(item)" @click="$emit('open', item.id)">
        <!-- 1 СТРОКА: #ID + Марка ТС + Статусы справа -->
        <div class="d-flex justify-content-between align-items-start gap-1 mb-1">
            <div class="d-flex align-items-center gap-1.5 flex-wrap min-w-0">
                <span class="badge bg-dark font-monospace flex-shrink-0">#{{ item.id }}</span>
                <span class="fw-bold text-dark text-truncate mob-car-title" :title="item.CarModel">
                    {{ item.CarModel || 'Авто не указано' }}
                </span>
            </div>

            <!-- Статусы (Основной и Финансовый) -->
            <div class="d-flex flex-column align-items-end gap-1 flex-shrink-0">
                <span class="status-compact-pill text-truncate px-2 py-0.5 text-center" :style="{
                    backgroundColor: item.status?.bg_color || item.status_theme || '#fef08a',
                    color: item.status?.text_color || '#1e293b',
                    maxWidth: '140px',
                }">
                    {{ item.status?.name || item.StatusName || 'Без статуса' }}
                </span>

                <span v-if="hasActiveFinanceStatus(item)"
                    class="status-compact-pill text-truncate px-2 py-0.5 text-center" :style="{
                        backgroundColor: item.finance_status?.bg_color || item.financeStatus?.bg_color || item.soglas_bg || '#dcfce7',
                        color: item.finance_status?.text_color || item.financeStatus?.text_color || item.soglas_color || '#15803d',
                        maxWidth: '140px',
                    }">
                    {{ getFinanceStatusTitle(item) }}
                </span>
            </div>
        </div>

        <!-- 2 СТРОКА: Госномер РФ (ГРЗ) + VIN номер -->
        <div class="d-flex align-items-center gap-2 mb-1.5 flex-wrap">
            <!-- Плашка номера РФ -->
            <div v-if="item.gnz" class="plate-ru flex-shrink-0">
                <span class="plate-number">{{ parsedGnz.number }}</span>
                <div v-if="parsedGnz.region" class="plate-region-box">
                    <span class="plate-region">{{ parsedGnz.region }}</span>
                    <div class="plate-flag-row">
                        <span class="plate-rus-text">RUS</span>
                        <span class="plate-flag">
                            <span class="flag-white"></span>
                            <span class="flag-blue"></span>
                            <span class="flag-red"></span>
                        </span>
                    </div>
                </div>
            </div>

            <!-- VIN номер с копированием по тапу -->
            <div v-if="item.vin" class="mob-vin-badge font-monospace text-uppercase" @click.stop="copyVin(item.vin)">
                <i class="bi bi-upc-scan me-1 text-secondary"></i>
                <span>VIN: {{ item.vin }}</span>
                <i class="bi ms-1" :class="isCopied ? 'bi-check2 text-success' : 'bi-copy text-muted'"></i>
            </div>
        </div>

        <!-- 3 СТРОКА: Клиент (ФИО) -->
        <div class="mob-client-row text-dark mb-1">
            <i class="bi bi-person text-secondary me-1"></i>
            <span class="fw-semibold mob-client-name text-uppercase">
                {{ item.ClientName || 'Клиент не указан' }}
            </span>
        </div>

        <!-- 4 СТРОКА: Номер телефона (если указан) -->
        <div v-if="item.ClientPhone" class="mob-phone-row mb-1">
            <a :href="'tel:' + item.ClientPhone"
                class="mob-phone-link d-inline-flex align-items-center text-decoration-none text-primary fw-medium text-nowrap"
                @click.stop>
                <i class="bi bi-telephone-fill me-1 text-success" style="font-size: 0.70rem;"></i>
                <span class="phone-text font-monospace">{{ item.ClientPhone }}</span>
            </a>
        </div>

        <!-- 5 СТРОКА: Юрист (если назначен) -->
        <div v-if="item.lawyer?.name || item.id_lawyer" class="mob-lawyer-row mb-1 small text-muted">
            <span>Юрист: </span>
            <span class="badge bg-light text-dark border">{{ item.lawyer?.name || 'Назначен' }}</span>
        </div>

        <!-- 6 СТРОКА: Футер (Убыток, СК, Филиал, Дата) -->
        <div class="mob-details-grid d-flex flex-wrap align-items-center justify-content-between gap-1 pt-1 border-top text-muted"
            style="font-size: 0.68rem;">
            <div class="text-nowrap text-secondary">
                <span v-if="item.numberInsuranceCompanyApplication || item.ub_number">
                    Убыток: {{ item.numberInsuranceCompanyApplication || item.ub_number }}
                </span>
            </div>

            <span class="badge-sk text-nowrap">
                {{ item.insurance_company?.name || 'ИНГОС' }}
            </span>

            <div class="text-truncate" style="max-width: 130px;">
                <i class="bi bi-geo-alt me-0.5"></i>{{ item.our_company?.name || item.city?.name || 'СТОА' }}
            </div>

            <div class="text-nowrap text-end font-monospace">
                {{ formatDate(item.created_at) }}
            </div>
        </div>

        <!-- 7 СТРОКА: Финансовый блок (если включен) -->
        <div v-if="hasFinancialColumns && item.sum_sk"
            class="mob-fin-bar mt-1 pt-1 border-top d-flex justify-content-between align-items-center small"
            style="font-size: 0.70rem;">
            <span>Сумма СК: <strong>{{ formatMoney(item.sum_sk) }} р.</strong></span>
            <span class="text-success fw-bold">Остаток: {{ formatMoney(item.remains) }} ({{ item.remains_percent
                }}%)</span>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useRowStyles } from '../../../composables/useRowStyles';

const props = defineProps({
    item: {
        type: Object,
        required: true,
    },
    hasFinancialColumns: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['open']);

const { getFinanceStatusTitle, hasActiveFinanceStatus, isItemUrgent, getRowBackgroundStyle } = useRowStyles();

const isCopied = ref(false);

const copyVin = (vin) => {
    if (!vin) return;
    navigator.clipboard.writeText(vin).then(() => {
        isCopied.value = true;
        setTimeout(() => {
            isCopied.value = false;
        }, 1500);
    });
};

// Парсинг госномера (ГРЗ РФ) с поддержкой латиницы и кириллицы
const parsedGnz = computed(() => {
    const raw = props.item?.gnz;
    if (!raw) return { number: '', region: '' };

    // Приводим к верхнему регистру, меняем частые латинские дубли на кириллицу
    let str = String(raw).trim().toUpperCase().replace(/\s+/g, '');
    const map = { 'A': 'А', 'B': 'В', 'E': 'Е', 'K': 'К', 'M': 'М', 'H': 'Н', 'O': 'О', 'P': 'Р', 'C': 'С', 'T': 'Т', 'Y': 'У', 'X': 'Х' };
    str = str.replace(/[ABEKMHOPCTYX]/g, m => map[m] || m);

    const match = str.match(/^([АВЕКМНОРСТУХ]\d{3}[АВЕКМНОРСТУХ]{2})(\d{2,3})$/);
    if (match) {
        const numPart = match[1];
        return {
            number: `${numPart[0]} ${numPart.slice(1, 4)} ${numPart.slice(4, 6)}`,
            region: match[2],
        };
    }
    return { number: String(raw).toUpperCase(), region: '' };
});

const formatDate = (val) => {
    if (!val) return '—';
    const d = new Date(val);
    return d.toLocaleDateString('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric' });
};

const formatMoney = (val) => {
    const n = parseFloat(val) || 0;
    return n.toLocaleString('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};
</script>

<style scoped>
.mob-deal-card {
    cursor: pointer;
    border: 1px solid #000000 !important;
    border-left: 5px solid #299bd1 !important;
    transition: transform 0.08s ease;
    line-height: 1.25;
}

.mob-deal-card:active {
    transform: scale(0.99);
}

.mob-row-urgent {
    border-left-color: #dc2626 !important;
    box-shadow: 0 0 0 1px #dc2626 !important;
}

.mob-row-overdue {
    border-left-color: #b91c1c !important;
}

.mob-car-title {
    font-size: 0.82rem;
    letter-spacing: -0.01em;
}

/* Настоящий госномер РФ (ГРЗ) */
.plate-ru {
    display: inline-flex;
    align-items: center;
    background-color: #ffffff;
    color: #111827;
    border: 1.5px solid #000000;
    border-radius: 3px;
    height: 20px;
    padding: 0 3px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.12);
    user-select: none;
    line-height: 1;
    flex-shrink: 0;
    white-space: nowrap;
}

.plate-number {
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.5px;
    padding: 0 3px 0 1px;
    text-transform: uppercase;
    color: #000000;
    white-space: nowrap;
}

.plate-region-box {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border-left: 1.5px solid #000000;
    padding-left: 2px;
    padding-right: 1px;
    height: 100%;
    min-width: 18px;
}

.plate-region {
    font-size: 0.60rem;
    font-weight: 800;
    line-height: 1;
    color: #000000;
    letter-spacing: -0.2px;
}

.plate-flag-row {
    display: flex;
    align-items: center;
    gap: 1.5px;
    line-height: 1;
    margin-top: 1px;
}

.plate-rus-text {
    font-size: 0.42rem;
    font-weight: 700;
    color: #000000;
    line-height: 1;
}

.plate-flag {
    display: flex;
    flex-direction: column;
    width: 5px;
    height: 3px;
    border: 0.5px solid #64748b;
}

.flag-white {
    background-color: #ffffff;
    height: 1px;
}

.flag-blue {
    background-color: #0039a6;
    height: 1px;
}

.flag-red {
    background-color: #d52b1e;
    height: 1px;
}

/* VIN бейдж */
.mob-vin-badge {
    display: inline-flex;
    align-items: center;
    font-size: 0.68rem;
    background-color: #f8fafc;
    border: 1px solid #000000;
    border-radius: 3px;
    padding: 1px 5px;
    color: #0f172a;
    user-select: none;
    cursor: pointer;
}

.mob-vin-badge:active {
    background-color: #e2e8f0;
}

/* ФИО Клиента */
.mob-client-row {
    font-size: 0.76rem;
    line-height: 1.25;
}

.mob-client-name {
    word-break: break-word;
    white-space: normal;
    display: inline;
}

/* Номер телефона */
.mob-phone-link {
    font-size: 0.75rem;
    padding: 2px 7px;
    background-color: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 4px;
}

.phone-text {
    white-space: nowrap;
    letter-spacing: -0.1px;
}

/* Статусы */
.status-compact-pill {
    display: block;
    font-size: 0.64rem !important;
    font-weight: 600;
    padding: 1px 4px;
    border-radius: 2px;
    line-height: 1.2;
    border: 1px solid #000000;
}

.badge-sk {
    font-size: 0.63rem !important;
    font-weight: 700;
    padding: 1px 4px;
    background-color: #fce7f3;
    color: #be185d;
    border-radius: 3px;
    border: 1px solid #000000;
}
</style>
<template>
    <div class="mob-deal-card mb-2 bg-white rounded shadow-sm p-2 position-relative overflow-hidden" :class="{
        'mob-row-overdue': item.is_overdue || item.app_timer?.is_overdue,
        'mob-row-urgent': isItemUrgent(item),
    }" :style="getRowBackgroundStyle(item)" @click="$emit('open', item.id)">
        <!-- 1 СТРОКА: #ID + Марка ТС + Статусы дела и финансов -->
        <div class="d-flex justify-content-between align-items-start gap-1 mb-1">
            <div class="d-flex align-items-center gap-1.5 flex-wrap min-w-0">
                <span class="badge bg-dark font-monospace flex-shrink-0">#{{ item.id }}</span>
                <span class="fw-bold text-dark text-truncate mob-car-title" :title="item.CarModel">
                    {{ item.CarModel || 'Авто не указано' }}
                </span>
            </div>

            <!-- Статусы справа -->
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

        <!-- 2 СТРОКА: Госномер РФ (ГРЗ) + VIN-номер с копированием -->
        <div class="d-flex align-items-center gap-2 mb-1.5 flex-wrap">
            <!-- Плашка госномера РФ -->
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

            <!-- VIN-номер с кнопкой копирования в буфер -->
            <div v-if="item.vin" class="mob-vin-badge font-monospace text-uppercase"
                title="Нажмите, чтобы скопировать VIN" @click.stop="copyVin(item.vin)">
                <i class="bi bi-upc-scan me-1 text-secondary"></i>
                <span>VIN: {{ item.vin }}</span>
                <i class="bi ms-1" :class="isCopied ? 'bi-check2 text-success' : 'bi-copy text-muted'"></i>
            </div>
        </div>

        <!-- 3 СТРОКА: Клиент или Представитель -->
        <div class="mob-client-row text-dark mb-1">
            <i class="bi bi-person text-secondary me-1"></i>
            <span class="fw-semibold mob-client-name text-uppercase">
                <span v-if="isRepresentative" class="text-primary me-1 fw-bold">Представитель:</span>
                {{ displayName }}
            </span>
        </div>

        <!-- 4 СТРОКА: Номер телефона (кликабельный) -->
        <div v-if="displayPhone" class="mob-phone-row mb-1">
            <a :href="'tel:' + cleanPhone(displayPhone)"
                class="mob-phone-link d-inline-flex align-items-center text-decoration-none text-primary fw-medium text-nowrap"
                @click.stop>
                <i class="bi bi-telephone-fill me-1 text-success" style="font-size: 0.70rem;"></i>
                <span class="phone-text font-monospace">{{ displayPhone }}</span>
            </a>
        </div>

        <!-- 5 СТРОКА: Юрист -->
        <div v-if="hasLawyer" class="d-flex align-items-center gap-1 mb-1" style="font-size: 0.72rem;">
            <span class="text-muted">Юрист:</span>
            <ApplicationLawyerBadge :item="item" />
        </div>

        <!-- 6 СТРОКА: Подвал (№ убытка, СК, СТОА, Дата) -->
        <div class="mob-details-grid d-flex flex-wrap align-items-center justify-content-between gap-1 pt-1 border-top text-muted"
            style="font-size: 0.68rem;">
            <div class="text-nowrap text-secondary">
                <span v-if="item.numberInsuranceCompanyApplication || item.ub_number">
                    Убыток: {{ item.numberInsuranceCompanyApplication || item.ub_number }}
                </span>
            </div>

            <span class="badge-sk cell-truncate d-inline-block" :style="{
                backgroundColor: item.insurance_company?.bg_color || item.insuranceCompany?.bg_color || '#fce7f3',
                color: item.insurance_company?.text_color || item.insuranceCompany?.text_color || '#be185d',
                border: '1px solid ' + (item.insurance_company?.text_color || item.insuranceCompany?.text_color || '#be185d') + '44'
            }">
                {{ item.insurance_company?.name || item.insuranceCompany?.name || '—' }}
            </span>

            <div class="text-truncate" style="max-width: 130px;">
                <i class="bi bi-geo-alt me-0.5"></i>{{ item.our_company?.name || item.city?.name || 'СТОА' }}
            </div>

            <div class="text-nowrap text-end font-monospace">
                {{ formatDate(item.created_at) }}
            </div>
        </div>

        <!-- 7 СТРОКА: Финансовый блок (если включены финансовые колонки) -->
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
import ApplicationLawyerBadge from '../ApplicationLawyerBadge.vue';

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

// ==========================================
// ЛОГИКА ФИНАНСОВОГО СТАТУСА И СТИЛЕЙ
// ==========================================
const getFinanceStatusTitle = (item) => {
    if (!item) return '';
    const raw =
        item.finance_status_name ||
        item.finance_status?.name ||
        item.financeStatus?.name ||
        item.fin_status?.name ||
        item.FinStatusName ||
        item.soglas_title ||
        '';
    const trimmed = String(raw).trim();
    if (!trimmed || trimmed.toLowerCase() === 'не определен' || trimmed.toLowerCase() === 'не определено') {
        return '';
    }
    return trimmed;
};

const hasActiveFinanceStatus = (item) => {
    if (!item) return false;
    const finId = Number(
        item.finance_status_id ||
        item.finance_statuses_id ||
        item.id_finance_status ||
        item.finance_status?.id ||
        item.financeStatus?.id ||
        0
    );
    const title = getFinanceStatusTitle(item);
    if (!title) return false;
    if (finId === 1) return false;
    return true;
};

const isItemUrgent = (item) => {
    if (!item) return false;
    const isAppArchive = Number(item.id_status || item.status_id || item.status?.id || 0) === 25;
    const isAppOverdue = !isAppArchive && Boolean(item.app_timer?.is_overdue || item.is_overdue);
    const isAppUrgent = !isAppArchive && !isAppOverdue && Boolean(
        item.app_timer?.has_timer &&
        (item.app_timer?.is_day === 0 || item.app_timer?.is_day === false) &&
        Number(item.app_timer?.count_hour ?? item.count_hour ?? 0) > 0
    );

    const finTitle = getFinanceStatusTitle(item).toLowerCase();
    const isFinPaid = finTitle.includes('оплата получена') || finTitle.includes('оплачен');
    const isFinOverdue = !isFinPaid && Boolean(item.finance_timer?.is_overdue || item.soglas_overdue);
    const isFinUrgent = hasActiveFinanceStatus(item) && !isFinPaid && !isFinOverdue && Boolean(
        item.finance_timer?.has_timer &&
        (item.finance_timer?.is_day === 0 || item.finance_timer?.is_day === false) &&
        Number(item.finance_timer?.count_hour_f ?? item.count_hour_f ?? 0) > 0
    );

    return isAppUrgent || isFinUrgent;
};

const getRowBackgroundStyle = (item) => {
    if (!item) return {};
    const BLUE_COLOR = 'rgba(37, 99, 235, 0.14)';
    const GREEN_COLOR = 'rgba(22, 163, 74, 0.16)';
    const YELLOW_COLOR = 'rgba(234, 179, 8, 0.28)';
    const PASTEL_PINK = 'rgba(254, 226, 226, 0.45)';
    const OVERDUE_RED = 'rgba(248, 113, 113, 0.38)';

    const isAppArchive = Number(item.id_status || item.status_id || item.status?.id || 0) === 25;
    const isAppOverdue = !isAppArchive && Boolean(item.app_timer?.is_overdue || item.is_overdue);
    const isAppUrgent = !isAppArchive && !isAppOverdue && Boolean(
        item.app_timer?.has_timer && (item.app_timer?.is_day === 0 || item.app_timer?.is_day === false)
    );

    let appFill = isAppUrgent ? YELLOW_COLOR : BLUE_COLOR;
    let appBg = '';

    if (isAppArchive) {
        appBg = `linear-gradient(to right, ${GREEN_COLOR} 0%, ${GREEN_COLOR} 100%)`;
    } else if (isAppOverdue) {
        appBg = `linear-gradient(to right, ${OVERDUE_RED} 0%, ${OVERDUE_RED} 100%)`;
    } else {
        const appWidth = Math.min(100, Math.max(0, Number(item.app_timer?.percent ?? 0)));
        appBg = `linear-gradient(to right, ${appFill} 0%, ${appFill} ${appWidth}%, ${PASTEL_PINK} ${appWidth}%, ${PASTEL_PINK} 100%)`;
    }

    if (!hasActiveFinanceStatus(item)) {
        return { background: `${appBg} top / 100% 100% no-repeat` };
    }

    const finTitle = getFinanceStatusTitle(item).toLowerCase();
    const finId = Number(item.finance_status_id || item.finance_statuses_id || 0);
    const isFinPaid = finId === 7 || finTitle.includes('оплата получена') || finTitle.includes('оплачен');
    const isFinOverdue = !isFinPaid && Boolean(item.finance_timer?.is_overdue || item.soglas_overdue);
    const isFinUrgent = !isFinPaid && !isFinOverdue && Boolean(
        item.finance_timer?.has_timer && (item.finance_timer?.is_day === 0 || item.finance_timer?.is_day === false)
    );

    let finFill = isFinUrgent ? YELLOW_COLOR : GREEN_COLOR;
    let finBg = '';

    if (isFinPaid) {
        finBg = `linear-gradient(to right, ${GREEN_COLOR} 0%, ${GREEN_COLOR} 100%)`;
    } else if (isFinOverdue) {
        finBg = `linear-gradient(to right, ${OVERDUE_RED} 0%, ${OVERDUE_RED} 100%)`;
    } else {
        const finWidth = Math.min(100, Math.max(0, Number(item.finance_timer?.percent ?? 0)));
        finBg = `linear-gradient(to right, ${finFill} 0%, ${finFill} ${finWidth}%, ${PASTEL_PINK} ${finWidth}%, ${PASTEL_PINK} 100%)`;
    }

    return {
        background: `
      ${appBg} top / 100% 50% no-repeat,
      ${finBg} bottom / 100% 50% no-repeat
    `,
    };
};

// ==========================================
// ПРЕДСТАВИТЕЛЬ / КЛИЕНТ
// ==========================================
const isRepresentative = computed(() => {
    return Boolean(props.item?.pred && String(props.item.pred).trim());
});

const displayName = computed(() => {
    if (isRepresentative.value) {
        return props.item.pred;
    }
    return props.item?.ClientName || 'Клиент не указан';
});

const displayPhone = computed(() => {
    if (isRepresentative.value && props.item?.pred_phone) {
        return props.item.pred_phone;
    }
    return props.item?.ClientPhone || '';
});

// Юрист
const hasLawyer = computed(() => {
    const lid = Number(props.item?.id_lawyer || props.item?.lawyer_id || props.item?.lawyer?.id || 0);
    return lid > 0 || Boolean(props.item?.lawyer?.name);
});

// Парсинг ГРЗ РФ с заменой латинских букв
const parsedGnz = computed(() => {
    const raw = props.item?.gnz;
    if (!raw) return { number: '', region: '' };

    let str = String(raw).trim().toUpperCase().replace(/\s+/g, '');
    const map = { A: 'А', B: 'В', E: 'Е', K: 'К', M: 'М', H: 'Н', O: 'О', P: 'Р', C: 'С', T: 'Т', Y: 'У', X: 'Х' };
    str = str.replace(/[ABEKMHOPCTYX]/g, (m) => map[m] || m);

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

const cleanPhone = (val) => {
    if (!val) return '';
    return String(val).replace(/\D/g, '');
};

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

/* Государственный регистрационный знак (ГРЗ РФ) */
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

/* VIN-бейдж */
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

/* ФИО Клиента / Представителя */
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
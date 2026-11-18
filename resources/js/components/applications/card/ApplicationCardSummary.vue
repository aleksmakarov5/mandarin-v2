<template>
    <section class="application-card-summary bg-white border-bottom flex-shrink-0">
        <div v-if="hasData" class="row g-2 align-items-center">
            <!-- 1. Автомобиль -->
            <div class="col-12 col-md-6 col-lg-3">
                <div class="summary-primary">
                    <i class="bi bi-car-front-fill text-primary"></i>
                    <span class="fw-bold text-truncate"
                        :title="application?.CarModel || application?.car_model || 'Автомобиль не указан'">
                        {{ application?.CarModel || application?.car_model || 'Автомобиль не указан' }}
                    </span>

                    <span v-if="application?.gnz || application?.car_number" class="car-plate ms-2">
                        <span class="car-plate__main">{{ plateParts.main }}</span>
                        <span v-if="plateParts.region" class="car-plate__region">
                            <span class="car-plate__code">{{ plateParts.region }}</span>
                            <span class="car-plate__flag">
                                <span class="car-plate__country">RUS</span>
                                <span class="car-plate__tricolor" aria-hidden="true">
                                    <span class="tr-white"></span>
                                    <span class="tr-blue"></span>
                                    <span class="tr-red"></span>
                                </span>
                            </span>
                        </span>
                    </span>
                </div>
                <div class="summary-secondary">VIN: {{ application?.vin || '—' }}</div>
            </div>

            <!-- 2. Клиент / Представитель -->
            <div class="col-12 col-md-6 col-lg-3">
                <div class="summary-primary">
                    <i class="bi bi-person-fill text-success"></i>
                    <span class="fw-semibold text-truncate">
                        {{ isRepresentative ? 'Представитель: ' : '' }}{{ clientNameDisplay }}
                    </span>
                </div>
                <div class="summary-secondary">
                    <a v-if="phoneLink" :href="phoneLink" class="text-decoration-none text-muted">
                        <i class="bi bi-telephone-fill me-1"></i>
                        {{ displayPhone }}
                    </a>
                    <span v-else>Телефон не указан</span>
                </div>
            </div>

            <!-- 3. Страховая, Тип убытка, ДТП и Дата создания -->
            <div class="col-12 col-md-6 col-lg-3">
                <div class="summary-primary">
                    <i
                        class="bi bi-shield-check text-warning"></i><!-- Название СК с темой (цветом фона и текста из базы данных) -->
                    <span class="badge badge-insurance-themed text-truncate" :style="insuranceBadgeStyle"
                        :title="insuranceName">
                        {{ insuranceName }}
                    </span>
                    <!-- Бейдж типа убытка -->
                    <span v-if="ubTypeText" class="badge ms-2" :class="ubTypeBadgeClass">
                        {{ ubTypeText }}
                    </span>
                    <!-- Дата создания (светлым цветом и курсивом) -->
                    <span v-if="application?.created_at" class="fst-italic opacity-75 ms-1"
                        :title="'Дата создания: ' + formatDate(application?.created_at)">
                        Заявка: {{ formatDate(application?.created_at) }}
                    </span>
                </div>
                <div class="summary-secondary d-flex flex-wrap align-items-center gap-1">
                    <span>№ {{ lossNumber }}</span>
                    <span class="mx-1">·</span>
                    <span>ДТП: {{ formatDate(application?.DTPdate) }}</span>

                </div>
            </div>

            <!-- 4. Статусы и Тайм-бары -->
            <div class="col-12 col-md-6 col-lg-3 d-flex justify-content-lg-end align-items-center">
                <ApplicationStatusChanger :current-status="currentStatus" :statuses="allStatuses"
                    :app-timer="appTimerData" :finance-status-id="financeStatusId"
                    :current-finance-status="currentFinanceStatus" :finance-statuses="allFinanceStatuses"
                    :finance-timer="financeTimerData" @change="handleStatusChange"
                    @change-finance="handleFinanceStatusChange" />
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed } from 'vue';
import ApplicationStatusChanger from './ApplicationStatusChanger.vue';

// Находим объект текущей СК из справочника (или берём из props.application)
const currentInsuranceObj = computed(() => {
    const icId = Number(
        props.application?.id_insuranceCompany ||
        props.application?.insurance_company_id ||
        props.application?.insurance_company?.id ||
        0
    );

    if (icId && Array.isArray(props.insuranceCompanies)) {
        const found = props.insuranceCompanies.find((i) => Number(i.id) === icId);
        if (found) return found;
    }

    return props.application?.insurance_company || props.application?.insuranceCompany || null;
});

// Название СК
const insuranceName = computed(() => {
    return (
        currentInsuranceObj.value?.name ||
        props.insuranceName ||
        props.application?.insurance_company?.name ||
        'Страховая не выбрана'
    );
});

// Вычисление стиля темы СК (bg_color и text_color из БД с безопасным фоллбеком)
const insuranceBadgeStyle = computed(() => {
    const bg = currentInsuranceObj.value?.bg_color || '#fce7f3';
    const text = currentInsuranceObj.value?.text_color || '#be185d';

    return {
        backgroundColor: bg.startsWith('#') || bg.startsWith('rgb') ? bg : `#${bg}`,
        color: text.startsWith('#') || text.startsWith('rgb') ? text : `#${text}`,
        border: '1px solid rgba(0, 0, 0, 0.12)',
    };
});

const props = defineProps({
    application: { type: Object, default: () => ({}) },
    currentStatus: { type: Object, default: null },
    allStatuses: { type: Array, default: () => [] },
    allFinanceStatuses: { type: Array, default: () => [] },
    insuranceName: { type: String, default: 'Страховая компания не выбрана' },
    insuranceCompanies: { type: Array, default: () => [] },
});

const emit = defineEmits(['status-change', 'finance-status-change']);

const hasData = computed(() => Object.keys(props.application || {}).length > 0);

// ID финансового статуса
const financeStatusId = computed(() => {
    const val = props.application?.finance_status_id
        ?? props.application?.finance_statuses_id
        ?? props.application?.id_finance_status;
    return val !== null && val !== undefined ? Number(val) : 0;
});

// Нахождение объекта финансового статуса
const currentFinanceStatus = computed(() => {
    if (!financeStatusId.value || !Array.isArray(props.allFinanceStatuses)) {
        return null;
    }
    return props.allFinanceStatuses.find(
        (item) => Number(item?.id) === financeStatusId.value
    ) || null;
});

function handleStatusChange(status) {
    if (status) {
        emit('status-change', status);
    }
}

function handleFinanceStatusChange(status) {
    if (status) {
        emit('finance-status-change', status);
    }
}

const lossNumber = computed(() => {
    return props.application?.numberInsuranceCompanyApplication
        || props.application?.ub_number
        || props.application?.insurance_app_number
        || 'б/н';
});

// Проверка: Представитель или Клиент
const isRepresentative = computed(() => {
    return Boolean(props.application?.pred || props.application?.pred_phone);
});

const clientNameDisplay = computed(() => {
    if (isRepresentative.value) {
        return props.application?.pred || 'Представитель (без имени)';
    }
    return props.application?.ClientName || props.application?.client_name || 'Клиент не указан';
});

const displayPhone = computed(() => {
    return isRepresentative.value
        ? (props.application?.pred_phone || props.application?.ClientPhone)
        : (props.application?.ClientPhone || props.application?.client_phone);
});

const phoneLink = computed(() => {
    const phone = displayPhone.value;
    if (!phone) return null;
    let digits = String(phone).replace(/\D/g, '');
    if (!digits) return null;
    if (digits.length === 10) digits = '7' + digits;
    if (digits.startsWith('8')) digits = '7' + digits.slice(1);
    return `tel:+${digits}`;
});

// Тип убытка
const ubTypeText = computed(() => {
    const raw = props.application?.ub_type ?? props.application?.loss_type ?? props.application?.type_loss;
    if (raw === null || raw === undefined || raw === '') return 'ОСАГО';

    const str = String(raw).trim().toLowerCase();
    if (str === '1' || str === 'каско' || str === 'casco' || str === '2') {
        return 'КАСКО';
    }
    return 'ОСАГО';
});

const ubTypeBadgeClass = computed(() => {
    return ubTypeText.value === 'КАСКО' ? 'bg-primary' : 'bg-success';
});

// Безопасный сбор данных таймеров
const appTimerData = computed(() => {
    if (props.application?.app_timer && typeof props.application.app_timer === 'object') {
        return props.application.app_timer;
    }
    return {
        max_hour: props.application?.max_hour ?? props.currentStatus?.time ?? 0,
        count_hour: props.application?.count_hour ?? props.application?.current_hour ?? 0,
        is_overdue: Boolean(props.application?.is_overdue),
        is_day: Boolean(props.application?.day || props.application?.is_day || props.currentStatus?.is_day),
    };
});

const financeTimerData = computed(() => {
    if (props.application?.finance_timer && typeof props.application.finance_timer === 'object') {
        return props.application.finance_timer;
    }
    return {
        max_hour_f: props.application?.max_hour_f ?? props.currentFinanceStatus?.time ?? 0,
        count_hour_f: props.application?.count_hour_f ?? props.application?.count_hour ?? 0,
        is_overdue: Boolean(Number(props.application?.count_hour_f) < 0),
        is_day: Boolean(props.currentFinanceStatus?.is_day),
    };
});

function formatDate(value) {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return String(value).substring(0, 10);
    }
    return date.toLocaleDateString('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
}

const plateParts = computed(() => {
    const raw = String(props.application?.gnz || props.application?.car_number || '').trim();
    if (!raw) return { main: '', region: '' };

    const cleaned = raw.replace(/\s+/g, '');
    const match = cleaned.match(/^([а-яА-Яa-zA-Z]\d{3}[а-яА-Яa-zA-Z]{2})(\d{2,3})$/i);

    if (match) {
        return {
            main: match[1].toUpperCase(),
            region: match[2],
        };
    }

    return {
        main: raw.toUpperCase(),
        region: '',
    };
});
</script>

<style scoped>
.application-card-summary {
    padding: 8px 14px;
    font-size: 0.78rem;
}

.summary-primary {
    display: flex;
    align-items: center;
    gap: 6px;
    min-width: 0;
}

.summary-secondary {
    margin-top: 2px;
    margin-left: 20px;
    color: #64748b;
    font-size: 0.72rem;
}

.car-plate {
    display: inline-flex;
    align-items: stretch;
    height: 22px;
    background: #ffffff;
    border: 1.5px solid #111827;
    border-radius: 3px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", monospace;
    font-weight: 800;
    line-height: 1;
    color: #111827;
    user-select: none;
    vertical-align: middle;
    flex-shrink: 0;
}

.car-plate__main {
    display: flex;
    align-items: center;
    padding: 0 4px;
    font-size: 11.5px;
    letter-spacing: 0.5px;
    font-weight: 800;
}

.car-plate__region {
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    border-left: 1.5px solid #111827;
    padding: 1px 3px;
    min-width: 20px;
    background: #fdfdfd;
}

.car-plate__code {
    font-size: 9.5px;
    line-height: 9.5px;
    font-weight: 800;
}

.car-plate__flag {
    display: flex;
    align-items: center;
    gap: 2px;
    line-height: 1;
    margin-top: 1px;
}

.car-plate__country {
    font-size: 5.5px;
    font-weight: 800;
    color: #111827;
}

.car-plate__tricolor {
    display: inline-flex;
    flex-direction: column;
    width: 6px;
    height: 3.5px;
    border: 0.5px solid #9ca3af;
    border-radius: 0.5px;
    overflow: hidden;
}

.car-plate__tricolor .tr-white {
    background: #ffffff;
    flex: 1;
}

.car-plate__tricolor .tr-blue {
    background: #0039a6;
    flex: 1;
}

.car-plate__tricolor .tr-red {
    background: #d52b1e;
    flex: 1;
}

@media (max-width: 767.98px) {
    .application-card-summary {
        padding: 6px 10px;
    }
}

.badge-insurance-themed {
    font-size: 0.70rem !important;
    font-weight: 700 !important;
    padding: 2px 7px !important;
    border-radius: 4px !important;
    max-width: 140px;
    display: inline-block;
    vertical-align: middle;
    letter-spacing: 0.2px;
}
</style>
<template>
    <section class="application-card-summary bg-white border-bottom flex-shrink-0">
        <div v-if="hasData" class="row g-2 align-items-center">
            <!-- 1. Автомобиль -->
            <div class="col-12 col-md-6 col-lg-3">
                <div class="summary-primary">
                    <i class="bi bi-car-front-fill text-primary"></i>
                    <span class="fw-bold text-truncate">
                        {{ application.CarModel || 'Автомобиль не указан' }}
                    </span>

                    <span v-if="application.gnz" class="car-plate ms-2">
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
                <div class="summary-secondary">VIN: {{ application.vin || '—' }}</div>
            </div>

            <!-- 2. Клиент / Представитель -->
            <div class="col-12 col-md-6 col-lg-3">
                <div class="summary-primary">
                    <i class="bi bi-person-fill text-success"></i>
                    <span class="fw-semibold text-truncate">
                        {{ isRepresentative ? 'Представитель:' : '' }}
                        {{ clientNameDisplay }}
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

            <!-- 3. Страховая и Тип убытка -->
            <div class="col-12 col-md-6 col-lg-3">
                <div class="summary-primary">
                    <i class="bi bi-shield-check text-warning"></i>
                    <span class="text-truncate">{{ insuranceName }}</span>
                    <!-- Бейдж типа убытка -->
                    <span class="badge ms-2" :class="ubTypeBadgeClass">
                        {{ ubTypeText }}
                    </span>
                </div>
                <div class="summary-secondary">
                    № {{ lossNumber }} <span class="mx-1">·</span> ДТП: {{ formatDate(application.DTPdate) }}
                </div>
            </div>
            <!-- 4. НОВЫЙ БЛОК: Статус -->
            <!-- Внутри ApplicationCardSummary.vue -->
            <div class="col-12 col-md-6 col-lg-3 d-flex justify-content-xl-end align-items-center">
                <ApplicationStatusChanger :current-status="currentStatus" :statuses="allStatuses"
                    :finance-status-id="financeStatusId" :current-finance-status="currentFinanceStatus"
                    :finance-statuses="allFinanceStatuses" @change="handleStatusChange"
                    @change-finance="handleFinanceStatusChange" />
            </div>

        </div>
    </section>
</template>

<script setup>
import { computed } from 'vue';
import ApplicationStatusChanger from './ApplicationStatusChanger.vue';

const props = defineProps({
    application: { type: Object, default: () => ({}) },
    currentStatus: { type: Object, default: null },
    allStatuses: { type: Array, default: () => [] },
    allFinanceStatuses: { type: Array, default: () => [] }, // Добавили список фин. статусов
    insuranceName: { type: String, default: 'Страховая компания не выбрана' },
});

const emit = defineEmits(['status-change', 'finance-status-change']);

// Определяем ID финансового статуса дела
const financeStatusId = computed(() => {
    const val = props.application?.finance_status_id
        ?? props.application?.finance_statuses_id
        ?? props.application?.id_finance_status;
    return val !== null && val !== undefined ? Number(val) : 0;
});

// Находим текущий объект финансового статуса из списка
const currentFinanceStatus = computed(() => {
    if (!financeStatusId.value || !Array.isArray(props.allFinanceStatuses)) {
        return null;
    }
    return props.allFinanceStatuses.find(
        (item) => Number(item.id) === financeStatusId.value
    ) || null;
});

function handleStatusChange(status) {
    emit('status-change', status);
}

function handleFinanceStatusChange(status) {
    emit('finance-status-change', status);
}

const hasData = computed(() => Object.keys(props.application || {}).length > 0);
const lossNumber = computed(() => props.application.numberInsuranceCompanyApplication || props.application.ub_number || 'б/н');

// Логика выбора Представитель / Клиент
const isRepresentative = computed(() => !!props.application.pred);
const clientNameDisplay = computed(() => props.application.pred || props.application.ClientName || 'Клиент не указан');
const displayPhone = computed(() => isRepresentative.value ? props.application.pred_phone : props.application.ClientPhone);

const phoneLink = computed(() => {
    const phone = displayPhone.value;
    if (!phone) return null;
    const digits = String(phone).replace(/\D/g, '');
    return digits ? `tel:+${digits}` : null;
});

// Логика бейджа типа убытка (0 = ОСАГО, 1 = КАСКО)
const ubTypeText = computed(() => props.application.ub_type == 1 ? 'КАСКО' : 'ОСАГО');
const ubTypeBadgeClass = computed(() => props.application.ub_type == 1 ? 'bg-primary' : 'bg-success');

function formatDate(value) {
    if (!value) return '—';
    const date = new Date(`${String(value).substring(0, 10)}T00:00:00`);
    return Number.isNaN(date.getTime()) ? '—' : date.toLocaleDateString('ru-RU');
}

const plateParts = computed(() => {
    const raw = String(props.application?.gnz || '').trim();
    if (!raw) return { main: '', region: '' };

    // Стандартный формат РФ: А123ВС77 или А 123 ВС 777
    const cleaned = raw.replace(/\s+/g, '');
    const match = cleaned.match(/^([а-яА-Яa-zA-Z]\d{3}[а-яА-Яa-zA-Z]{2})(\d{2,3})$/i);

    if (match) {
        return {
            main: match[1].toUpperCase(),
            region: match[2],
        };
    }

    // Если формат нестандартный — выводим целиком слева, регион пустой
    return {
        main: raw.toUpperCase(),
        region: '',
    };
});
</script>
<style scoped>
.car-plate {
    display: inline-flex;
    align-items: stretch;
    height: 26px;
    background: #ffffff;
    border: 1.5px solid #111827;
    border-radius: 4px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", monospace;
    font-weight: 800;
    line-height: 1;
    color: #111827;
    user-select: none;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12);
    vertical-align: middle;
    flex-shrink: 0;
    margin-left: 0.65rem;
    /* Заметный отступ от марки и модели */
}

/* Основная серия и номер (буква-цифры-буквы) */
.car-plate__main {
    display: flex;
    align-items: center;
    padding: 0 6px;
    font-size: 13.5px;
    letter-spacing: 0.8px;
    font-weight: 800;
}

/* Правая секция региона */
.car-plate__region {
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    border-left: 1.5px solid #111827;
    padding: 1px 4px 1px 3px;
    min-width: 24px;
    background: #fdfdfd;
}

/* Цифры региона */
.car-plate__code {
    font-size: 11px;
    line-height: 11px;
    font-weight: 800;
    letter-spacing: -0.2px;
}

/* Нижняя строчка с кодом страны и триколором */
.car-plate__flag {
    display: flex;
    align-items: center;
    gap: 2.5px;
    line-height: 1;
    margin-top: 1.5px;
}

.car-plate__country {
    font-size: 6.5px;
    font-weight: 800;
    letter-spacing: -0.2px;
    color: #111827;
}

/* Флаг РФ */
.car-plate__tricolor {
    display: inline-flex;
    flex-direction: column;
    width: 7px;
    height: 4.5px;
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
</style>
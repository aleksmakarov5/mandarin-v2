<template>
    <section class="application-card-summary bg-white border-bottom flex-shrink-0">
        <div v-if="hasData" class="row g-2 align-items-center">
            <div class="col-12 col-md-6 col-xl-4">
                <div class="summary-primary">
                    <i class="bi bi-car-front-fill text-primary"></i>
                    <span class="fw-bold text-truncate">
                        {{ application.CarModel || 'Автомобиль не указан' }}
                    </span>
                    <span v-if="application.gnz" class="summary-plate">
                        {{ application.gnz }}
                    </span>
                </div>
                <div class="summary-secondary">
                    VIN: {{ application.vin || '—' }}
                </div>
            </div>

            <div class="col-12 col-md-6 col-xl-4">
                <div class="summary-primary">
                    <i class="bi bi-person-fill text-success"></i>
                    <span class="fw-semibold text-truncate">
                        {{ application.ClientName || 'Клиент не указан' }}
                    </span>
                </div>

                <div class="summary-secondary">
                    <a v-if="phoneLink" :href="phoneLink" class="text-decoration-none">
                        <i class="bi bi-telephone-fill me-1"></i>
                        {{ application.ClientPhone }}
                    </a>
                    <span v-else>Телефон не указан</span>
                </div>
            </div>

            <div class="col-12 col-xl-4">
                <div class="summary-primary">
                    <i class="bi bi-shield-check text-warning"></i>
                    <span class="text-truncate">{{ insuranceName }}</span>
                </div>

                <div class="summary-secondary">
                    № {{ lossNumber }}
                    <span class="mx-1">·</span>
                    ДТП: {{ formatDate(application.DTPdate) }}
                </div>
            </div>
        </div>

        <div v-else class="text-muted small">
            Загрузка кратких сведений по делу…
        </div>
    </section>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    application: {
        type: Object,
        default: () => ({}),
    },
    insuranceName: {
        type: String,
        default: 'Страховая компания не выбрана',
    },
});

const hasData = computed(() => (
    Object.keys(props.application || {}).length > 0
));

const lossNumber = computed(() => (
    props.application.numberInsuranceCompanyApplication
    || props.application.ub_number
    || 'б/н'
));

const phoneLink = computed(() => {
    let digits = String(props.application.ClientPhone || '')
        .replace(/\D/g, '');

    if (!digits) {
        return null;
    }

    if (digits.length === 10) {
        digits = `7${digits}`;
    } else if (digits.startsWith('8')) {
        digits = `7${digits.slice(1)}`;
    }

    return /^7\d{10}$/.test(digits)
        ? `tel:+${digits}`
        : null;
});

function formatDate(value) {
    if (!value) {
        return '—';
    }

    const date = new Date(`${String(value).substring(0, 10)}T00:00:00`);

    return Number.isNaN(date.getTime())
        ? '—'
        : date.toLocaleDateString('ru-RU');
}
</script>

<style scoped>
.application-card-summary {
    padding: 8px 14px;
    font-size: 0.78rem;
}

.summary-primary {
    display: flex;
    align-items: center;
    gap: 7px;
    min-width: 0;
}

.summary-secondary {
    margin-top: 2px;
    margin-left: 21px;
    color: #64748b;
    font-size: 0.7rem;
}

.summary-plate {
    flex-shrink: 0;
    padding: 1px 5px;
    color: #111827;
    background: #ffffff;
    border: 1px solid #1f2937;
    border-radius: 3px;
    font-family: monospace;
    font-size: 0.7rem;
    font-weight: 800;
    text-transform: uppercase;
}

@media (max-width: 767.98px) {
    .application-card-summary {
        padding: 7px 10px;
    }
}
</style>
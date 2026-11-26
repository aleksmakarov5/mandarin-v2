<template>
    <section class="card shadow-sm border-0" @focusout="commit" @change="commit">
        <div class="card-header bg-white fw-bold py-2 border-bottom">
            Филиал, СТО и сроки
        </div>
        <div v-if="!readonly">
            <div class="card-body p-3">

                <div class="row g-2 mb-3">
                    <!-- Наш филиал -->
                    <div class="col-12 col-md-4">
                        <label class="form-label">Наш филиал</label>
                        <select v-model.number="form.our_company" class="form-select form-select-sm"
                            @change="onBranchChange">
                            <option :value="null">Не выбран</option>
                            <option v-for="branch in branches" :key="branch.id" :value="Number(branch.id)">
                                {{ branch.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Город -->
                    <div class="col-12 col-md-4">
                        <label class="form-label">Город</label>
                        <select v-model.number="form.id_city" class="form-select form-select-sm">
                            <option :value="null">Не выбран</option>
                            <option v-for="city in cities" :key="city.id" :value="Number(city.id)">
                                {{ city.name }}
                            </option>
                        </select>
                    </div>

                    <!-- СТО (только привязанные к выбранному филиалу) -->
                    <div class="col-12 col-md-4">
                        <label class="form-label">СТО</label>
                        <select v-model.number="form.id_service" class="form-select form-select-sm"
                            :disabled="!form.our_company && filteredServices.length === 0">
                            <option :value="null">
                                {{ !form.our_company ? 'Сначала выберите филиал'
                                    : (filteredServices.length ? 'Не выбрана' :
                                        'Нет привязанных СТО') }}
                            </option>
                            <option v-for="service in filteredServices" :key="service.id" :value="Number(service.id)">
                                {{ service.name }}
                            </option>
                        </select>
                    </div>
                </div>

                <!-- Даты и сроки -->
                <div class="row g-2">
                    <div class="col-6 col-md-3">
                        <label class="form-label">Планируемый осмотр</label>
                        <input v-model="form.view_date" type="datetime-local" class="form-control form-control-sm" />
                    </div>

                    <div class="col-6 col-md-3">
                        <label class="form-label">Начало ремонта</label>
                        <input v-model="form.start_date" type="date" class="form-control form-control-sm" />
                    </div>

                    <div class="col-6 col-md-3">
                        <label class="form-label">Поставка запчастей</label>
                        <input v-model="form.sp_date" type="date" class="form-control form-control-sm" />
                    </div>

                    <div class="col-6 col-md-3">
                        <label class="form-label">Окончание ремонта</label>
                        <input :value="form.end_date" type="date" class="form-control form-control-sm" readonly disabled
                            title="Рассчитывается автоматически: начало ремонта + 30 рабочих дней" />
                    </div>
                </div>
            </div>

        </div>

        <div v-else class="readonly-grid">
            <div class="readonly-field">
                <span>Наш филиал</span>
                <strong>{{ branchName }}</strong>
            </div>

            <div class="readonly-field">
                <span>Город</span>
                <strong>{{ cityName }}</strong>
            </div>

            <div class="readonly-field">
                <span>СТО</span>
                <strong>{{ serviceName }}</strong>
            </div>

            <div class="readonly-field">
                <span>Планируемый осмотр</span>
                <strong>{{ formatDateTime(form.view_date) }}</strong>
            </div>

            <div class="readonly-field">
                <span>Начало ремонта</span>
                <strong>{{ formatDate(form.start_date) }}</strong>
            </div>

            <div class="readonly-field">
                <span>Поставка запчастей</span>
                <strong>{{ formatDate(form.sp_date) }}</strong>
            </div>

            <div class="readonly-field">
                <span>Окончание ремонта</span>
                <strong>{{ formatDate(form.end_date) }}</strong>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    form: {
        type: Object,
        required: true,
    },
    branches: {
        type: Array,
        default: () => [],
    },
    cities: {
        type: Array,
        default: () => [],
    },
    services: {
        type: Array,
        default: () => [],
    },
    readonly: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['commit']);

const branchName = computed(() => {
    const item = props.branches.find(
        branch => Number(branch.id) === Number(props.form.our_company)
    );

    return item?.name || '—';
});

const cityName = computed(() => {
    const item = props.cities.find(
        city => Number(city.id) === Number(props.form.id_city)
    );

    return item?.name || '—';
});

const serviceName = computed(() => {
    const item = props.services.find(
        service => Number(service.id) === Number(props.form.id_service)
    );

    return item?.name || '—';
});

function formatDate(value) {
    if (!value) return '—';

    const match = String(value).match(
        /^(\d{4})-(\d{2})-(\d{2})/
    );

    return match
        ? `${match[3]}.${match[2]}.${match[1]}`
        : '—';
}

function formatDateTime(value) {
    if (!value) return '—';

    return String(value)
        .replace('T', ' ')
        .substring(0, 16);
}
// Вычисляемый список СТО: фильтруем по текущему филиалу (our_company)
const filteredServices = computed(() => {
    const currentBranchId = Number(props.form.our_company || 0);

    if (!currentBranchId) {
        return props.services;
    }

    return (props.services || []).filter((s) => {
        // Если у сервиса есть массив branch_ids, проверяем вхождение
        if (Array.isArray(s.branch_ids) && s.branch_ids.length > 0) {
            return s.branch_ids.includes(currentBranchId);
        }
        // Если привязки нет — доступен для всех филиалов
        return true;
    });
});

// При смене филиала проверяем, подходит ли текущая СТО
function onBranchChange() {
    const currentBranchId = Number(props.form.our_company || 0);
    const currentServiceId = Number(props.form.id_service || 0);

    if (currentServiceId && currentBranchId) {
        const isAvailable = filteredServices.value.some((s) => Number(s.id) === currentServiceId);
        if (!isAvailable) {
            props.form.id_service = null;
        }
    }

    emit('commit');
}

function commit(event) {
    if (['INPUT', 'SELECT', 'TEXTAREA'].includes(event.target?.tagName)) {
        emit('commit');
    }
}
</script>

<style scoped>
.form-label {
    margin-bottom: 0.25rem;
    color: #64748b;
    font-size: 0.76rem;
    font-weight: 600;
}
</style>
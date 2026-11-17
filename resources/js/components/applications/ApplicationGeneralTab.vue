<template>
    <div class="application-general-tab">
        <div v-if="loading" class="d-flex justify-content-center align-items-center py-5">
            <div class="spinner-border text-warning" role="status">
                <span class="visually-hidden">Загрузка основных данных</span>
            </div>
        </div>

        <div v-else-if="loadError" class="alert alert-danger mb-0">
            <div class="fw-bold mb-2">Не удалось загрузить основные данные</div>
            <pre class="error-text mb-3">{{ loadError }}</pre>
            <button class="btn btn-sm btn-outline-danger" @click="load">
                Повторить
            </button>
        </div>

        <template v-else>
            <div class="general-save-bar d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div class="small">
                    <span v-if="saveState === 'saving'" class="text-primary">
                        <span class="spinner-border spinner-border-sm me-1"></span>
                        Сохранение изменений…
                    </span>

                    <span v-else-if="saveState === 'saved'" class="text-success">
                        ✓ Все изменения сохранены
                    </span>

                    <span v-else-if="saveState === 'error'" class="text-danger">
                        Ошибка сохранения
                    </span>

                    <span v-else class="text-muted">
                        Изменения сохраняются автоматически
                    </span>
                </div>

                <button type="button" class="btn btn-sm btn-primary fw-semibold" :disabled="saving"
                    @click="saveCore(true)">
                    Сохранить
                </button>
            </div>

            <div v-if="saveError" class="alert alert-danger py-2">
                <pre class="error-text mb-0">{{ saveError }}</pre>
            </div>

            <div class="row g-3">
                <div class="col-12 col-xl-8">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <GeneralVehicleSection :form="form" @commit="queueSave" />
                        </div>

                        <div class="col-12 col-md-6">
                            <GeneralClientSection :form="form" @commit="queueSave" />
                        </div>

                        <div class="col-12">
                            <GeneralPassportSection :form="form" @commit="queueSave" />
                        </div>

                        <div class="col-12">
                            <GeneralInsuranceSection :form="form" :insurance-companies="options.insurance_companies"
                                @commit="queueSave" />
                        </div>

                        <div class="col-12">
                            <GeneralLogisticsSection :form="form" :branches="options.branches" :cities="options.cities"
                                :services="options.services" @commit="queueSave" />
                        </div>
                    </div>
                </div>

                <div class="col-12 col-xl-4">
                    <GeneralStaffSection :form="form" :staff="staff" :lawyers="lawyers" @commit="queueSave"
                        @new-lawyer="openLawyerModal" />
                </div>
            </div>
        </template>

        <Teleport to="body">
            <div v-if="showLawyerModal" class="modal-backdrop fade show" style="z-index: 1055"></div>

            <div v-if="showLawyerModal" class="modal d-block" tabindex="-1" style="z-index: 1056"
                @keydown.esc="showLawyerModal = false">
                <div class="modal-dialog modal-dialog-centered px-3">
                    <div class="modal-content border-0 shadow-lg">
                        <div class="modal-header">
                            <h6 class="modal-title fw-bold">Новый юрист</h6>
                            <button type="button" class="btn-close" @click="showLawyerModal = false"></button>
                        </div>

                        <div class="modal-body">
                            <div class="mb-2">
                                <label class="form-label small fw-semibold">ФИО *</label>
                                <input v-model="newLawyer.name" type="text" class="form-control form-control-sm"
                                    maxlength="191" />
                            </div>

                            <div class="mb-2">
                                <label class="form-label small fw-semibold">Телефон</label>
                                <input v-model="newLawyer.phone" type="tel" class="form-control form-control-sm"
                                    maxlength="50" placeholder="+7 (999) 123-45-67" />
                            </div>

                            <div class="mb-2">
                                <label class="form-label small fw-semibold">Email</label>
                                <input v-model="newLawyer.email" type="email" class="form-control form-control-sm"
                                    maxlength="191" />
                            </div>

                            <div>
                                <label class="form-label small fw-semibold">Примечание</label>
                                <textarea v-model="newLawyer.comment" class="form-control form-control-sm" rows="3"
                                    maxlength="2000"></textarea>
                            </div>

                            <div v-if="lawyerError" class="alert alert-danger small mt-3 mb-0">
                                {{ lawyerError }}
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-sm btn-outline-secondary"
                                @click="showLawyerModal = false">
                                Отмена
                            </button>

                            <button type="button" class="btn btn-sm btn-primary"
                                :disabled="creatingLawyer || !newLawyer.name.trim()" @click="createLawyer">
                                <span v-if="creatingLawyer" class="spinner-border spinner-border-sm me-1"></span>
                                Создать и выбрать
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import {
    nextTick,
    onBeforeUnmount,
    onMounted,
    reactive,
    ref,
    watch,
} from 'vue';
import axios from 'axios';
import GeneralVehicleSection from './general/GeneralVehicleSection.vue';
import GeneralClientSection from './general/GeneralClientSection.vue';
import GeneralPassportSection from './general/GeneralPassportSection.vue';
import GeneralInsuranceSection from './general/GeneralInsuranceSection.vue';
import GeneralLogisticsSection from './general/GeneralLogisticsSection.vue';
import GeneralStaffSection from './general/GeneralStaffSection.vue';

const props = defineProps({
    applicationId: {
        type: Number,
        required: true,
    },
});

const emit = defineEmits(['loaded', 'saved', 'error']);

const GENERAL_FIELDS = [
    'CarModel',
    'gnz',
    'vin',
    'mnf_year',
    'Color',
    'mileage',
    'vehicle_passport',
    'ClientName',
    'gender',
    'ClientPhone',
    'pred',
    'pred_phone',
    'birthday',
    'ClientPasport',
    'ClientPasport_number',
    'ClientPasport_date',
    'ClientPasport_struct',
    'id_insuranceCompany',
    'ub_type',
    'numberInsuranceCompanyApplication',
    'DTPdate',
    'napr_date',
    'ub_number',
    'dogovor',
    'our_company',
    'id_city',
    'id_service',
    'view_date',
    'start_date',
    'sp_date',
    'id_MRK',
    'id_mentor',
    'id_spm',
    'id_lawyer',
    'comment_true',
];

const DATE_FIELDS = [
    'birthday',
    'ClientPasport_date',
    'DTPdate',
    'napr_date',
    'view_date',
    'start_date',
    'sp_date',
    'end_date',
];

const NUMERIC_FIELDS = [
    'gender',
    'ub_type',
    'id_insuranceCompany',
    'our_company',
    'id_city',
    'id_service',
    'id_MRK',
    'id_mentor',
    'id_spm',
    'id_lawyer',
];

const loading = ref(true);
const saving = ref(false);
const loadError = ref('');
const saveError = ref('');
const saveState = ref('saved');
const saveQueued = ref(false);
const saveTimer = ref(null);

const form = ref({});
const lastSavedPayload = ref({});

const options = reactive({
    statuses: [],
    cities: [],
    branches: [],
    insurance_companies: [],
    services: [],
});

const staff = reactive({
    mrk: [],
    mentors: [],
    spm: [],
});

const lawyers = ref([]);

const showLawyerModal = ref(false);
const creatingLawyer = ref(false);
const lawyerError = ref('');

const newLawyer = reactive({
    name: '',
    phone: '',
    email: '',
    comment: '',
});

function normalizeForm(source) {
    const normalized = {
        ...source,
    };

    GENERAL_FIELDS.forEach((field) => {
        if (!Object.prototype.hasOwnProperty.call(normalized, field)) {
            normalized[field] = null;
        }
    });

    DATE_FIELDS.forEach((field) => {
        const value = normalized[field];
        normalized[field] = value ? String(value).substring(0, 10) : null;
    });

    NUMERIC_FIELDS.forEach((field) => {
        const value = normalized[field];

        normalized[field] = value === null || value === undefined || value === ''
            ? null
            : Number(value);
    });

    return normalized;
}

function buildPayload() {
    const payload = {};

    GENERAL_FIELDS.forEach((field) => {
        const value = form.value[field];
        payload[field] = value === '' || value === undefined ? null : value;
    });

    return payload;
}

function getChangedPayload() {
    const current = buildPayload();
    const changed = {};

    Object.entries(current).forEach(([field, value]) => {
        const currentValue = value ?? null;
        const savedValue = lastSavedPayload.value[field] ?? null;

        if (currentValue !== savedValue) {
            changed[field] = value;
        }
    });

    return changed;
}

async function load() {
    loading.value = true;
    loadError.value = '';
    saveError.value = '';

    try {
        const [applicationResponse, optionsResponse, lawyersResponse] =
            await Promise.all([
                axios.get(`/api/applications/${props.applicationId}/general`),
                axios.get('/api/applications/general-options'),
                axios.get('/api/general-lawyers'),
            ]);

        form.value = normalizeForm(applicationResponse.data.data || {});

        Object.assign(options, {
            statuses: optionsResponse.data.statuses || [],
            cities: optionsResponse.data.cities || [],
            branches: optionsResponse.data.branches || [],
            insurance_companies: optionsResponse.data.insurance_companies || [],
            services: optionsResponse.data.services || [],
        });

        lawyers.value = lawyersResponse.data || [];

        await loadStaff();

        lastSavedPayload.value = {
            ...buildPayload(),
        };

        saveState.value = 'saved';
        emit('loaded', { ...form.value });
    } catch (error) {
        loadError.value = formatError(error);
        emit('error', loadError.value);
    } finally {
        loading.value = false;
    }
}

async function loadStaff() {
    try {
        const response = await axios.get(
            '/api/applications/general-branch-employees',
            {
                params: {
                    branch_id: form.value.our_company || undefined,
                },
            }
        );

        staff.mrk = response.data.mrk || [];
        staff.mentors = response.data.mentors || [];
        staff.spm = response.data.spm || [];
    } catch (error) {
        console.error('Не удалось загрузить сотрудников филиала', error);

        staff.mrk = [];
        staff.mentors = [];
        staff.spm = [];
    }
}

function queueSave() {
    window.clearTimeout(saveTimer.value);

    saveTimer.value = window.setTimeout(() => {
        saveCore(false);
    }, 450);
}

function validPhone(value) {
    if (!value) {
        return true;
    }

    let digits = String(value).replace(/\D/g, '');

    if (digits.length === 10) {
        digits = '7' + digits;
    }

    if (digits.length === 11 && digits.startsWith('8')) {
        digits = '7' + digits.slice(1);
    }

    return /^7\d{10}$/.test(digits);
}

async function saveCore(manual = false) {
    window.clearTimeout(saveTimer.value);

    if (!form.value.id) {
        return false;
    }

    if (saving.value) {
        saveQueued.value = true;
        return false;
    }

    const payload = getChangedPayload();

    if (!Object.keys(payload).length) {
        saveState.value = 'saved';
        saveError.value = '';
        return true;
    }

    for (const field of ['ClientPhone', 'pred_phone']) {
        if (
            Object.prototype.hasOwnProperty.call(payload, field) &&
            !validPhone(payload[field])
        ) {
            saveState.value = 'error';
            saveError.value = 'Проверьте телефон: +7 (999) 123-45-67';

            if (manual) {
                window.alert(saveError.value);
            }

            return false;
        }
    }

    saving.value = true;
    saveQueued.value = false;
    saveState.value = 'saving';
    saveError.value = '';

    let succeeded = false;

    try {
        const response = await axios.put(
            `/api/applications/${props.applicationId}/general`,
            payload
        );

        // Отмечаем сохранёнными только реально отправленные поля.
        lastSavedPayload.value = {
            ...lastSavedPayload.value,
            ...payload,
        };

        // Не перезаписываем форму всем ответом сервера.
        // Обновляем только рассчитанное сервером поле end_date.
        if (Object.prototype.hasOwnProperty.call(payload, 'start_date')) {
            form.value.end_date = response.data?.data?.end_date || null;
        }

        saveState.value = 'saved';
        succeeded = true;

        emit('saved', { ...form.value });

        return true;
    } catch (error) {
        saveState.value = 'error';
        saveError.value = formatError(error);

        console.error('Ошибка сохранения вкладки «Основное»', {
            payload,
            status: error.response?.status,
            response: error.response?.data,
        });

        if (manual) {
            window.alert(saveError.value);
        }

        emit('error', saveError.value);

        return false;
    } finally {
        saving.value = false;

        if (succeeded) {
            const remaining = getChangedPayload();

            if (saveQueued.value || Object.keys(remaining).length) {
                saveQueued.value = false;
                nextTick(() => saveCore(false));
            }
        }
    }
}

function formatError(error) {
    const response = error.response?.data;
    const messages = [];

    if (response?.message) {
        messages.push(response.message);
    }

    if (response?.errors) {
        Object.entries(response.errors).forEach(([field, fieldErrors]) => {
            const errors = Array.isArray(fieldErrors)
                ? fieldErrors
                : [fieldErrors];

            errors.forEach((message) => {
                messages.push(`${field}: ${message}`);
            });
        });
    }

    if (response?.debug) {
        messages.push(response.debug);
    }

    return messages.length
        ? [...new Set(messages)].join('\n')
        : error.message || 'Неизвестная ошибка';
}

function openLawyerModal() {
    Object.assign(newLawyer, {
        name: '',
        phone: '',
        email: '',
        comment: '',
    });

    lawyerError.value = '';
    showLawyerModal.value = true;
}

async function createLawyer() {
    if (!newLawyer.name.trim() || creatingLawyer.value) {
        return;
    }

    creatingLawyer.value = true;
    lawyerError.value = '';

    try {
        const response = await axios.post('/api/general-lawyers', {
            name: newLawyer.name,
            phone: newLawyer.phone || null,
            email: newLawyer.email || null,
            comment: newLawyer.comment || null,
        });

        const lawyer = response.data.data;

        lawyers.value.push(lawyer);
        form.value.id_lawyer = Number(lawyer.id);
        showLawyerModal.value = false;

        queueSave();
    } catch (error) {
        lawyerError.value = formatError(error);
    } finally {
        creatingLawyer.value = false;
    }
}

watch(
    () => form.value.our_company,
    () => {
        if (!loading.value && form.value.id) {
            loadStaff();
        }
    }
);

onMounted(load);

onBeforeUnmount(() => {
    window.clearTimeout(saveTimer.value);
});

defineExpose({
    reload: load,
    save: () => saveCore(true),
});
</script>

<style scoped>
.application-general-tab {
    width: 100%;
}

.general-save-bar {
    padding: 0.55rem 0.75rem;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
}

.error-text {
    font: inherit;
    font-size: 0.78rem;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
}

@media (max-width: 767.98px) {
    .general-save-bar {
        align-items: stretch;
    }

    .general-save-bar .btn {
        min-height: 38px;
    }
}
</style>
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
            <div v-if="canEdit"
                class="general-save-bar d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
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
                <div class="col-12 col-xl-9">
                    <div class="row g-3">
                        <!-- Видно сервису и запчастисту -->
                        <div v-if="generalPermissions.view_vehicle" class="col-12 col-md-5">
                            <GeneralVehicleSection :form="form" :readonly="isReadOnly" @commit="queueSave" />
                        </div>

                        <!-- Только полный доступ -->
                        <div v-if="generalPermissions.view_client" class="col-12 col-md-7">
                            <GeneralClientSection :form="form" @commit="queueSave" />
                        </div>

                        <!-- Только полный доступ -->
                        <div v-if="generalPermissions.view_insurance" class="col-12 col-md-5">
                            <GeneralInsuranceSection :form="form" :insurance-companies="options.insurance_companies"
                                @commit="queueSave" />
                        </div>

                        <!-- Видно сервису и запчастисту -->
                        <div v-if="generalPermissions.view_branch_service_dates" class="col-12 col-md-7">
                            <GeneralLogisticsSection :form="form" :branches="options.branches" :cities="options.cities"
                                :services="options.services" :readonly="isReadOnly" @commit="queueSave" />
                        </div>

                        <!-- Только полный доступ -->
                        <div v-if="generalPermissions.view_passport" class="col-12">
                            <GeneralPassportSection :form="form" @commit="queueSave" />
                        </div>
                    </div>
                </div>

                <!-- Видно сервису и запчастисту -->
                <div v-if="generalPermissions.view_responsible_employees" class="col-12 col-xl-3">
                    <GeneralStaffSection :form="form" :staff="staff" :lawyers="lawyers" :readonly="isReadOnly"
                        :show-internal-note="generalPermissions.view_internal_note" @commit="queueSave"
                        @new-lawyer="openLawyerModal" />
                </div>
            </div>
        </template>


        <LawyerCreateModal v-if="canEdit" v-model:show="showLawyerModal" @created="onLawyerCreated" />

    </div>
</template>

<script setup>
import {
    computed,
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

import LawyerCreateModal from './card/LawyerCreateModal.vue';

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
    'color',
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
    // 'view_date',
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

const generalPermissions = reactive({
    view_vehicle: false,
    view_branch_service_dates: false,
    view_responsible_employees: false,
    view_client: false,
    view_passport: false,
    view_insurance: false,
    view_internal_note: false,
    edit: false,
});

const canEdit = computed(() => {
    return generalPermissions.edit === true;
});

const isReadOnly = computed(() => !canEdit.value);

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

    // 1. Обычные даты (обрезаем до 10 символов)
    DATE_FIELDS.forEach((field) => {
        const value = normalized[field];
        normalized[field] = value ? String(value).substring(0, 10) : null;
    });

    // 2. view_date (datetime-local)
    const rawViewDate = normalized.view_date;
    if (rawViewDate) {
        normalized.view_date = String(rawViewDate).replace(' ', 'T').substring(0, 16);
    } else {
        normalized.view_date = '';
    }

    // 3. Числовые поля (гарантируем Number, исключаем NaN)
    NUMERIC_FIELDS.forEach((field) => {
        const value = normalized[field];
        normalized[field] = (value === null || value === undefined || value === '' || isNaN(Number(value)))
            ? null
            : Number(value);
    });

    // 4. Филиал (our_company / our_company_id)
    const rawBranch = normalized.our_company ?? source.our_company_id ?? source.id_our_company;
    normalized.our_company = (rawBranch !== null && rawBranch !== undefined && rawBranch !== '' && !isNaN(Number(rawBranch)))
        ? Number(rawBranch)
        : null;

    return normalized;
}

function buildPayload() {
    const payload = {};

    GENERAL_FIELDS.forEach((field) => {
        let value = form.value[field];
        if (value === '' || value === undefined) {
            value = null;
        }

        // Приводим view_date к стандартному формату БД при сборке payload
        if (field === 'view_date' && value) {
            value = String(value).replace('T', ' ').substring(0, 16);
        }

        payload[field] = value;
    });

    return payload;
}

function getChangedPayload() {
    const current = buildPayload();
    const changed = {};

    Object.entries(current).forEach(([field, value]) => {
        let currentValue = value ?? null;
        let savedValue = lastSavedPayload.value[field] ?? null;

        // Нормализуем T и пробелы для корректного сравнения
        if (field === 'view_date') {
            if (currentValue) currentValue = String(currentValue).replace('T', ' ').substring(0, 16);
            if (savedValue) savedValue = String(savedValue).replace('T', ' ').substring(0, 16);
        }

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
        // 1. Загружаем всё параллельно
        const [applicationResponse, optionsResponse, lawyersResponse] =
            await Promise.all([
                axios.get(`/api/applications/${props.applicationId}/general`),
                axios.get('/api/applications/general-options'),
                axios.get('/api/general-lawyers'),
            ]);

        // 2. СНАЧАЛА наполняем списки опций (филиалы, города, СК), чтобы селекты успели их отрендерить
        Object.assign(options, {
            statuses: optionsResponse.data.statuses || [],
            cities: optionsResponse.data.cities || [],
            branches: optionsResponse.data.branches || [],
            insurance_companies: optionsResponse.data.insurance_companies || [],
            services: optionsResponse.data.services || [],
        });

        lawyers.value = lawyersResponse.data || [];

        // 3. ЗАТЕМ нормализуем и выставляем данные формы
        form.value = normalizeForm(applicationResponse.data.data || {});

        const serverPermissions =
            applicationResponse.data.data?.permissions?.general || {};

        Object.assign(generalPermissions, {
            view_vehicle: serverPermissions.view_vehicle === true,
            view_branch_service_dates:
                serverPermissions.view_branch_service_dates === true,
            view_responsible_employees:
                serverPermissions.view_responsible_employees === true,
            view_client: serverPermissions.view_client === true,
            view_passport: serverPermissions.view_passport === true,
            view_insurance: serverPermissions.view_insurance === true,
            view_internal_note:
                serverPermissions.view_internal_note === true,
            edit: serverPermissions.edit === true,
        });

        // 4. Подгружаем сотрудников под уже выбранный филиал
        if (form.value.our_company) {
            await loadStaff();
        }

        // 5. Фиксируем снимок сохранённого состояния
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
    const branchId = form.value?.our_company ? Number(form.value.our_company) : null;

    try {
        const response = await axios.get(
            '/api/applications/general-branch-employees',
            {
                params: {
                    branch_id: branchId || undefined,
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
    if (!canEdit.value || loading.value) {
        return;
    }
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
    if (!canEdit.value) {
        saveState.value = 'saved';
        saveError.value = '';
        return false;
    }
    window.clearTimeout(saveTimer.value);

    if (!form.value.id) {
        return false;
    }

    if (saving.value) {
        saveQueued.value = true;
        return false;
    }

    const payload = getChangedPayload();

    // if (payload.view_date) {
    //     payload.view_date = payload.view_date.replace('T', ' ');
    // }
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
            if (payload.view_date) {
                form.value.view_date = String(payload.view_date).replace(' ', 'T').substring(0, 16);
            }
            if (saveQueued.value && Object.keys(remaining).length > 0) {
                saveQueued.value = false;
                nextTick(() => saveCore(false));
            } else {
                saveQueued.value = false;
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
    if (!canEdit.value) {
        return;
    }
    Object.assign(newLawyer, {
        name: '',
        phone: '',
        email: '',
        comment: '',
    });

    lawyerError.value = '';
    showLawyerModal.value = true;
}
function onLawyerCreated(newLawyer) {
    // Добавляем юриста в список, чтобы выпадающий список обновился
    lawyers.value.push(newLawyer);

    // Выбираем созданного юриста в форме дела
    form.value.id_lawyer = Number(newLawyer.id);


    showLawyerModal.value = false;
    // Можно сразу вызвать автосохранение
    scheduleAutoSave(100);
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
    () => form.value?.our_company,
    async (newBranch, oldBranch) => {
        // Игнорируем вызовы во время первичной загрузки страницы
        if (loading.value || !form.value?.id || !canEdit.value) {
            return;
        }

        const parsedNew = (newBranch !== null && newBranch !== undefined && newBranch !== '' && !isNaN(Number(newBranch)))
            ? Number(newBranch)
            : null;
        const parsedOld = (oldBranch !== null && oldBranch !== undefined && oldBranch !== '' && !isNaN(Number(oldBranch)))
            ? Number(oldBranch)
            : null;

        if (parsedNew === parsedOld) {
            return;
        }

        // Обновляем список сотрудников под новый филиал
        await loadStaff();

        // Сбрасываем выбранных сотрудников, если они не принадлежат новому филиалу
        if (form.value.id_MRK && !staff.mrk.some(u => Number(u.id) === Number(form.value.id_MRK))) {
            form.value.id_MRK = null;
        }
        if (form.value.id_mentor && !staff.mentors.some(u => Number(u.id) === Number(form.value.id_mentor))) {
            form.value.id_mentor = null;
        }
        if (form.value.id_spm && !staff.spm.some(u => Number(u.id) === Number(form.value.id_spm))) {
            form.value.id_spm = null;
        }

        // Запускаем сохранение
        queueSave();
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

.general-readonly-bar {
    padding: 0.55rem 0.75rem;
    color: #475569;
    background: #f8fafc;
    border: 1px solid #cbd5e1;
    border-radius: 0.5rem;
    font-size: 0.82rem;
}

/* Единый режим просмотра во всех дочерних разделах. */
.application-general-tab :deep(.readonly-grid) {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 0;
    min-width: 0;
    padding: 0;
}

.application-general-tab :deep(.readonly-field) {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 5px;
    min-width: 0;
    margin: 10;
    padding: 11px 0;
    border: 0;
    border-bottom: 1px solid #edf2f7;
}

.application-general-tab :deep(.readonly-field:first-child) {
    padding-top: 0;
}

.application-general-tab :deep(.readonly-field:last-child) {
    padding-bottom: 0;
    border-bottom: 0;
}

.application-general-tab :deep(.readonly-field > span) {
    display: block;
    margin: 10;
    color: #64748b;
    font-size: 12px;
    font-weight: 500;
    line-height: 1.4;
}

.application-general-tab :deep(.readonly-field > strong) {
    display: block;
    min-width: 0;
    margin: 10;
    color: #1e293b;
    font-size: 14px;
    font-weight: 600;
    line-height: 1.5;
    white-space: normal;
    overflow-wrap: anywhere;
}
</style>
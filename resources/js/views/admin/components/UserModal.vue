<template>
    <div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalLabel" aria-hidden="true"
        ref="modalRef">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-light border-bottom py-3">
                    <h5 class="modal-title fw-bold text-dark" id="userModalLabel">
                        <i class="bi me-2 text-primary"
                            :class="isEdit ? 'bi-pencil-square' : 'bi-person-plus-fill'"></i>
                        {{ isEdit ? 'Редактирование сотрудника' : 'Новый сотрудник' }}
                    </h5>
                    <button type="button" class="btn-close" @click="closeModal" aria-label="Закрыть"></button>
                </div>

                <form @submit.prevent="handleSubmit">
                    <div class="modal-body p-4" style="max-height: calc(85vh - 120px); overflow-y: auto;">
                        <!-- Общая ошибка -->
                        <div v-if="generalError" class="alert alert-danger py-2 small border-0 mb-3">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i> {{ generalError }}
                        </div>

                        <!-- РАЗДЕЛ 1: ОСНОВНЫЕ ДАННЫЕ -->
                        <h6 class="fw-bold text-dark mb-3 d-flex align-items-center">
                            <i class="bi bi-person-badge text-primary me-2"></i> Персональные данные
                        </h6>

                        <div class="row g-3 mb-4">
                            <!-- Фамилия -->
                            <div class="col-12 col-md-4">
                                <label class="form-label small fw-semibold text-muted">
                                    Фамилия <span class="text-danger">*</span>
                                </label>
                                <input v-model="form.last_name" type="text" class="form-control"
                                    :class="{ 'is-invalid': errors.last_name }" placeholder="Иванов" required />
                                <div v-if="errors.last_name" class="invalid-feedback">{{ errors.last_name[0] }}</div>
                            </div>

                            <!-- Имя -->
                            <div class="col-12 col-md-4">
                                <label class="form-label small fw-semibold text-muted">
                                    Имя <span class="text-danger">*</span>
                                </label>
                                <input v-model="form.name" type="text" class="form-control"
                                    :class="{ 'is-invalid': errors.name }" placeholder="Иван" required />
                                <div v-if="errors.name" class="invalid-feedback">{{ errors.name[0] }}</div>
                            </div>

                            <!-- Отчество -->
                            <div class="col-12 col-md-4">
                                <label class="form-label small fw-semibold text-muted">
                                    Отчество <span class="text-muted fw-normal">(необязательно)</span>
                                </label>
                                <input v-model="form.patronymic" type="text" class="form-control"
                                    placeholder="Иванович" />
                            </div>

                            <!-- Email -->
                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-semibold text-muted">
                                    Email / Логин <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-envelope"></i></span>
                                    <input v-model="form.email" type="email" class="form-control"
                                        :class="{ 'is-invalid': errors.email }" placeholder="ivanov@mandarin.local"
                                        required />
                                    <div v-if="errors.email" class="invalid-feedback">{{ errors.email[0] }}</div>
                                </div>
                            </div>

                            <!-- Телефон -->
                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-semibold text-muted">Телефон</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-telephone"></i></span>
                                    <input v-model="form.phone" v-mask-phone type="tel" class="form-control"
                                        placeholder="+7 (999) 000-00-00" />
                                </div>
                            </div>
                        </div>

                        <!-- РАЗДЕЛ 2: ГОРОДА ДОСТУПА -->
                        <div class="mb-4 pt-3 border-top">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <h6 class="mb-0 fw-bold text-dark d-flex align-items-center">
                                        <i class="bi bi-geo-alt text-primary me-2"></i> Прикрепленные города
                                    </h6>
                                    <small class="text-muted">Сотрудник видит заявки и справочники выбранных
                                        городов</small>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none"
                                        @click="selectAllCities">
                                        Выбрать все
                                    </button>
                                    <span class="text-muted">|</span>
                                    <button type="button"
                                        class="btn btn-link btn-sm p-0 text-decoration-none text-muted"
                                        @click="form.city_ids = []">
                                        Снять все
                                    </button>
                                </div>
                            </div>

                            <div class="p-3 border rounded bg-light" style="max-height: 130px; overflow-y: auto;">
                                <div class="d-flex flex-wrap gap-2">
                                    <div v-for="c in cities" :key="c.id" class="form-check form-check-inline m-0">
                                        <input class="form-check-input" type="checkbox" :id="'user-city-' + c.id"
                                            :value="c.id" v-model="form.city_ids" />
                                        <label class="form-check-label small" :for="'user-city-' + c.id">
                                            {{ c.name }}
                                        </label>
                                    </div>
                                    <div v-if="cities.length === 0" class="text-muted small">
                                        Города пока не добавлены. Создайте их во вкладке «Города».
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- РАЗДЕЛ 3: ФИЛИАЛЫ И РОЛИ В ФИЛИАЛАХ -->
                        <div class="mb-4 pt-3 border-top">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <h6 class="mb-0 fw-bold text-dark d-flex align-items-center">
                                        <i class="bi bi-shop text-primary me-2"></i> Филиалы и роли
                                    </h6>
                                    <small class="text-muted">Один сотрудник может выполнять разные роли в разных
                                        филиалах</small>
                                </div>
                                <button type="button" class="btn btn-outline-primary btn-sm" @click="addBranchRole">
                                    <i class="bi bi-plus-lg me-1"></i> Добавить филиал
                                </button>
                            </div>

                            <div v-if="form.branch_roles.length === 0"
                                class="alert alert-light border text-center small text-muted py-3 mb-0">
                                Сотрудник пока не прикреплен ни к одному филиалу. Нажмите <strong>«Добавить
                                    филиал»</strong> для назначения роли.
                            </div>

                            <div v-for="(item, idx) in form.branch_roles" :key="idx"
                                class="row g-2 align-items-center mb-2 bg-light p-2 rounded border">
                                <!-- Филиал -->
                                <div class="col-12 col-md-6">
                                    <label class="form-label small text-muted mb-1">Филиал / СТО</label>
                                    <select v-model="item.our_company_id" class="form-select form-select-sm" required>
                                        <option :value="null" disabled>Выберите филиал...</option>
                                        <option v-for="b in branches" :key="b.id" :value="b.id">
                                            {{ b.name }}
                                        </option>
                                    </select>
                                </div>

                                <!-- Роль -->
                                <div class="col-10 col-md-5">
                                    <label class="form-label small text-muted mb-1">Роль в этом филиале</label>
                                    <select v-model="item.user_group_id" class="form-select form-select-sm" required>
                                        <option :value="null" disabled>Выберите роль...</option>
                                        <option v-for="g in availableGroups" :key="g.id" :value="g.id">
                                            {{ g.name }}
                                        </option>
                                    </select>
                                </div>

                                <!-- Удалить строку -->
                                <div class="col-2 col-md-1 text-end pt-3">
                                    <button type="button" class="btn btn-outline-danger btn-sm" title="Удалить привязку"
                                        @click="removeBranchRole(idx)">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- РАЗДЕЛ 4: ДЕФОЛТНЫЕ ПРОЦЕНТЫ -->
                        <div class="mb-4 pt-3 border-top">
                            <h6 class="fw-bold text-dark mb-2 d-flex align-items-center">
                                <i class="bi bi-percent text-primary me-2"></i> Дефолтные проценты
                            </h6>
                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <label class="form-label small fw-semibold text-muted">Процент МРК (%)</label>
                                    <input v-model.number="form.def_mrk_proc" type="number" min="0" max="100"
                                        class="form-control" placeholder="Например: 10" />
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label small fw-semibold text-muted">Процент Запчасти (%)</label>
                                    <input v-model.number="form.def_zap_proc" type="number" min="0" max="100"
                                        class="form-control" placeholder="Например: 15" />
                                </div>
                            </div>
                        </div>

                        <!-- РАЗДЕЛ 5: ПАРОЛЬ -->
                        <div class="pt-3 border-top">
                            <h6 class="fw-bold text-dark mb-2 d-flex align-items-center">
                                <i class="bi bi-shield-lock text-primary me-2"></i>
                                {{ passwordTitle }}
                            </h6>
                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <label class="form-label small fw-semibold text-muted">Пароль</label>
                                    <input v-model="form.password" type="password" class="form-control"
                                        :class="{ 'is-invalid': errors.password }" :required="!isEdit"
                                        placeholder="Минимум 8 символов" autocomplete="new-password" />
                                    <div v-if="errors.password" class="invalid-feedback">{{ errors.password[0] }}</div>
                                </div>

                                <div class="col-12 col-md-6">
                                    <label class="form-label small fw-semibold text-muted">Подтверждение пароля</label>
                                    <input v-model="form.password_confirmation" type="password" class="form-control"
                                        :required="!isEdit || !!form.password" placeholder="Повторите пароль"
                                        autocomplete="new-password" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer bg-light border-top py-2 px-4 d-flex justify-content-between">
                        <button type="button" class="btn btn-outline-secondary btn-sm" @click="closeModal"
                            :disabled="loading">
                            Отмена
                        </button>
                        <button type="submit" class="btn btn-primary btn-sm px-4 fw-semibold" :disabled="loading">
                            <span v-if="loading" class="spinner-border spinner-border-sm me-1" role="status"></span>
                            {{ isEdit ? 'Сохранить изменения' : 'Создать сотрудника' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    groups: { type: Array, default: () => [] },
    cities: { type: Array, default: () => [] },
    branches: { type: Array, default: () => [] },
});

const emit = defineEmits(['saved']);

const modalRef = ref(null);
let bsModal = null;

const isEdit = ref(false);
const editingId = ref(null);
const loading = ref(false);
const generalError = ref('');
const errors = ref({});

const passwordTitle = computed(() => {
    return isEdit.value
        ? 'Смена пароля (оставьте пустым, если не меняется)'
        : 'Пароль учетной записи *';
});

// Исключаем системную роль sys_admin из выпадающего списка
const availableGroups = computed(() => {
    return props.groups.filter((g) => g.code !== 'sys_admin');
});

const initialForm = {
    last_name: '',
    name: '',
    patronymic: '',
    email: '',
    phone: '',
    user_group_id: null,
    def_mrk_proc: null,
    def_zap_proc: null,
    password: '',
    password_confirmation: '',
    city_ids: [],
    branch_roles: [],
};

const form = reactive({ ...initialForm });

onMounted(() => {
    if (window.bootstrap && modalRef.value) {
        bsModal = new window.bootstrap.Modal(modalRef.value, { backdrop: 'static' });
    }
});

const selectAllCities = () => {
    form.city_ids = props.cities.map((c) => c.id);
};

const addBranchRole = () => {
    form.branch_roles.push({
        our_company_id: props.branches[0]?.id || null,
        user_group_id: availableGroups.value[0]?.id || null,
    });
};

const removeBranchRole = (index) => {
    form.branch_roles.splice(index, 1);
};

const resetForm = () => {
    Object.assign(form, initialForm);
    form.city_ids = [];
    form.branch_roles = [];
    errors.value = {};
    generalError.value = '';
};

const open = (user = null) => {
    resetForm();

    if (user) {
        isEdit.value = true;
        editingId.value = user.id;

        form.last_name = user.last_name || '';
        form.name = user.name || '';
        form.patronymic = user.patronymic || '';
        form.email = user.email || '';
        form.phone = user.phone || '';
        form.user_group_id = user.user_group_id || null;
        form.def_mrk_proc = user.def_mrk_proc ?? null;
        form.def_zap_proc = user.def_zap_proc ?? null;
        form.password = '';
        form.password_confirmation = '';

        // Города сотрудника
        form.city_ids = Array.isArray(user.cities) ? user.cities.map((c) => c.id) : [];

        // Привязанные филиалы и роли
        if (Array.isArray(user.branch_roles)) {
            form.branch_roles = user.branch_roles.map((br) => ({
                our_company_id: br.our_company_id,
                user_group_id: br.user_group_id,
            }));
        }
    } else {
        isEdit.value = false;
        editingId.value = null;
        if (props.branches.length > 0 && availableGroups.value.length > 0) {
            addBranchRole();
        }
    }

    bsModal?.show();
};

const closeModal = () => {
    bsModal?.hide();
};

const handleSubmit = async () => {
    loading.value = true;
    generalError.value = '';
    errors.value = {};

    const payload = {
        ...form,
        city_ids: form.city_ids,
        branch_roles: form.branch_roles,
    };

    if (isEdit.value && !payload.password) {
        delete payload.password;
        delete payload.password_confirmation;
    }

    try {
        if (isEdit.value) {
            await axios.put(`/api/admin/users/${editingId.value}`, payload);
        } else {
            await axios.post('/api/admin/users', payload);
        }
        closeModal();
        emit('saved');
    } catch (err) {
        if (err.response?.status === 422) {
            errors.value = err.response.data.errors || {};
            generalError.value = err.response.data.message || 'Проверьте заполненные поля.';
        } else {
            generalError.value = err.response?.data?.message || 'Ошибка сохранения сотрудника.';
        }
    } finally {
        loading.value = false;
    }
};

defineExpose({ open, closeModal });
</script>
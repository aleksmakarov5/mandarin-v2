<template>
    <Teleport to="body">

        <div class="modal fade" id="serviceModal" tabindex="-1" ref="modalRef" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header bg-light border-bottom py-3">
                        <h5 class="modal-title fw-bold text-dark">
                            <i class="bi me-2 text-primary" :class="isEdit ? 'bi-pencil-square' : 'bi-tools'"></i>
                            {{ isEdit ? 'Редактировать автосервис' : 'Новый автосервис (СТО)' }}
                        </h5>
                        <button type="button" class="btn-close" @click="closeModal" aria-label="Закрыть"></button>
                    </div>

                    <form @submit.prevent="handleSubmit">
                        <div class="modal-body p-4" style="max-height: calc(85vh - 120px); overflow-y: auto;">
                            <div v-if="generalError" class="alert alert-danger py-2 small mb-3">
                                {{ generalError }}
                            </div>

                            <!-- БЛОК 1: ОСНОВНЫЕ ДАННЫЕ -->
                            <div class="row g-3 mb-3">
                                <div class="col-12 col-md-7">
                                    <label class="form-label small fw-semibold text-muted">
                                        Название автосервиса / СТО <span class="text-danger">*</span>
                                    </label>
                                    <input v-model="form.name" type="text" class="form-control"
                                        :class="{ 'is-invalid': errors.name }" placeholder="Автотехцентр «Северный»"
                                        required />
                                    <div v-if="errors.name" class="invalid-feedback">{{ errors.name[0] }}</div>
                                </div>

                                <!-- Ответственный пользователь (только роль СТО) -->
                                <div class="col-12 col-md-5">
                                    <label class="form-label small fw-semibold text-muted">
                                        Учетная запись СТО (User)
                                    </label>
                                    <select v-model="form.user_id" class="form-select">
                                        <option :value="null">-- Не привязан --</option>
                                        <option v-for="u in serviceUsers" :key="u.id" :value="u.id">
                                            {{ u.last_name ? `${u.last_name} ${u.name}` : u.name }} ({{ u.email }})
                                        </option>
                                    </select>
                                    <small class="text-muted d-block mt-1" style="font-size: 0.72rem;">
                                        Отображаются только пользователи с ролью «Сервис / СТО»
                                    </small>
                                </div>

                                <!-- Телефон с валидацией и маской -->
                                <div class="col-12 col-md-6">
                                    <label class="form-label small fw-semibold text-muted">Контактный телефон</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light"><i class="bi bi-telephone"></i></span>
                                        <input :value="form.phone" type="tel" class="form-control font-monospace"
                                            :class="{ 'is-invalid': phoneError }" placeholder="+7 (___) ___-__-__"
                                            maxlength="18" @input="onPhoneInput" />
                                    </div>
                                    <div v-if="phoneError" class="text-danger small mt-1" style="font-size: 0.72rem;">
                                        {{ phoneError }}
                                    </div>
                                </div>

                                <!-- Email -->
                                <div class="col-12 col-md-6">
                                    <label class="form-label small fw-semibold text-muted">Email сервиса</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light"><i class="bi bi-envelope"></i></span>
                                        <input v-model="form.email" type="email" class="form-control"
                                            placeholder="sto@service.ru" />
                                    </div>
                                </div>

                                <!-- Адрес -->
                                <div class="col-12">
                                    <label class="form-label small fw-semibold text-muted">Физический адрес цеха /
                                        СТО</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light"><i class="bi bi-geo-alt"></i></span>
                                        <input v-model="form.address" type="text" class="form-control"
                                            placeholder="г. Архангельск, ул. Промышленная, д. 12" />
                                    </div>
                                </div>
                            </div>

                            <!-- БЛОК 2: ТЕМА ОФОРМЛЕНИЯ (БЕЙДЖ СТО В РЕЕСТРЕ) -->
                            <div class="mb-3 pt-3 border-top">
                                <h6 class="mb-2 fw-bold text-dark d-flex align-items-center">
                                    <i class="bi bi-palette text-primary me-2"></i> Тема оформления бейджа СТО
                                </h6>
                                <small class="text-muted d-block mb-3">
                                    Этот бейдж будет отображаться в колонке СТО реестра дел
                                </small>

                                <div class="row g-3 align-items-center">
                                    <!-- Цвет фона -->
                                    <div class="col-12 col-sm-4">
                                        <label class="form-label small text-muted mb-1">Цвет фона бейджа</label>
                                        <div class="d-flex align-items-center gap-2">
                                            <input v-model="form.bg_color" type="color"
                                                class="form-control form-control-color"
                                                style="width: 44px; height: 34px;" />
                                            <input v-model="form.bg_color" type="text"
                                                class="form-control form-control-sm font-monospace" maxlength="7" />
                                        </div>
                                    </div>

                                    <!-- Цвет текста -->
                                    <div class="col-12 col-sm-4">
                                        <label class="form-label small text-muted mb-1">Цвет текста бейджа</label>
                                        <div class="d-flex align-items-center gap-2">
                                            <input v-model="form.text_color" type="color"
                                                class="form-control form-control-color"
                                                style="width: 44px; height: 34px;" />
                                            <input v-model="form.text_color" type="text"
                                                class="form-control form-control-sm font-monospace" maxlength="7" />
                                        </div>
                                    </div>

                                    <!-- Превью бейджа -->
                                    <div class="col-12 col-sm-4 text-center">
                                        <label class="form-label small text-muted mb-1 d-block">Превью в реестре</label>
                                        <div class="p-2 border rounded bg-white d-inline-flex justify-content-center align-items-center"
                                            style="min-width: 140px;">
                                            <span class="badge px-2 py-1 text-truncate" :style="{
                                                backgroundColor: form.bg_color || '#e0f2fe',
                                                color: form.text_color || '#0369a1',
                                                border: '1px solid rgba(0,0,0,0.1)',
                                                fontWeight: '600',
                                                fontSize: '0.72rem'
                                            }">
                                                {{ form.name || 'Название СТО' }}
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Быстрые пресеты -->
                                    <div class="col-12">
                                        <div class="d-flex align-items-center gap-1 flex-wrap">
                                            <span class="small text-muted me-2">Готовые темы:</span>
                                            <button v-for="(p, idx) in colorPresets" :key="idx" type="button"
                                                class="btn btn-sm py-0 px-2"
                                                :style="{ backgroundColor: p.bg, color: p.text, border: '1px solid rgba(0,0,0,0.15)', fontSize: '0.70rem' }"
                                                @click="applyPreset(p)">
                                                {{ p.label }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- БЛОК 3: ПРИВЯЗКА К НАШИМ ФИЛИАЛАМ -->
                            <div class="mb-3 pt-3 border-top">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div>
                                        <h6 class="mb-0 fw-bold text-dark d-flex align-items-center">
                                            <i class="bi bi-buildings text-primary me-2"></i> Привязка к нашим филиалам
                                        </h6>
                                        <small class="text-muted">Сервис будет доступен для выбора в заявках указанных
                                            филиалов</small>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none"
                                            @click="selectAllBranches">
                                            Выбрать все
                                        </button>
                                        <span class="text-muted">|</span>
                                        <button type="button"
                                            class="btn btn-link btn-sm p-0 text-decoration-none text-muted"
                                            @click="form.branch_ids = []">
                                            Снять все
                                        </button>
                                    </div>
                                </div>

                                <div class="p-3 border rounded bg-light" style="max-height: 140px; overflow-y: auto;">
                                    <div class="d-flex flex-wrap gap-2">
                                        <div v-for="b in branches" :key="b.id" class="form-check form-check-inline m-0">
                                            <input class="form-check-input" type="checkbox"
                                                :id="'service-branch-' + b.id" :value="b.id"
                                                v-model="form.branch_ids" />
                                            <label class="form-check-label small fw-semibold"
                                                :for="'service-branch-' + b.id">
                                                {{ b.our_name || b.name }}
                                            </label>
                                        </div>
                                        <div v-if="branches.length === 0" class="text-muted small">
                                            Филиалы пока не созданы. Добавьте их во вкладке «Наши филиалы».
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- БЛОК 4: ПРИМЕЧАНИЕ И СТАТУС -->
                            <div class="mb-2 pt-2 border-top">
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold text-muted">Примечание / Условия
                                        работы</label>
                                    <textarea v-model="form.comment" rows="2" class="form-control"
                                        placeholder="Реквизиты, условия по нормо-часу, специализация..."></textarea>
                                </div>

                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="serviceActiveSwitch"
                                        v-model="form.is_active" />
                                    <label class="form-check-label small fw-semibold text-dark"
                                        for="serviceActiveSwitch">
                                        Автосервис активен (отображается в списках выбора)
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer bg-light border-top py-2 px-4 d-flex justify-content-between">
                            <button type="button" class="btn btn-outline-secondary btn-sm" @click="closeModal"
                                :disabled="loading">
                                Отмена
                            </button>
                            <button type="submit" class="btn btn-primary btn-sm px-4 fw-semibold" :disabled="loading">
                                <span v-if="loading" class="spinner-border spinner-border-sm me-1"></span>
                                {{ isEdit ? 'Сохранить изменения' : 'Создать автосервис' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    branches: { type: Array, default: () => [] },
});

const emit = defineEmits(['saved']);
const modalRef = ref(null);
let bsModal = null;

const isEdit = ref(false);
const editingId = ref(null);
const loading = ref(false);
const generalError = ref('');
const phoneError = ref('');
const errors = ref({});

const serviceUsers = ref([]);

const colorPresets = [
    { label: 'Голубой', bg: '#e0f2fe', text: '#0369a1' },
    { label: 'Изумрудный', bg: '#dcfce7', text: '#15803d' },
    { label: 'Фиолетовый', bg: '#f3e8ff', text: '#7e22ce' },
    { label: 'Янтарный', bg: '#fef3c7', text: '#b45309' },
    { label: 'Индиго', bg: '#e0e7ff', text: '#3730a3' },
    { label: 'Розовый', bg: '#fce7f3', text: '#be185d' },
];

const initialForm = {
    name: '',
    user_id: null,
    phone: '',
    email: '',
    address: '',
    comment: '',
    bg_color: '#e0f2fe',
    text_color: '#0369a1',
    is_active: true,
    branch_ids: [],
};

const form = reactive({ ...initialForm });

// Загрузка только пользователей с ролью СТО
const fetchServiceUsers = async () => {
    try {
        const res = await axios.get('/api/admin/services/users');
        serviceUsers.value = res.data.data || [];
    } catch (err) {
        console.error('Ошибка загрузки пользователей СТО:', err);
    }
};

onMounted(() => {
    if (window.bootstrap && modalRef.value) {
        bsModal = new window.bootstrap.Modal(modalRef.value, { backdrop: 'static' });
    }
    fetchServiceUsers();
});

const getModalInstance = () => {
    if (!bsModal && modalRef.value && window.bootstrap) {
        bsModal = new window.bootstrap.Modal(modalRef.value, { backdrop: 'static' });
    }
    return bsModal;
};

// =========================================================
// МАСКА И ВАЛИДАЦИЯ ТЕЛЕФОНА: +7 (999) 000-00-00
// =========================================================
const formatPhoneString = (raw) => {
    if (!raw) return '';
    let digits = String(raw).replace(/\D/g, '');

    if (digits.startsWith('8')) {
        digits = '7' + digits.slice(1);
    } else if (!digits.startsWith('7') && digits.length > 0) {
        digits = '7' + digits;
    }

    digits = digits.slice(0, 11);
    if (digits.length <= 1) return '+7 (';

    let formatted = '+7 (';
    formatted += digits.slice(1, 4);

    if (digits.length >= 4) formatted += ') ';
    if (digits.length > 4) formatted += digits.slice(4, 7);
    if (digits.length >= 7) formatted += '-';
    if (digits.length > 7) formatted += digits.slice(7, 9);
    if (digits.length >= 9) formatted += '-';
    if (digits.length > 9) formatted += digits.slice(9, 11);

    return formatted;
};

const onPhoneInput = (event) => {
    const input = event.target;
    const formatted = formatPhoneString(input.value);
    form.phone = formatted;
    input.value = formatted;
    phoneError.value = '';
};

const isPhoneValid = (phone) => {
    if (!phone || !phone.trim()) return true;
    const digits = String(phone).replace(/\D/g, '');
    return digits.length === 11;
};

const applyPreset = (p) => {
    form.bg_color = p.bg;
    form.text_color = p.text;
};

const selectAllBranches = () => {
    form.branch_ids = props.branches.map((b) => b.id);
};

const open = (item = null) => {
    generalError.value = '';
    phoneError.value = '';
    errors.value = {};
    fetchServiceUsers();

    if (item) {
        isEdit.value = true;
        editingId.value = item.id;
        form.name = item.name || '';
        form.user_id = item.user_id || null;
        form.phone = formatPhoneString(item.phone || '');
        form.email = item.email || '';
        form.address = item.address || '';
        form.comment = item.comment || '';
        form.bg_color = item.bg_color || '#e0f2fe';
        form.text_color = item.text_color || '#0369a1';
        form.is_active = item.is_active ?? true;
        form.branch_ids = Array.isArray(item.branches) ? item.branches.map((b) => b.id) : [];
    } else {
        isEdit.value = false;
        editingId.value = null;
        Object.assign(form, initialForm);
        form.branch_ids = [];
    }

    getModalInstance()?.show();
};

const closeModal = () => {
    getModalInstance()?.hide();
};

const handleSubmit = async () => {
    phoneError.value = '';

    if (form.phone && !isPhoneValid(form.phone)) {
        phoneError.value = 'Введите полный номер телефона: +7 (XXX) XXX-XX-XX';
        return;
    }

    loading.value = true;
    generalError.value = '';
    errors.value = {};

    try {
        if (isEdit.value) {
            await axios.put(`/api/admin/services/${editingId.value}`, form);
        } else {
            await axios.post('/api/admin/services', form);
        }
        closeModal();
        emit('saved');
    } catch (err) {
        if (err.response?.status === 422) {
            errors.value = err.response.data.errors || {};
            generalError.value = err.response.data.message || 'Проверьте заполненные поля.';
        } else {
            generalError.value = err.response?.data?.message || 'Ошибка сохранения автосервиса.';
        }
    } finally {
        loading.value = false;
    }
};

defineExpose({ open, closeModal });
</script>
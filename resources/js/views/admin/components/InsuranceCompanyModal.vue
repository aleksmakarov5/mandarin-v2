<template>
    <Teleport to="body">

        <div class="modal fade" id="insuranceCompanyModal" tabindex="-1" ref="modalRef" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header bg-light border-bottom py-3">
                        <h5 class="modal-title fw-bold text-dark">
                            <i class="bi me-2 text-primary"
                                :class="isEdit ? 'bi-pencil-square' : 'bi-building-fill-add'"></i>
                            {{ isEdit ? 'Редактировать страховую компанию' : 'Новая страховая компания' }}
                        </h5>
                        <button type="button" class="btn-close" @click="closeModal" aria-label="Закрыть"></button>
                    </div>

                    <form @submit.prevent="handleSubmit">
                        <div class="modal-body p-4">
                            <div v-if="generalError" class="alert alert-danger py-2 small mb-3">
                                {{ generalError }}
                            </div>

                            <!-- Предпросмотр бейджа СК -->
                            <div class="p-3 bg-light rounded text-center mb-3 border">
                                <small class="text-muted d-block mb-1">Предпросмотр бейджа в таблице реестра:</small>
                                <span class="badge px-3 py-2 fw-bold shadow-sm" :style="{
                                    backgroundColor: form.bg_color,
                                    color: form.text_color,
                                    border: '1px solid ' + form.text_color + '55',
                                    fontSize: '0.85rem'
                                }">
                                    {{ form.name || 'НАИМЕНОВАНИЕ СК' }}
                                </span>
                            </div>

                            <div class="row g-3">
                                <!-- Наименование СК -->
                                <div class="col-12 col-md-6">
                                    <label class="form-label small fw-semibold text-muted">Наименование СК <span
                                            class="text-danger">*</span></label>
                                    <input v-model="form.name" type="text" class="form-control"
                                        :class="{ 'is-invalid': errors.name }"
                                        placeholder="ВСК, Ингосстрах, РЕСО, СОГАЗ..." required />
                                    <div v-if="errors.name" class="invalid-feedback">{{ errors.name[0] }}</div>
                                </div>

                                <!-- Куратор / Менеджер -->
                                <div class="col-12 col-md-6">
                                    <label class="form-label small fw-semibold text-muted">ФИО Куратора /
                                        Менеджера</label>
                                    <input v-model="form.curator" type="text" class="form-control"
                                        placeholder="Иванов Алексей" />
                                </div>

                                <!-- Настройка темы бейджа СК -->
                                <div class="col-12 col-md-6">
                                    <label class="form-label small fw-semibold text-muted">Цвет фона бейджа</label>
                                    <div class="input-group">
                                        <input v-model="form.bg_color" type="color"
                                            class="form-control form-control-color p-1" style="width: 48px;"
                                            title="Выбрать цвет фона" />
                                        <input v-model="form.bg_color" type="text"
                                            class="form-control text-uppercase font-monospace" maxlength="7" />
                                    </div>
                                </div>

                                <div class="col-12 col-md-6">
                                    <label class="form-label small fw-semibold text-muted">Цвет текста бейджа</label>
                                    <div class="input-group">
                                        <input v-model="form.text_color" type="color"
                                            class="form-control form-control-color p-1" style="width: 48px;"
                                            title="Выбрать цвет текста" />
                                        <input v-model="form.text_color" type="text"
                                            class="form-control text-uppercase font-monospace" maxlength="7" />
                                    </div>
                                </div>

                                <!-- Быстрые палитры для СК -->
                                <div class="col-12">
                                    <small class="text-muted d-block mb-1">Готовые палитры СК:</small>
                                    <div class="d-flex flex-wrap gap-1">
                                        <button type="button" class="btn btn-sm border py-0 px-2"
                                            style="font-size: 0.72rem; background: #fce7f3; color: #be185d;"
                                            @click="applyPreset('#fce7f3', '#be185d')">Розовый (Ингос / РЕСО)</button>
                                        <button type="button" class="btn btn-sm border py-0 px-2"
                                            style="font-size: 0.72rem; background: #e0f2fe; color: #0369a1;"
                                            @click="applyPreset('#e0f2fe', '#0369a1')">Голубой (ВСК)</button>
                                        <button type="button" class="btn btn-sm border py-0 px-2"
                                            style="font-size: 0.72rem; background: #f3e8ff; color: #7e22ce;"
                                            @click="applyPreset('#f3e8ff', '#7e22ce')">Фиолетовый (СОГАЗ)</button>
                                        <button type="button" class="btn btn-sm border py-0 px-2"
                                            style="font-size: 0.72rem; background: #ffedd5; color: #c2410c;"
                                            @click="applyPreset('#ffedd5', '#c2410c')">Оранжевый (Альфа /
                                            Югория)</button>
                                        <button type="button" class="btn btn-sm border py-0 px-2"
                                            style="font-size: 0.72rem; background: #dcfce7; color: #15803d;"
                                            @click="applyPreset('#dcfce7', '#15803d')">Зеленый (Сбер /
                                            Согласие)</button>
                                        <button type="button" class="btn btn-sm border py-0 px-2"
                                            style="font-size: 0.72rem; background: #f1f5f9; color: #334155;"
                                            @click="applyPreset('#f1f5f9', '#334155')">Серый (Прочее)</button>
                                    </div>
                                </div>

                                <!-- Контакты -->
                                <div class="col-12 col-md-6">
                                    <label class="form-label small fw-semibold text-muted">Телефон</label>
                                    <input v-model="form.phone" type="text" class="form-control"
                                        placeholder="+7 (999) 000-00-00" />
                                </div>

                                <div class="col-12 col-md-6">
                                    <label class="form-label small fw-semibold text-muted">Email</label>
                                    <input v-model="form.email" type="email" class="form-control"
                                        placeholder="curator@company.ru" />
                                </div>

                                <div class="col-12">
                                    <label class="form-label small fw-semibold text-muted">Адрес отдела выплат</label>
                                    <input v-model="form.address" type="text" class="form-control"
                                        placeholder="г. Москва, ул. Примерная, д. 1" />
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
                                {{ isEdit ? 'Сохранить' : 'Создать' }}
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

const emit = defineEmits(['saved']);
const modalRef = ref(null);
let bsModal = null;

const isEdit = ref(false);
const editingId = ref(null);
const loading = ref(false);
const generalError = ref('');
const errors = ref({});

const initialForm = {
    name: '',
    bg_color: '#fce7f3',
    text_color: '#be185d',
    curator: '',
    phone: '',
    email: '',
    address: '',
};

const form = reactive({ ...initialForm });

onMounted(() => {
    if (window.bootstrap && modalRef.value) {
        bsModal = new window.bootstrap.Modal(modalRef.value, { backdrop: 'static' });
    }
});

const applyPreset = (bg, text) => {
    form.bg_color = bg;
    form.text_color = text;
};

const open = (item = null) => {
    generalError.value = '';
    errors.value = {};
    if (item) {
        isEdit.value = true;
        editingId.value = item.id;
        form.name = item.name || '';
        form.bg_color = item.bg_color || '#fce7f3';
        form.text_color = item.text_color || '#be185d';
        form.curator = item.curator || item.manager || '';
        form.phone = item.phone || '';
        form.email = item.email || '';
        form.address = item.address || '';
    } else {
        isEdit.value = false;
        editingId.value = null;
        Object.assign(form, initialForm);
    }
    bsModal?.show();
};

const closeModal = () => bsModal?.hide();

const handleSubmit = async () => {
    loading.value = true;
    generalError.value = '';
    errors.value = {};

    try {
        if (isEdit.value) {
            await axios.put(`/api/admin/insurance-companies/${editingId.value}`, form);
        } else {
            await axios.post('/api/admin/insurance-companies', form);
        }
        closeModal();
        emit('saved');
    } catch (err) {
        if (err.response?.status === 422) {
            errors.value = err.response.data.errors || {};
            generalError.value = err.response.data.message || 'Проверьте заполненные поля.';
        } else {
            generalError.value = err.response?.data?.message || 'Ошибка сохранения страховой компании.';
        }
    } finally {
        loading.value = false;
    }
};

defineExpose({ open, closeModal });
</script>
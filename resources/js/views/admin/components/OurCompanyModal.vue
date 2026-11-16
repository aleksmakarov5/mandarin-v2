<template>
    <div class="modal fade" id="ourCompanyModal" tabindex="-1" ref="modalRef" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-light border-bottom py-3">
                    <h5 class="modal-title fw-bold text-dark">
                        <i class="bi me-2 text-primary" :class="isEdit ? 'bi-pencil-square' : 'bi-shop'"></i>
                        {{ isEdit ? 'Редактировать филиал' : 'Новый филиал' }}
                    </h5>
                    <button type="button" class="btn-close" @click="closeModal" aria-label="Закрыть"></button>
                </div>

                <form @submit.prevent="handleSubmit">
                    <div class="modal-body p-4">
                        <!-- Ошибки валидации -->
                        <div v-if="generalError" class="alert alert-danger py-2 small mb-3">
                            {{ generalError }}
                        </div>

                        <div class="row g-3">
                            <!-- Название -->
                            <div class="col-12 col-md-7">
                                <label class="form-label small fw-semibold text-muted">Название филиала / СТО *</label>
                                <input v-model="form.name" type="text" class="form-control"
                                    :class="{ 'is-invalid': errors.name }" placeholder="Мандарин Восток" required />
                                <div v-if="errors.name" class="invalid-feedback">{{ errors.name[0] }}</div>
                            </div>

                            <!-- Юр. лицо -->
                            <div class="col-12 col-md-5">
                                <label class="form-label small fw-semibold text-muted">Юридическое лицо</label>
                                <select v-model="form.id_legal" class="form-select">
                                    <option :value="null">Не привязано</option>
                                    <option v-for="l in legalEntities" :key="l.id" :value="l.id">
                                        {{ l.name }}
                                    </option>
                                </select>
                            </div>

                            <!-- Email -->
                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-semibold text-muted">Email филиала *</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-envelope"></i></span>
                                    <input v-model="form.email" type="email" class="form-control"
                                        :class="{ 'is-invalid': errors.email }" placeholder="branch@mandarin.local"
                                        required />
                                    <div v-if="errors.email" class="invalid-feedback">{{ errors.email[0] }}</div>
                                </div>
                            </div>

                            <!-- Почтовый отправитель (Mailer) -->
                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-semibold text-muted">Mailer (отправитель)</label>
                                <input v-model="form.mailer" type="text" class="form-control"
                                    placeholder="smtp_mandarin_vostok" />
                            </div>

                            <!-- Телефон и Директор -->
                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-semibold text-muted">Телефон</label>
                                <input v-model="form.phone" type="text" class="form-control"
                                    placeholder="+7 (999) 000-00-00" />
                            </div>



                            <!-- Адрес -->
                            <div class="col-12">
                                <label class="form-label small fw-semibold text-muted">Фактический адрес филиала</label>
                                <input v-model="form.address" type="text" class="form-control"
                                    placeholder="г. Москва, ул. Примерная, д. 10" />
                            </div>



                            <!-- Подпись филиала -->
                            <div class="col-12">
                                <label class="form-label small fw-semibold text-muted">Подпись (Sign) в письмах и
                                    документах</label>
                                <textarea v-model="form.sign" rows="3" class="form-control"
                                    placeholder="С уважением, автосервис Мандарин..."></textarea>
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
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import axios from 'axios';

defineProps({
    legalEntities: { type: Array, default: () => [] },
});

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
    email: '',
    mailer: '',
    id_legal: null,
    inn: '',
    kpp: '',
    ogrn: '',
    address: '',
    phone: '',
    director: '',
    sign: '',
};

const form = reactive({ ...initialForm });

onMounted(() => {
    if (window.bootstrap && modalRef.value) {
        bsModal = new window.bootstrap.Modal(modalRef.value, { backdrop: 'static' });
    }
});

const open = (item = null) => {
    generalError.value = '';
    errors.value = {};
    if (item) {
        isEdit.value = true;
        editingId.value = item.id;
        form.name = item.name || '';
        form.email = item.email || '';
        form.mailer = item.mailer || '';
        form.id_legal = item.id_legal || null;
        form.inn = item.inn || '';
        form.kpp = item.kpp || '';
        form.ogrn = item.ogrn || '';
        form.address = item.address || '';
        form.phone = item.phone || '';
        form.director = item.director || '';
        form.sign = item.sign || '';
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
            await axios.put(`/api/admin/our-companies/${editingId.value}`, form);
        } else {
            await axios.post('/api/admin/our-companies', form);
        }
        closeModal();
        emit('saved');
    } catch (err) {
        if (err.response?.status === 422) {
            errors.value = err.response.data.errors || {};
            generalError.value = err.response.data.message || 'Проверьте заполненные поля.';
        } else {
            generalError.value = err.response?.data?.message || 'Ошибка сохранения филиала.';
        }
    } finally {
        loading.value = false;
    }
};

defineExpose({ open, closeModal });
</script>
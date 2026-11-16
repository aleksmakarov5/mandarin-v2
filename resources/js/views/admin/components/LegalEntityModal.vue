<template>
    <div class="modal fade" id="legalEntityModal" tabindex="-1" ref="modalRef">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-light border-bottom py-3">
                    <h5 class="modal-title fw-bold text-dark">
                        <i class="bi me-2 text-primary"
                            :class="isEdit ? 'bi-pencil-square' : 'bi-plus-circle-fill'"></i>
                        {{ isEdit ? 'Редактировать юр. лицо' : 'Новое юр. лицо' }}
                    </h5>
                    <button type="button" class="btn-close" @click="closeModal"></button>
                </div>
                <form @submit.prevent="handleSubmit">
                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-semibold text-muted">Краткое название *</label>
                                <input v-model="form.name" type="text" class="form-control" placeholder="ООО «Мандарин»"
                                    required />
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-semibold text-muted">ИНН</label>
                                <input v-model="form.inn" type="text" class="form-control" placeholder="7700000000" />
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-semibold text-muted">КПП</label>
                                <input v-model="form.kpp" type="text" class="form-control" placeholder="770101001" />
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-semibold text-muted">ОГРН</label>
                                <input v-model="form.ogrn" type="text" class="form-control" />
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold text-muted">Полное фирменное
                                    наименование</label>
                                <input v-model="form.full_name" type="text" class="form-control"
                                    placeholder="Общество с ограниченной ответственностью «...»" />
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold text-muted">Юридический адрес</label>
                                <input v-model="form.legal_address" type="text" class="form-control" />
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-semibold text-muted">Телефон</label>
                                <input v-model="form.phone" type="text" class="form-control" />
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-semibold text-muted">Email</label>
                                <input v-model="form.email" type="email" class="form-control" />
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-semibold text-muted">ФИО Руководителя</label>
                                <input v-model="form.director_name" type="text" class="form-control" />
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-semibold text-muted">Банк</label>
                                <input v-model="form.bank_name" type="text" class="form-control" />
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-semibold text-muted">БИК</label>
                                <input v-model="form.bik" type="text" class="form-control" />
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-semibold text-muted">Расчетный счет</label>
                                <input v-model="form.checking_account" type="text" class="form-control" />
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light border-top py-2 px-4 d-flex justify-content-between">
                        <button type="button" class="btn btn-outline-secondary btn-sm"
                            @click="closeModal">Отмена</button>
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

const emit = defineEmits(['saved']);
const modalRef = ref(null);
let bsModal = null;
const isEdit = ref(false);
const editingId = ref(null);
const loading = ref(false);

const initialForm = {
    name: '', full_name: '', inn: '', kpp: '', ogrn: '',
    legal_address: '', phone: '', email: '', director_name: '',
    bank_name: '', bik: '', checking_account: ''
};
const form = reactive({ ...initialForm });

onMounted(() => {
    if (window.bootstrap && modalRef.value) {
        bsModal = new window.bootstrap.Modal(modalRef.value, { backdrop: 'static' });
    }
});

const open = (item = null) => {
    if (item) {
        isEdit.value = true;
        editingId.value = item.id;
        Object.assign(form, item);
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
    try {
        if (isEdit.value) {
            await axios.put(`/api/admin/legal-entities/${editingId.value}`, form);
        } else {
            await axios.post('/api/admin/legal-entities', form);
        }
        closeModal();
        emit('saved');
    } catch (err) {
        alert(err.response?.data?.message || 'Ошибка сохранения');
    } finally {
        loading.value = false;
    }
};

defineExpose({ open, closeModal });
</script>
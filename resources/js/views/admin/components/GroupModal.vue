<template>
    <Teleport to="body">

        <div class="modal fade" id="groupModal" tabindex="-1" ref="modalRef" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header bg-light border-bottom py-3">
                        <h5 class="modal-title fw-bold text-dark">
                            <i class="bi me-2 text-primary"
                                :class="isEdit ? 'bi-pencil-square' : 'bi-person-badge-fill'"></i>
                            {{ isEdit ? 'Редактировать роль' : 'Новая роль / группа' }}
                        </h5>
                        <button type="button" class="btn-close" @click="closeModal" aria-label="Закрыть"></button>
                    </div>

                    <form @submit.prevent="handleSubmit">
                        <div class="modal-body p-4">
                            <div v-if="generalError" class="alert alert-danger py-2 small mb-3">
                                {{ generalError }}
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-muted">Наименование роли *</label>
                                <input v-model="form.name" type="text" class="form-control"
                                    :class="{ 'is-invalid': errors.name }"
                                    placeholder="Например: Мастер МРК, Снабженец, Приемщик" required />
                                <div v-if="errors.name" class="invalid-feedback">{{ errors.name[0] }}</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-muted">Системный код *</label>
                                <input v-model="form.code" type="text" class="form-control"
                                    :class="{ 'is-invalid': errors.code }" placeholder="mrk, zap, owner, inspector"
                                    required :disabled="isEdit && form.code === 'sys_admin'" />
                                <div v-if="errors.code" class="invalid-feedback">{{ errors.code[0] }}</div>
                                <small class="text-muted">Только латинские буквы, цифры и знак подчеркивания.</small>
                            </div>

                            <div class="mb-2">
                                <label class="form-label small fw-semibold text-muted">Описание обязанностей</label>
                                <textarea v-model="form.description" rows="3" class="form-control"
                                    placeholder="Краткое описание функций сотрудника с этой ролью"></textarea>
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
    code: '',
    description: '',
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
        form.code = item.code || '';
        form.description = item.description || '';
    } else {
        isEdit.value = false;
        editingId.value = null;
        Object.assign(form, initialForm);
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

    try {
        if (isEdit.value) {
            await axios.put(`/api/admin/user-groups/${editingId.value}`, form);
        } else {
            await axios.post('/api/admin/user-groups', form);
        }
        closeModal();
        emit('saved');
    } catch (err) {
        if (err.response?.status === 422) {
            errors.value = err.response.data.errors || {};
            generalError.value = err.response.data.message || 'Проверьте корректность заполнения полей.';
        } else {
            generalError.value = err.response?.data?.message || 'Ошибка сохранения роли.';
        }
    } finally {
        loading.value = false;
    }
};

defineExpose({ open, closeModal });
</script>
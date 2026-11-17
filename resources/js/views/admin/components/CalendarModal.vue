<template>
    <div class="modal fade" id="calendarModal" tabindex="-1" ref="modalRef" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-light border-bottom py-3">
                    <h5 class="modal-title fw-bold text-dark">
                        <i class="bi me-2 text-primary" :class="isEdit ? 'bi-pencil-square' : 'bi-calendar-plus'"></i>
                        {{ isEdit ? 'Редактировать день' : 'Добавить исключение в календарь' }}
                    </h5>
                    <button type="button" class="btn-close" @click="closeModal" aria-label="Закрыть"></button>
                </div>

                <form @submit.prevent="handleSubmit">
                    <div class="modal-body p-4">
                        <div v-if="generalError" class="alert alert-danger py-2 small mb-3">
                            {{ generalError }}
                        </div>

                        <!-- Дата -->
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">Дата <span
                                    class="text-danger">*</span></label>
                            <input v-model="form.date" type="date" class="form-control"
                                :class="{ 'is-invalid': errors.date }" required />
                            <div v-if="errors.date" class="invalid-feedback">{{ errors.date[0] }}</div>
                        </div>

                        <!-- Тип дня -->
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">Тип дня <span
                                    class="text-danger">*</span></label>
                            <select v-model="form.is_workday" class="form-select" required>
                                <option :value="false">🔴 Нерабочий / Праздничный день / Перенесенный выходной</option>
                                <option :value="true">🟢 Рабочая суббота / Перенесенный рабочий день</option>
                            </select>
                        </div>

                        <!-- Комментарий / Описание -->
                        <div class="mb-2">
                            <label class="form-label small fw-semibold text-muted">Описание / Праздник</label>
                            <input v-model="form.comment" type="text" class="form-control"
                                placeholder="Например: Перенос выходного с 27 апреля, День города" />
                        </div>
                    </div>

                    <div class="modal-footer bg-light border-top py-2 px-4 d-flex justify-content-between">
                        <button type="button" class="btn btn-outline-secondary btn-sm" @click="closeModal"
                            :disabled="loading">
                            Отмена
                        </button>
                        <button type="submit" class="btn btn-primary btn-sm px-4 fw-semibold" :disabled="loading">
                            <span v-if="loading" class="spinner-border spinner-border-sm me-1"></span>
                            {{ isEdit ? 'Сохранить' : 'Добавить' }}
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
const generalError = ref('');
const errors = ref({});

const initialForm = {
    date: new Date().toISOString().split('T')[0],
    is_workday: false,
    comment: '',
};

const form = reactive({ ...initialForm });

onMounted(() => {
    if (window.bootstrap && modalRef.value) {
        bsModal = new window.bootstrap.Modal(modalRef.value, { backdrop: 'static' });
    }
});

const getModalInstance = () => {
    if (!bsModal && modalRef.value && window.bootstrap) {
        bsModal = new window.bootstrap.Modal(modalRef.value, { backdrop: 'static' });
    }
    return bsModal;
};

const open = (item = null, defaultYear = null) => {
    generalError.value = '';
    errors.value = {};

    if (item) {
        isEdit.value = true;
        editingId.value = item.id;
        form.date = item.date;
        form.is_workday = Boolean(item.is_workday);
        form.comment = item.comment || '';
    } else {
        isEdit.value = false;
        editingId.value = null;
        Object.assign(form, initialForm);
        if (defaultYear) {
            form.date = `${defaultYear}-01-01`;
        }
    }

    getModalInstance()?.show();
};

const closeModal = () => {
    getModalInstance()?.hide();
};

const handleSubmit = async () => {
    loading.value = true;
    generalError.value = '';
    errors.value = {};

    try {
        if (isEdit.value) {
            await axios.put(`/api/admin/calendar-exceptions/${editingId.value}`, form);
        } else {
            await axios.post('/api/admin/calendar-exceptions', form);
        }
        closeModal();
        emit('saved');
    } catch (err) {
        if (err.response?.status === 422) {
            errors.value = err.response.data.errors || {};
            generalError.value = err.response.data.message || 'Проверьте заполненные поля.';
        } else {
            generalError.value = err.response?.data?.message || 'Ошибка сохранения дня.';
        }
    } finally {
        loading.value = false;
    }
};

defineExpose({ open, closeModal });
</script>
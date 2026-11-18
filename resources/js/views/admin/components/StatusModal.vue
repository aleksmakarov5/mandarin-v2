<template>
    <Teleport to="body">

        <div class="modal fade" id="statusModal" tabindex="-1" ref="modalRef" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header bg-light border-bottom py-3">
                        <h5 class="modal-title fw-bold text-dark">
                            <i class="bi me-2 text-primary" :class="isEdit ? 'bi-pencil-square' : 'bi-tags-fill'"></i>
                            {{ isEdit ? 'Редактировать статус' : 'Новый статус' }}
                        </h5>
                        <button type="button" class="btn-close" @click="closeModal" aria-label="Закрыть"></button>
                    </div>

                    <form @submit.prevent="handleSubmit">
                        <div class="modal-body p-4">
                            <div v-if="generalError" class="alert alert-danger py-2 small mb-3">
                                {{ generalError }}
                            </div>

                            <!-- Предпросмотр бейджа со статусом -->
                            <div class="p-3 bg-light rounded text-center mb-3 border">
                                <small class="text-muted d-block mb-1">Предпросмотр отображения:</small>
                                <span class="badge px-3 py-2 fw-semibold shadow-sm" :style="{
                                    backgroundColor: form.bg_color,
                                    color: form.text_color,
                                    border: '1px solid ' + form.text_color + '33',
                                    fontSize: '0.85rem'
                                }">
                                    {{ form.name || 'Пример статуса' }}
                                </span>
                            </div>

                            <!-- Название статуса -->
                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-muted">Название статуса <span
                                        class="text-danger">*</span></label>
                                <input v-model="form.name" type="text" class="form-control"
                                    :class="{ 'is-invalid': errors.name }" placeholder="Например: В работе у МРК"
                                    required />
                                <div v-if="errors.name" class="invalid-feedback">{{ errors.name[0] }}</div>
                            </div>

                            <!-- Настройка цветов фона и текста -->
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label small fw-semibold text-muted">Цвет фона</label>
                                    <div class="input-group">
                                        <input v-model="form.bg_color" type="color"
                                            class="form-control form-control-color p-1" style="width: 48px;"
                                            title="Выбрать цвет фона" />
                                        <input v-model="form.bg_color" type="text"
                                            class="form-control form-control-sm text-uppercase font-monospace"
                                            maxlength="7" />
                                    </div>
                                </div>

                                <div class="col-6">
                                    <label class="form-label small fw-semibold text-muted">Цвет текста</label>
                                    <div class="input-group">
                                        <input v-model="form.text_color" type="color"
                                            class="form-control form-control-color p-1" style="width: 48px;"
                                            title="Выбрать цвет текста" />
                                        <input v-model="form.text_color" type="text"
                                            class="form-control form-control-sm text-uppercase font-monospace"
                                            maxlength="7" />
                                    </div>
                                </div>
                            </div>

                            <!-- Быстрые пресеты популярных стилей -->
                            <div class="mb-3">
                                <small class="text-muted d-block mb-1">Готовые палитры:</small>
                                <div class="d-flex flex-wrap gap-1">
                                    <button type="button" class="btn btn-sm border py-0 px-2"
                                        style="font-size: 0.72rem; background: #e0f2fe; color: #0369a1;"
                                        @click="applyPreset('#e0f2fe', '#0369a1')">Голубой</button>
                                    <button type="button" class="btn btn-sm border py-0 px-2"
                                        style="font-size: 0.72rem; background: #ffedd5; color: #c2410c;"
                                        @click="applyPreset('#ffedd5', '#c2410c')">Оранжевый</button>
                                    <button type="button" class="btn btn-sm border py-0 px-2"
                                        style="font-size: 0.72rem; background: #dcfce7; color: #15803d;"
                                        @click="applyPreset('#dcfce7', '#15803d')">Зеленый</button>
                                    <button type="button" class="btn btn-sm border py-0 px-2"
                                        style="font-size: 0.72rem; background: #fee2e2; color: #b91c1c;"
                                        @click="applyPreset('#fee2e2', '#b91c1c')">Красный</button>
                                    <button type="button" class="btn btn-sm border py-0 px-2"
                                        style="font-size: 0.72rem; background: #fef3c7; color: #b45309;"
                                        @click="applyPreset('#fef3c7', '#b45309')">Желтый</button>
                                    <button type="button" class="btn btn-sm border py-0 px-2"
                                        style="font-size: 0.72rem; background: #f3e8ff; color: #7e22ce;"
                                        @click="applyPreset('#f3e8ff', '#7e22ce')">Фиолетовый</button>
                                    <button type="button" class="btn btn-sm border py-0 px-2"
                                        style="font-size: 0.72rem; background: #f1f5f9; color: #334155;"
                                        @click="applyPreset('#f1f5f9', '#334155')">Серый</button>
                                </div>
                            </div>

                            <!-- Ответственная роль -->
                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-muted">Ответственная роль</label>
                                <select v-model="form.user_group_id" class="form-select form-select-sm">
                                    <option :value="null">Общий статус (для всех)</option>
                                    <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
                                </select>
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

const props = defineProps({
    groups: { type: Array, default: () => [] },
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
    color: 'secondary',
    bg_color: '#f1f5f9',
    text_color: '#334155',
    user_group_id: null,
    time: 0,
    is_day: false,
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
        form.color = item.color || 'secondary';
        form.bg_color = item.bg_color || '#f1f5f9';
        form.text_color = item.text_color || '#334155';
        form.user_group_id = item.user_group_id || item.group?.id || null;
        form.time = item.time || 0;
        form.is_day = Boolean(item.is_day);
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
            await axios.put(`/api/admin/application-statuses/${editingId.value}`, form);
        } else {
            await axios.post('/api/admin/application-statuses', form);
        }
        closeModal();
        emit('saved');
    } catch (err) {
        if (err.response?.status === 422) {
            errors.value = err.response.data.errors || {};
            generalError.value = err.response.data.message || 'Проверьте заполненные поля.';
        } else {
            generalError.value = err.response?.data?.message || 'Ошибка сохранения статуса.';
        }
    } finally {
        loading.value = false;
    }
};

defineExpose({ open, closeModal });
</script>
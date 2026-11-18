<template>
    <Teleport to="body">

        <div class="modal fade" id="statusModal" tabindex="-1" ref="modalRef" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header bg-light border-bottom py-3">
                        <h5 class="modal-title fw-bold text-dark">
                            <i class="bi me-2 text-primary" :class="isEdit ? 'bi-pencil-square' : 'bi-tags-fill'"></i>
                            {{ isEdit ? 'Редактировать статус заявки' : 'Новый статус заявки' }}
                        </h5>
                        <button type="button" class="btn-close" @click="closeModal" aria-label="Закрыть"></button>
                    </div>

                    <form @submit.prevent="handleSubmit">
                        <div class="modal-body p-4">
                            <div v-if="generalError" class="alert alert-danger py-2 small mb-3">
                                {{ generalError }}
                            </div>

                            <!-- Предпросмотр бейджа -->
                            <div class="p-3 bg-light rounded text-center mb-3 border">
                                <small class="text-muted d-block mb-1">Предпросмотр темы статуса:</small>
                                <span class="badge px-3 py-2 fw-semibold shadow-sm" :style="{
                                    backgroundColor: form.bg_color,
                                    color: form.text_color,
                                    border: '1px solid ' + form.text_color + '44',
                                    fontSize: '0.85rem'
                                }">
                                    {{ form.name || 'Пример статуса дела' }}
                                </span>
                            </div>

                            <!-- Название статуса -->
                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-muted">Название статуса <span
                                        class="text-danger">*</span></label>
                                <input v-model="form.name" type="text" class="form-control"
                                    :class="{ 'is-invalid': errors.name }"
                                    placeholder="Например: Расчет МРК, Согласование со страховой" required />
                                <div v-if="errors.name" class="invalid-feedback">{{ errors.name[0] }}</div>
                            </div>

                            <!-- Ответственная роль -->
                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-muted">Ответственная роль</label>
                                <select v-model="form.user_group_id" class="form-select form-select-sm">
                                    <option :value="null">Общий статус (для всех)</option>
                                    <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
                                </select>
                            </div>

                            <!-- Настройка темы: фон и текст -->
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

                            <!-- Готовые пресеты цветов -->
                            <div class="mb-3">
                                <small class="text-muted d-block mb-1">Готовые пресеты:</small>
                                <div class="d-flex flex-wrap gap-1">
                                    <button type="button" class="btn btn-sm border py-0 px-2"
                                        style="font-size: 0.72rem; background: #fef08a; color: #854d0e;"
                                        @click="applyPreset('#fef08a', '#854d0e')">Желтый</button>
                                    <button type="button" class="btn btn-sm border py-0 px-2"
                                        style="font-size: 0.72rem; background: #e0f2fe; color: #0369a1;"
                                        @click="applyPreset('#e0f2fe', '#0369a1')">Голубой</button>
                                    <button type="button" class="btn btn-sm border py-0 px-2"
                                        style="font-size: 0.72rem; background: #dcfce7; color: #15803d;"
                                        @click="applyPreset('#dcfce7', '#15803d')">Зеленый</button>
                                    <button type="button" class="btn btn-sm border py-0 px-2"
                                        style="font-size: 0.72rem; background: #fee2e2; color: #b91c1c;"
                                        @click="applyPreset('#fee2e2', '#b91c1c')">Красный</button>
                                    <button type="button" class="btn btn-sm border py-0 px-2"
                                        style="font-size: 0.72rem; background: #f3e8ff; color: #7e22ce;"
                                        @click="applyPreset('#f3e8ff', '#7e22ce')">Фиолетовый</button>
                                    <button type="button" class="btn btn-sm border py-0 px-2"
                                        style="font-size: 0.72rem; background: #f1f5f9; color: #334155;"
                                        @click="applyPreset('#f1f5f9', '#334155')">Серый</button>
                                </div>
                            </div>

                            <!-- БЛОК НАСТРОЙКИ ВРЕМЕНИ И ЕДИНИЦ ИЗМЕРЕНИЯ (time + is_day) -->
                            <div class="p-3 bg-light rounded border mb-2">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="bi bi-stopwatch text-primary me-2 fs-5"></i>
                                    <div>
                                        <div class="fw-bold text-dark small">Нормативный таймер статуса</div>
                                        <small class="text-muted" style="font-size: 0.75rem;">Лимит времени нахождения
                                            дела
                                            в данном статусе</small>
                                    </div>
                                </div>

                                <div class="row g-2 align-items-center mt-1">
                                    <div class="col-7">
                                        <label class="form-label small text-muted mb-1">Количество</label>
                                        <input v-model.number="form.time" type="number" min="0" step="1"
                                            class="form-control form-control-sm" placeholder="0 — без таймера" />
                                    </div>
                                    <div class="col-5">
                                        <label class="form-label small text-muted mb-1">Ед. измерения</label>
                                        <select v-model="form.is_day" class="form-select form-select-sm">
                                            <option :value="false">Часов</option>
                                            <option :value="true">Раб. дней</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="mt-2 text-muted small" style="font-size: 0.75rem;">
                                    <span v-if="!form.time || form.time <= 0">Без ограничения времени.</span>
                                    <span v-else>
                                        Норматив: <strong>{{ form.time }} {{ form.is_day ? 'раб. дн.' : 'ч.' }}</strong>
                                    </span>
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
    bg_color: '#fef08a',
    text_color: '#854d0e',
    user_group_id: null,
    time: 0,
    is_day: true,
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
        form.bg_color = item.bg_color || '#fef08a';
        form.text_color = item.text_color || '#854d0e';
        form.user_group_id = item.user_group_id || item.group?.id || null;
        form.time = item.time !== undefined && item.time !== null ? item.time : 0;
        form.is_day = item.is_day !== undefined ? Boolean(item.is_day) : true;
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
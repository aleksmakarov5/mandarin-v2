<template>
    <Teleport to="body">
        <div v-if="show" class="modal-backdrop fade show" style="z-index: 1055"></div>
        <div v-if="show" class="modal d-block" tabindex="-1" role="dialog" style="z-index: 1056"
            @click.self="$emit('update:show', false)">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header bg-light">
                        <h5 class="modal-title fs-6 fw-bold">
                            <i class="bi bi-person-plus text-primary me-2"></i> Новый юрист
                        </h5>
                        <button type="button" class="btn-close" @click="$emit('update:show', false)"></button>
                    </div>

                    <form @submit.prevent="submit">
                        <div class="modal-body">
                            <div v-if="error" class="alert alert-danger py-2 small">{{ error }}</div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">ФИО *</label>
                                <input v-model="form.name" type="text" class="form-control form-control-sm" required />
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label fw-semibold">Телефон</label>
                                    <input v-model="form.phone" type="tel" class="form-control form-control-sm"
                                        placeholder="+7..." />
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-semibold">Email</label>
                                    <input v-model="form.email" type="email" class="form-control form-control-sm" />
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Примечание</label>
                                <textarea v-model="form.comment" class="form-control form-control-sm"
                                    rows="2"></textarea>
                            </div>

                            <div class="form-check form-switch">
                                <input id="coop" v-model="form.is_cooperating" class="form-check-input"
                                    type="checkbox" />
                                <label class="form-check-label" for="coop">Активное сотрудничество</label>
                            </div>
                        </div>

                        <div class="modal-footer bg-light">
                            <button type="button" class="btn btn-sm btn-outline-secondary"
                                @click="$emit('update:show', false)">Отмена</button>
                            <button type="submit" class="btn btn-sm btn-primary" :disabled="loading">
                                {{ loading ? 'Сохранение...' : 'Создать' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { reactive, ref } from 'vue';
import axios from 'axios';

const props = defineProps({ show: Boolean });
const emit = defineEmits(['update:show', 'created']);

const loading = ref(false);
const error = ref('');
const form = reactive({
    name: '', phone: '', email: '', comment: '', is_cooperating: true
});

async function submit() {
    loading.value = true;
    error.value = '';
    try {
        const response = await axios.post('/api/general-lawyers', {
            ...form,
            is_cooperating: form.is_cooperating ? 1 : 0
        });
        console.log('Успех! Пытаюсь закрыть окно...');
        emit('created', response.data.data); // Передаем данные нового юриста родителю
        emit('update:show', false);
    } catch (e) {
        e.value = 'Ошибка при создании юриста';
    } finally {
        loading.value = false;
    }
}
</script>
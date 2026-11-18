<template>
    <Teleport to="body">

        <div class="modal fade" id="cityModal" tabindex="-1" ref="modalRef">
            <div class="modal-dialog modal-dialog-centered modal-sm">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header bg-light border-bottom py-3">
                        <h6 class="modal-title fw-bold text-dark">
                            {{ isEdit ? 'Изменить город' : 'Добавить город' }}
                        </h6>
                        <button type="button" class="btn-close" @click="closeModal"></button>
                    </div>
                    <form @submit.prevent="handleSubmit">
                        <div class="modal-body p-3">
                            <label class="form-label small fw-semibold text-muted">Название города *</label>
                            <input v-model="form.name" type="text" class="form-control" placeholder="Москва" required
                                autofocus />
                        </div>
                        <div class="modal-footer bg-light border-top py-2 px-3 d-flex justify-content-between">
                            <button type="button" class="btn btn-outline-secondary btn-sm"
                                @click="closeModal">Отмена</button>
                            <button type="submit" class="btn btn-primary btn-sm px-3" :disabled="loading">
                                {{ isEdit ? 'Сохранить' : 'Добавить' }}
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

const form = reactive({ name: '' });

onMounted(() => {
    if (window.bootstrap && modalRef.value) {
        bsModal = new window.bootstrap.Modal(modalRef.value, { backdrop: 'static' });
    }
});

const open = (item = null) => {
    if (item) {
        isEdit.value = true;
        editingId.value = item.id;
        form.name = item.name;
    } else {
        isEdit.value = false;
        editingId.value = null;
        form.name = '';
    }
    bsModal?.show();
};

const closeModal = () => bsModal?.hide();

const handleSubmit = async () => {
    loading.value = true;
    try {
        if (isEdit.value) {
            await axios.put(`/api/admin/cities/${editingId.value}`, form);
        } else {
            await axios.post('/api/admin/cities', form);
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
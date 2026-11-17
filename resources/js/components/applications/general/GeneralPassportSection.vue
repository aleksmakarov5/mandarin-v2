<template>
    <section class="card shadow-sm border-0" @focusout="commit" @change="commit">
        <div class="card-header bg-white fw-bold py-2 border-bottom">
            Паспорт РФ
        </div>

        <div class="card-body p-3">
            <div class="row g-2">
                <div class="col-6 col-md-3 col-xl-2">
                    <label class="form-label">Серия</label>
                    <input v-model="form.ClientPasport" type="text" class="form-control form-control-sm"
                        maxlength="50" />
                </div>

                <div class="col-6 col-md-3 col-xl-2">
                    <label class="form-label">Номер</label>
                    <input v-model="form.ClientPasport_number" type="text" class="form-control form-control-sm"
                        maxlength="50" />
                </div>

                <div class="col-12 col-md-6 col-xl-3">
                    <label class="form-label">Дата выдачи</label>
                    <input v-model="form.ClientPasport_date" type="date" class="form-control form-control-sm" />
                </div>

                <div class="col-12 col-xl-5">
                    <label class="form-label">Кем выдан</label>
                    <textarea v-model="form.ClientPasport_struct" class="form-control form-control-sm" rows="2"
                        maxlength="2000"></textarea>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
defineProps({
    form: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['commit']);

function commit(event) {
    if (['INPUT', 'SELECT', 'TEXTAREA'].includes(event.target?.tagName)) {
        emit('commit');
    }
}
</script>

<style scoped>
.form-label {
    margin-bottom: 0.25rem;
    color: #64748b;
    font-size: 0.76rem;
    font-weight: 600;
}
</style>
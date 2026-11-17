<template>
    <section class="card shadow-sm border-0 h-100" @focusout="commit" @change="commit">
        <div class="card-body p-3">
            <h6 class="fw-bold mb-3 text-uppercase staff-title">
                Ответственные лица
            </h6>

            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label class="form-label mb-0">Менеджер МРК</label>
                </div>

                <select v-model.number="form.id_MRK" class="form-select form-select-sm">
                    <option :value="null">Не назначен</option>
                    <option v-for="employee in staff.mrk" :key="employee.id" :value="Number(employee.id)">
                        {{ employee.name }}
                    </option>
                </select>
            </div>

            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label class="form-label mb-0">Наставник</label>
                </div>

                <select v-model.number="form.id_mentor" class="form-select form-select-sm">
                    <option :value="null">Без наставника</option>
                    <option v-for="employee in staff.mentors" :key="employee.id" :value="Number(employee.id)">
                        {{ employee.name }}
                    </option>
                </select>
            </div>

            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label class="form-label mb-0">Запчастист СПМ</label>
                </div>

                <select v-model.number="form.id_spm" class="form-select form-select-sm">
                    <option :value="null">Не назначен</option>
                    <option v-for="employee in staff.spm" :key="employee.id" :value="Number(employee.id)">
                        {{ employee.name }}
                    </option>
                </select>
            </div>

            <hr />

            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label class="form-label mb-0">Юрист по делу</label>
                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none"
                        @click="$emit('new-lawyer')">
                        + Новый юрист
                    </button>
                </div>

                <select id="application-lawyer" v-model.number="form.id_lawyer" class="form-select form-select-sm">
                    <option :value="null">Без юриста</option>
                    <option v-for="lawyer in lawyers" :key="lawyer.id" :value="Number(lawyer.id)"
                        :style="{ color: lawyer.is_cooperating ? '#15803d' : '#dc2626' }">
                        {{ lawyer.is_cooperating ? '●' : '○' }} {{ lawyer.name }}
                    </option>
                </select>

            </div>

            <div>
                <h6 class="fw-bold mb-2 text-uppercase staff-title">
                    Заметка по делу
                </h6>

                <textarea v-model="form.comment_true" class="form-control form-control-sm" rows="7" maxlength="10000"
                    placeholder="Внутренняя служебная заметка"></textarea>
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
    staff: {
        type: Object,
        required: true,
    },
    lawyers: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['commit', 'new-lawyer']);

function commit(event) {
    if (['INPUT', 'SELECT', 'TEXTAREA'].includes(event.target?.tagName)) {
        emit('commit');
    }
}
</script>

<style scoped>
.staff-title {
    color: #1e293b;
    font-size: 0.82rem;
    letter-spacing: 0.025em;
}

.form-label {
    color: #64748b;
    font-size: 0.76rem;
    font-weight: 600;
}

.role-badge {
    padding: 0.12rem 0.38rem;
    color: #64748b;
    background: #f8fafc;
    border: 1px solid #cbd5e1;
    border-radius: 0.3rem;
    font-family: monospace;
    font-size: 0.66rem;
}
</style>
<template>
    <section class="card shadow-sm border-0" @focusout="commit" @change="commit">
        <div class="card-header bg-white fw-bold py-2 border-bottom">
            Страховая компания и убыток
        </div>

        <div class="card-body p-3">
            <div class="row g-2">
                <div class="col-12 col-md-6">
                    <label class="form-label">Страховая компания</label>
                    <select v-model.number="form.id_insuranceCompany" class="form-select form-select-sm">
                        <option :value="null">Не выбрана</option>
                        <option v-for="company in insuranceCompanies" :key="company.id" :value="Number(company.id)">
                            {{ company.name }}
                        </option>
                    </select>
                </div>

                <div class="col-12 col-sm-6 col-md-3">
                    <label class="form-label">Тип убытка</label>
                    <select v-model.number="form.ub_type" class="form-select form-select-sm">
                        <option :value="null">Не указан</option>
                        <option :value="0">ОСАГО</option>
                        <option :value="1">КАСКО</option>
                    </select>
                </div>

                <div class="col-12 col-sm-6 col-md-3">
                    <label class="form-label">Номер убытка</label>
                    <input v-model="form.ub_number" type="text" class="form-control form-control-sm" maxlength="100" />
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label">Номер заявления / дела СК</label>
                    <input v-model="form.numberInsuranceCompanyApplication" type="text"
                        class="form-control form-control-sm" maxlength="191" />
                </div>

                <div class="col-12 col-sm-6 col-md-3">
                    <label class="form-label">Дата ДТП</label>
                    <input v-model="form.DTPdate" type="date" class="form-control form-control-sm" />
                </div>

                <div class="col-12 col-sm-6 col-md-3">
                    <label class="form-label">Дата направления</label>
                    <input v-model="form.napr_date" type="date" class="form-control form-control-sm" />
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label">Номер договора</label>
                    <input v-model="form.dogovor" type="text" class="form-control form-control-sm" maxlength="100" />
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
    insuranceCompanies: {
        type: Array,
        default: () => [],
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
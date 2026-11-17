<template>
    <section class="card shadow-sm border-0" @focusout="commit" @change="commit">
        <div class="card-header bg-white fw-bold py-2 border-bottom">
            Филиал, СТО и сроки
        </div>

        <div class="card-body p-3">
            <div class="row g-2 mb-3">
                <div class="col-12 col-md-4">
                    <label class="form-label">Наш филиал</label>
                    <select v-model.number="form.our_company" class="form-select form-select-sm">
                        <option :value="null">Не выбран</option>
                        <option v-for="branch in branches" :key="branch.id" :value="Number(branch.id)">
                            {{ branch.name }}
                        </option>
                    </select>
                </div>

                <div class="col-12 col-md-4">
                    <label class="form-label">Город</label>
                    <select v-model.number="form.id_city" class="form-select form-select-sm">
                        <option :value="null">Не выбран</option>
                        <option v-for="city in cities" :key="city.id" :value="Number(city.id)">
                            {{ city.name }}
                        </option>
                    </select>
                </div>

                <div class="col-12 col-md-4">
                    <label class="form-label">СТО</label>
                    <select v-model.number="form.id_service" class="form-select form-select-sm">
                        <option :value="null">Не выбрана</option>
                        <option v-for="service in services" :key="service.id" :value="Number(service.id)">
                            {{ service.name }}
                        </option>
                    </select>
                </div>
            </div>

            <div class="row g-2">
                <div class="col-6 col-md-3">
                    <label class="form-label">Планируемый осмотр</label>
                    <input v-model="form.view_date" type="datetime-local" class="form-control form-control-sm" />
                </div>

                <div class="col-6 col-md-3">
                    <label class="form-label">Начало ремонта</label>
                    <input v-model="form.start_date" type="date" class="form-control form-control-sm" />
                </div>

                <div class="col-6 col-md-3">
                    <label class="form-label">Поставка запчастей</label>
                    <input v-model="form.sp_date" type="date" class="form-control form-control-sm" />
                </div>

                <div class="col-6 col-md-3">
                    <label class="form-label">Окончание ремонта</label>
                    <input :value="form.end_date" type="date" class="form-control form-control-sm" readonly disabled
                        title="Рассчитывается автоматически: начало ремонта + 30 рабочих дней" />
                </div>
            </div>

            <div class="form-text mt-2">
                Окончание ремонта рассчитывается автоматически после сохранения начала
                ремонта: прибавляются 30 рабочих дней без суббот и воскресений.
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
    branches: {
        type: Array,
        default: () => [],
    },
    cities: {
        type: Array,
        default: () => [],
    },
    services: {
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
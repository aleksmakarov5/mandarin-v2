<template>
    <section class="card shadow-sm border-0 h-100" @focusout="commit" @change="commit">
        <div class="card-header bg-white fw-bold py-2 border-bottom">
            Автомобиль
        </div>

        <div class="card-body p-3">
            <div class="mb-2">
                <label class="form-label">Марка и модель</label>
                <input v-model="form.CarModel" type="text" class="form-control form-control-sm" maxlength="191"
                    placeholder="Toyota RAV4" />
            </div>

            <div class="row g-2 mb-2">
                <div class="col-12 col-sm-6">
                    <label class="form-label">Госномер</label>
                    <input v-model="form.gnz" type="text" class="form-control form-control-sm text-uppercase"
                        maxlength="32" placeholder="А001АА29" />
                </div>

                <div class="col-12 col-sm-6">
                    <label class="form-label">Год выпуска</label>
                    <input v-model="form.mnf_year" type="text" class="form-control form-control-sm" maxlength="10"
                        placeholder="2020" />
                </div>
            </div>

            <div class="mb-2">
                <label class="form-label">VIN</label>
                <input v-model="form.vin" type="text" class="form-control form-control-sm text-uppercase font-monospace"
                    maxlength="32" />
            </div>

            <div class="mb-2">
                <label class="form-label">ПТС / свидетельство</label>
                <input v-model="form.vehicle_passport" type="text" class="form-control form-control-sm text-uppercase"
                    maxlength="100" />
            </div>

            <div class="row g-2">
                <div class="col-12 col-sm-6">
                    <label class="form-label">Цвет</label>
                    <input v-model="form.Color" type="text" class="form-control form-control-sm" maxlength="100" />
                </div>

                <div class="col-12 col-sm-6">
                    <label class="form-label">Пробег, км</label>
                    <input v-model.number="form.mileage" type="number" min="0" class="form-control form-control-sm" />
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
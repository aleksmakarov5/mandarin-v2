<template>
    <section class="card shadow-sm border-0 h-100" @focusout="commit" @change="commit">
        <div class="card-header bg-white fw-bold py-2 border-bottom">
            Автомобиль
        </div>
        <div v-if="!readonly">

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
                    <input v-model="form.vin" type="text"
                        class="form-control form-control-sm text-uppercase font-monospace" maxlength="32" />
                </div>

                <div class="mb-2">
                    <label class="form-label">ПТС / свидетельство</label>
                    <input v-model="form.vehicle_passport" type="text"
                        class="form-control form-control-sm text-uppercase" maxlength="100" />
                </div>

                <div class="row g-2">
                    <div class="col-12 col-sm-6">
                        <label class="form-label">Цвет</label>
                        <input v-model="form.color" type="text" class="form-control form-control-sm" maxlength="100" />
                    </div>

                    <div class="col-12 col-sm-6">
                        <label class="form-label">Пробег, км</label>
                        <input v-model.number="form.mileage" type="number" min="0"
                            class="form-control form-control-sm" />
                    </div>
                </div>
            </div>
        </div>

        <div v-else class="readonly-grid">
            <div class="readonly-field">
                <span>Марка и модель</span>
                <strong>{{ form.CarModel || '—' }}</strong>
            </div>

            <div class="readonly-field">
                <span>Госномер</span>
                <strong>{{ form.gnz || '—' }}</strong>
            </div>

            <div class="readonly-field">
                <span>Год выпуска</span>
                <strong>{{ form.mnf_year || '—' }}</strong>
            </div>

            <div class="readonly-field">
                <span>VIN</span>
                <strong>{{ form.vin || '—' }}</strong>
            </div>

            <div class="readonly-field">
                <span>ПТС / свидетельство</span>
                <strong>{{ form.vehicle_passport || '—' }}</strong>
            </div>

            <div class="readonly-field">
                <span>Цвет</span>
                <strong>{{ form.color || form.Color || '—' }}</strong>
            </div>

            <div class="readonly-field">
                <span>Пробег, км</span>
                <strong>{{ form.mileage ?? '—' }}</strong>
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
    readonly: {
        type: Boolean,
        default: false,
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

.readonly-grid {
    display: grid;
    gap: 0.65rem;
}

.readonly-field {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    padding-bottom: 0.45rem;
    border-bottom: 1px solid #f1f5f9;
}

.readonly-field span {
    color: #64748b;
    font-size: 0.72rem;
    font-weight: 600;
}

.readonly-field strong {
    color: #1e293b;
    font-size: 0.82rem;
    font-weight: 600;
    overflow-wrap: anywhere;
}
</style>
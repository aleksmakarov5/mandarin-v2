<template>
    <section class="card shadow-sm border-0 h-100" @focusout="commit" @change="commit">
        <div class="card-header bg-white fw-bold py-2 border-bottom">
            Клиент
        </div>

        <div class="card-body p-3">
            <div class="mb-2">
                <label class="form-label">ФИО клиента</label>
                <input v-model="form.ClientName" type="text" class="form-control form-control-sm" maxlength="191" />
            </div>

            <div class="row g-2 mb-2">
                <div class="col-12 col-sm-5">
                    <label class="form-label">Пол</label>
                    <select v-model.number="form.gender" class="form-select form-select-sm">
                        <option :value="null">Не указан</option>
                        <option :value="0">Мужской</option>
                        <option :value="1">Женский</option>
                    </select>
                </div>

                <div class="col-12 col-sm-7">
                    <label class="form-label">Дата рождения</label>
                    <input v-model="form.birthday" type="date" class="form-control form-control-sm" />
                </div>
            </div>

            <div class="mb-2">
                <label class="form-label">Телефон клиента</label>
                <input :value="form.ClientPhone" type="tel" class="form-control form-control-sm font-monospace"
                    maxlength="18" placeholder="+7 (999) 123-45-67" @input="maskPhone($event, 'ClientPhone')" />
            </div>

            <div class="mb-2">
                <label class="form-label">Представитель</label>
                <input v-model="form.pred" type="text" class="form-control form-control-sm" maxlength="191" />
            </div>

            <div>
                <label class="form-label">Телефон представителя</label>
                <input :value="form.pred_phone" type="tel" class="form-control form-control-sm font-monospace"
                    maxlength="18" placeholder="+7 (999) 123-45-67" @input="maskPhone($event, 'pred_phone')" />
            </div>
        </div>
    </section>
</template>

<script setup>
const props = defineProps({
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

function maskPhone(event, field) {
    const source = event.target.value;

    if (!source.trim()) {
        props.form[field] = '';
        return;
    }

    let digits = source.replace(/\D/g, '');

    if (!digits) {
        props.form[field] = '';
        return;
    }

    if (digits[0] === '8') {
        digits = '7' + digits.slice(1);
    } else if (digits[0] !== '7') {
        digits = '7' + digits;
    }

    digits = digits.slice(0, 11);

    let result = '+7';

    if (digits.length > 1) {
        result += ' (' + digits.slice(1, 4);
    }

    if (digits.length >= 4) {
        result += ')';
    }

    if (digits.length > 4) {
        result += ' ' + digits.slice(4, 7);
    }

    if (digits.length > 7) {
        result += '-' + digits.slice(7, 9);
    }

    if (digits.length > 9) {
        result += '-' + digits.slice(9, 11);
    }

    props.form[field] = result;
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
<template>
    <Teleport to="body">
        <div class="modal fade" id="legalEntityModal" tabindex="-1" ref="modalRef">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header bg-light border-bottom py-3">
                        <h5 class="modal-title fw-bold text-dark">
                            <i class="bi me-2 text-primary"
                                :class="isEdit ? 'bi-pencil-square' : 'bi-plus-circle-fill'"></i>
                            {{ isEdit ? 'Редактировать юр. лицо' : 'Новое юр. лицо' }}
                        </h5>

                        <button type="button" class="btn-close" @click="closeModal"></button>
                    </div>

                    <form @submit.prevent="handleSubmit">
                        <div class="modal-body p-4">
                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <label class="form-label small fw-semibold text-muted">
                                        Краткое название *
                                    </label>
                                    <input v-model="form.name" type="text" class="form-control"
                                        placeholder="ООО «Мандарин»" required />
                                </div>

                                <div class="col-12 col-md-6">
                                    <label class="form-label small fw-semibold text-muted">
                                        ИНН
                                    </label>
                                    <input v-model="form.inn" type="text" class="form-control"
                                        placeholder="7700000000" />
                                </div>

                                <div class="col-12 col-md-6">
                                    <label class="form-label small fw-semibold text-muted">
                                        КПП
                                    </label>
                                    <input v-model="form.kpp" type="text" class="form-control"
                                        placeholder="770101001" />
                                </div>

                                <div class="col-12 col-md-6">
                                    <label class="form-label small fw-semibold text-muted">
                                        ОГРН
                                    </label>
                                    <input v-model="form.ogrn" type="text" class="form-control" />
                                </div>

                                <div class="col-12">
                                    <label class="form-label small fw-semibold text-muted">
                                        Полное фирменное наименование
                                    </label>
                                    <input v-model="form.full_name" type="text" class="form-control"
                                        placeholder="Общество с ограниченной ответственностью «...»" />
                                </div>

                                <div class="col-12">
                                    <label class="form-label small fw-semibold text-muted">
                                        Юридический адрес
                                    </label>
                                    <input v-model="form.legal_address" type="text" class="form-control" />
                                </div>

                                <div class="col-12 col-md-6">
                                    <label class="form-label small fw-semibold text-muted">
                                        Телефон
                                    </label>
                                    <input v-model="form.phone" type="text" class="form-control" />
                                </div>

                                <div class="col-12 col-md-6">
                                    <label class="form-label small fw-semibold text-muted">
                                        Email
                                    </label>
                                    <input v-model="form.email" type="email" class="form-control" />
                                </div>

                                <div class="col-12 col-md-6">
                                    <label class="form-label small fw-semibold text-muted">
                                        ФИО Руководителя
                                    </label>
                                    <input v-model="form.director_name" type="text" class="form-control" />
                                </div>

                                <div class="col-12 col-md-6">
                                    <label class="form-label small fw-semibold text-muted">
                                        Банк
                                    </label>
                                    <input v-model="form.bank_name" type="text" class="form-control" />
                                </div>

                                <div class="col-12 col-md-6">
                                    <label class="form-label small fw-semibold text-muted">
                                        БИК
                                    </label>
                                    <input v-model="form.bik" type="text" class="form-control" />
                                </div>

                                <div class="col-12 col-md-6">
                                    <label class="form-label small fw-semibold text-muted">
                                        Расчетный счет
                                    </label>
                                    <input v-model="form.checking_account" type="text" class="form-control" />
                                </div>

                                <!-- Финансовые настройки юридического лица -->
                                <div class="col-12">
                                    <div class="border rounded bg-light p-3 mt-1">
                                        <div class="d-flex align-items-center gap-2 mb-3">
                                            <i class="bi bi-calculator text-primary"></i>
                                            <span class="fw-bold text-dark">
                                                Налоги и финансовые расчёты
                                            </span>
                                        </div>

                                        <div class="row g-3">
                                            <div class="col-12 col-md-6">
                                                <label for="legal-entity-nds-rate"
                                                    class="form-label small fw-semibold text-muted">
                                                    Ставка НДС
                                                </label>

                                                <div class="input-group">
                                                    <input id="legal-entity-nds-rate" v-model.number="form.nds_rate"
                                                        type="number" min="0" max="100" step="0.01" class="form-control"
                                                        placeholder="Не настроено" />
                                                    <span class="input-group-text">
                                                        %
                                                    </span>
                                                </div>

                                                <div class="form-text">
                                                    0 — без НДС. Пустое поле — ставка не настроена.
                                                </div>
                                            </div>

                                            <div class="col-12 col-md-6">
                                                <label for="legal-entity-income-tax-rate"
                                                    class="form-label small fw-semibold text-muted">
                                                    Налог с дохода
                                                </label>

                                                <div class="input-group">
                                                    <input id="legal-entity-income-tax-rate"
                                                        v-model.number="form.income_tax_rate" type="number" min="0"
                                                        max="100" step="0.01" class="form-control"
                                                        placeholder="Не настроено" />
                                                    <span class="input-group-text">
                                                        %
                                                    </span>
                                                </div>

                                                <div class="form-text">
                                                    Процент от дохода для управленческого
                                                    расчёта прибыли.
                                                </div>
                                            </div>
                                        </div>

                                        <div class="small text-muted mt-3">
                                            Настройки используются для финансовых расчётов
                                            дел филиалов, связанных с этим юридическим лицом.
                                            Новые ставки применяются при следующем пересчёте.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer bg-light border-top py-2 px-4 d-flex justify-content-between">
                            <button type="button" class="btn btn-outline-secondary btn-sm" @click="closeModal">
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

const emit = defineEmits(['saved']);
const modalRef = ref(null);
let bsModal = null;
const isEdit = ref(false);
const editingId = ref(null);
const loading = ref(false);

const initialForm = {
    name: '',
    full_name: '',
    inn: '',
    kpp: '',
    ogrn: '',
    legal_address: '',
    phone: '',
    email: '',
    director_name: '',
    bank_name: '',
    bik: '',
    checking_account: '',

    nds_rate: '',
    income_tax_rate: '',
};

const form = reactive({ ...initialForm });

onMounted(() => {
    if (window.bootstrap && modalRef.value) {
        bsModal = new window.bootstrap.Modal(
            modalRef.value,
            { backdrop: 'static' }
        );
    }
});

const open = (item = null) => {
    if (item) {
        isEdit.value = true;
        editingId.value = item.id;

        // Сохраняем прежнюю загрузку всех полей записи.
        Object.assign(form, item);

        // Для старой записи без ставок показываем пустые поля.
        // При этом явный ноль не заменяется пустым значением.
        form.nds_rate = item.nds_rate ?? '';
        form.income_tax_rate = item.income_tax_rate ?? '';
    } else {
        isEdit.value = false;
        editingId.value = null;
        Object.assign(form, initialForm);
    }

    bsModal?.show();
};

const closeModal = () => bsModal?.hide();

// Пустая ставка — не настроена, нулевая — установлена явно.
// Проверку диапазона и точности выполняет backend.
const normalizeRateForRequest = (value) => {
    if (value === null || value === undefined) {
        return null;
    }

    const text = String(value).trim();

    return text === '' ? null : text.replace(',', '.');
};

const handleSubmit = async () => {
    loading.value = true;

    try {
        const payload = {
            ...form,
            nds_rate: normalizeRateForRequest(form.nds_rate),
            income_tax_rate: normalizeRateForRequest(
                form.income_tax_rate
            ),
        };

        if (isEdit.value) {
            await axios.put(
                `/api/admin/legal-entities/${editingId.value}`,
                payload
            );
        } else {
            await axios.post(
                '/api/admin/legal-entities',
                payload
            );
        }

        closeModal();
        emit('saved');
    } catch (err) {
        const validationErrors = err.response?.data?.errors;

        const firstFieldError = validationErrors
            ? Object.values(validationErrors)
                .flat()
                .find(message => typeof message === 'string')
            : null;

        alert(
            firstFieldError
            || err.response?.data?.message
            || 'Ошибка сохранения'
        );
    } finally {
        loading.value = false;
    }
};

defineExpose({ open, closeModal });
</script>
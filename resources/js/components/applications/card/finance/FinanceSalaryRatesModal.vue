<template>
    <Teleport to="body">
        <div v-if="modelValue" class="rates-overlay" @click.self="close" @keydown.esc.stop.prevent="close">
            <form ref="dialog" class="rates-modal" role="dialog" aria-modal="true" :aria-labelledby="headingId"
                :aria-busy="loading || saving" tabindex="-1" @submit.prevent="save" @keydown.tab="trapFocus">
                <div class="rates-heading">
                    <div>
                        <h3 :id="headingId">
                            Проценты сотрудников
                        </h3>

                        <span>Дело #{{ applicationId }}</span>
                    </div>

                    <button type="button" class="close-button" aria-label="Закрыть" :disabled="saving" @click="close">
                        ×
                    </button>
                </div>

                <div class="rates-body">
                    <div v-if="loading" class="loading-message">
                        Загрузка настроек…
                    </div>

                    <template v-else-if="loaded">
                        <p class="rates-note">
                            Проценты рассчитываются от фактической
                            прибыли. При убытке зарплата по делу
                            равна нулю.
                        </p>

                        <section v-for="item in employees" :key="item.role" class="employee-card">
                            <div class="employee-heading">
                                <strong>{{ item.label }}</strong>
                                <span>{{ item.employee.name }}</span>
                            </div>

                            <label>
                                Индивидуальный процент по делу

                                <div class="rate-input-group">
                                    <input v-model="form[item.role]" type="text" inputmode="decimal" autocomplete="off"
                                        placeholder="По умолчанию" :disabled="saving" />

                                    <span>%</span>
                                </div>
                            </label>

                            <div class="default-rate">
                                По умолчанию:
                                <b>
                                    {{
                                        item.employee.default_rate === null
                                            ? 'не настроено'
                                            : `${percent(item.employee.default_rate)}%`
                                    }}
                                </b>

                                <button type="button" :disabled="saving" @click="form[item.role] = ''">
                                    Использовать по умолчанию
                                </button>
                            </div>
                        </section>

                        <div v-if="!employees.length" class="rates-note">
                            В деле не назначены МРК и наставник.
                        </div>

                        <p class="rates-help">
                            Пустое поле — стандартный процент.
                            Значение 0 — индивидуальная ставка 0%.
                        </p>
                    </template>

                    <div v-if="error" class="rates-error" role="alert">
                        {{ error }}
                    </div>

                    <button v-if="!loaded && !loading" type="button" class="retry-button" @click="load">
                        Повторить загрузку
                    </button>
                </div>

                <div class="rates-footer">
                    <button type="button" :disabled="saving" @click="close">
                        Отмена
                    </button>

                    <button type="submit" class="save-button" :disabled="loading
                        || saving
                        || !loaded
                        || !employees.length
                        ">
                        {{ saving ? 'Сохранение…' : 'Сохранить' }}
                    </button>
                </div>
            </form>
        </div>
    </Teleport>
</template>

<script setup>
import {
    computed,
    nextTick,
    onBeforeUnmount,
    reactive,
    ref,
    watch,
} from 'vue';

import axios from 'axios';
import { percent } from './financeFormatters';

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false,
    },
    applicationId: {
        type: [Number, String],
        required: true,
    },
});

const emit = defineEmits([
    'update:modelValue',
    'saved',
    'busy-change',
]);

const dialog = ref(null);
const loading = ref(false);
const saving = ref(false);
const loaded = ref(false);
const error = ref('');

const data = ref({
    mrk: null,
    mentor: null,
});

const form = reactive({
    mrk: '',
    mentor: '',
});

const headingId = `salary-rates-${Math.random()
    .toString(36)
    .slice(2)}`;

let controller = null;
let version = 0;
let previousFocus = null;

const employees = computed(() => {
    return [
        {
            role: 'mrk',
            label: 'МРК',
            employee: data.value.mrk,
        },
        {
            role: 'mentor',
            label: 'Наставник',
            employee: data.value.mentor,
        },
    ].filter(item => item.employee);
});

watch(
    () => loading.value || saving.value,
    value => emit('busy-change', value),
    { flush: 'sync' }
);

function inputRate(value) {
    if (value === null || value === undefined) {
        return '';
    }

    const rate = Number(value);

    return `${Math.floor(rate / 100)}.${String(rate % 100)
        .padStart(2, '0')}`;
}

function parseRate(value, label) {
    const text = String(value)
        .trim()
        .replace(',', '.');

    if (text === '') return null;

    if (!/^\d+(?:\.\d{1,2})?$/.test(text)) {
        throw new Error(
            `${label}: укажите процент с точностью до двух знаков.`
        );
    }

    const [whole, fraction = ''] = text.split('.');

    const rate = Number(whole) * 100
        + Number(fraction.padEnd(2, '0'));

    if (
        !Number.isSafeInteger(rate)
        || rate < 0
        || rate > 10000
    ) {
        throw new Error(
            `${label}: процент должен быть от 0 до 100.`
        );
    }

    return rate;
}

function errorText(exception, fallback) {
    const errors = exception.response?.data?.errors;

    return (
        errors
            ? Object.values(errors).flat().find(
                message => typeof message === 'string'
            )
            : null
    ) || exception.response?.data?.message
        || fallback;
}

async function load() {
    if (!props.modelValue || saving.value) return;

    controller?.abort();

    const requestVersion = ++version;
    const requestController = new AbortController();

    controller = requestController;
    loading.value = true;
    loaded.value = false;
    error.value = '';

    try {
        const response = await axios.get(
            `/api/applications/${props.applicationId}/finance/salary-rates`,
            {
                signal: requestController.signal,
                headers: {
                    Accept: 'application/json',
                },
            }
        );

        if (requestVersion !== version) return;

        const result = response.data?.data;

        if (
            !result
            || Number(result.application_id)
            !== Number(props.applicationId)
        ) {
            throw new Error('INVALID_SALARY_RATES_RESPONSE');
        }

        data.value = {
            mrk: result.mrk ?? null,
            mentor: result.mentor ?? null,
        };

        form.mrk = inputRate(data.value.mrk?.custom_rate);
        form.mentor = inputRate(data.value.mentor?.custom_rate);

        loaded.value = true;

        await nextTick();

        if (requestVersion === version) {
            dialog.value?.querySelector('input')?.focus();
        }
    } catch (exception) {
        if (requestVersion !== version) return;
        if (exception.code === 'ERR_CANCELED') return;

        error.value = errorText(
            exception,
            'Не удалось получить настройки процентов.'
        );
    } finally {
        if (requestVersion === version) {
            loading.value = false;
            controller = null;
        }
    }
}

async function save() {
    if (loading.value || saving.value || !loaded.value) {
        return;
    }

    error.value = '';

    let payload;

    try {
        payload = {
            mrk_proc: parseRate(form.mrk, 'МРК'),
            mentor_proc: parseRate(form.mentor, 'Наставник'),
        };
    } catch (exception) {
        error.value = exception.message;
        return;
    }

    const applicationId = Number(props.applicationId);
    const requestVersion = ++version;
    const requestController = new AbortController();

    controller = requestController;
    saving.value = true;

    let successful = false;

    try {
        await axios.patch(
            `/api/applications/${applicationId}/finance/salary-rates`,
            payload,
            {
                signal: requestController.signal,
                headers: {
                    Accept: 'application/json',
                },
            }
        );

        if (requestVersion !== version) return;

        successful = true;
    } catch (exception) {
        if (requestVersion !== version) return;
        if (exception.code === 'ERR_CANCELED') return;

        error.value = errorText(
            exception,
            'Не удалось сохранить проценты.'
        );
    } finally {
        if (requestVersion === version) {
            saving.value = false;
            controller = null;
        }
    }

    if (successful && requestVersion === version) {
        close();
        emit('saved', applicationId);
    }
}

function restoreFocus() {
    if (
        previousFocus instanceof HTMLElement
        && previousFocus.isConnected
    ) {
        previousFocus.focus();
    }
}

function close() {
    if (saving.value) return;

    emit('update:modelValue', false);
    restoreFocus();
}

function trapFocus(event) {
    const elements = Array.from(
        dialog.value?.querySelectorAll(
            'button:not(:disabled), input:not(:disabled)'
        ) ?? []
    );

    if (!elements.length) {
        event.preventDefault();
        dialog.value?.focus();
        return;
    }

    const first = elements[0];
    const last = elements[elements.length - 1];
    const active = document.activeElement;

    if (
        !dialog.value?.contains(active)
        || active === dialog.value
    ) {
        event.preventDefault();
        (event.shiftKey ? last : first).focus();
    } else if (event.shiftKey && active === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && active === last) {
        event.preventDefault();
        first.focus();
    }
}

watch(
    [
        () => props.modelValue,
        () => props.applicationId,
    ],
    async ([opened], previous) => {
        version += 1;
        controller?.abort();
        controller = null;

        loading.value = false;
        saving.value = false;
        loaded.value = false;
        error.value = '';

        if (!opened) return;

        if (!previous?.[0]) {
            previousFocus = document.activeElement;
        }

        await nextTick();
        dialog.value?.focus();

        load();
    },
    { immediate: true }
);

onBeforeUnmount(() => {
    version += 1;
    controller?.abort();
    emit('busy-change', false);
});
</script>

<style scoped>
.rates-overlay {
    position: fixed;
    inset: 0;
    z-index: 12500;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
    background: rgb(15 23 42 / 45%);
}

.rates-modal {
    width: 470px;
    max-width: 100%;
    max-height: calc(100dvh - 32px);
    overflow-y: auto;
    border-radius: 9px;
    background: #ffffff;
    box-shadow: 0 18px 50px rgb(15 23 42 / 25%);
}

.rates-heading,
.rates-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 12px 14px;
    background: #f8fafc;
}

.rates-heading {
    border-bottom: 1px solid #e2e8f0;
}

.rates-heading h3 {
    margin: 0 0 3px;
    color: #0f172a;
    font-size: 15px;
}

.rates-heading span {
    color: #64748b;
    font-size: 11px;
}

.close-button {
    padding: 0 3px;
    border: 0;
    background: transparent;
    color: #64748b;
    font-size: 24px;
    cursor: pointer;
}

.rates-body {
    padding: 14px;
}

.rates-note,
.rates-help,
.loading-message {
    color: #64748b;
    font-size: 11px;
    line-height: 1.5;
}

.rates-note {
    margin: 0 0 12px;
}

.rates-help {
    margin: 12px 0 0;
}

.employee-card {
    padding: 12px;
    border: 1px solid #dbe4ee;
    border-radius: 6px;
    background: #f8fafc;
}

.employee-card+.employee-card {
    margin-top: 10px;
}

.employee-heading {
    display: flex;
    flex-direction: column;
    gap: 3px;
    margin-bottom: 10px;
    color: #334155;
    font-size: 12px;
}

.employee-heading span {
    color: #64748b;
    font-size: 11px;
}

.employee-card label {
    display: block;
    color: #475569;
    font-size: 11px;
}

.rate-input-group {
    display: flex;
    margin-top: 5px;
}

.rate-input-group input {
    flex: 1;
    min-width: 0;
    padding: 8px 10px;
    border: 1px solid #cbd5e1;
    border-radius: 5px 0 0 5px;
    background: #ffffff;
    color: #0f172a;
    font-size: 14px;
}

.rate-input-group>span {
    display: flex;
    align-items: center;
    padding: 0 12px;
    border: 1px solid #cbd5e1;
    border-left: 0;
    border-radius: 0 5px 5px 0;
    background: #f1f5f9;
}

.default-rate {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 5px;
    margin-top: 7px;
    color: #64748b;
    font-size: 10px;
}

.default-rate button {
    padding: 0;
    border: 0;
    background: transparent;
    color: #0284c7;
    font: inherit;
    cursor: pointer;
}

.rates-error {
    margin-top: 10px;
    padding: 8px;
    border-radius: 5px;
    background: #fef2f2;
    color: #b91c1c;
    font-size: 12px;
}

.rates-footer {
    justify-content: flex-end;
    border-top: 1px solid #e2e8f0;
}

.rates-footer button,
.retry-button {
    padding: 7px 12px;
    border: 1px solid #cbd5e1;
    border-radius: 5px;
    background: #ffffff;
    color: #334155;
    font-size: 12px;
    cursor: pointer;
}

.rates-footer .save-button {
    border-color: #0284c7;
    background: #0284c7;
    color: #ffffff;
}

button:disabled,
input:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

button:focus-visible,
input:focus-visible {
    outline: 2px solid #38bdf8;
    outline-offset: 2px;
}
</style>
<template>
    <Teleport to="body">
        <div class="document-modal-overlay" @click.self="close" @keydown.esc.stop.prevent="close">
            <form ref="dialog" class="document-modal" role="dialog" aria-modal="true"
                aria-label="Назначить тип документа" tabindex="-1" @submit.prevent="save" @keydown.tab="trapFocus">
                <h6>Назначить тип документа</h6>

                <p class="file-title">{{ document.title }}</p>

                <label>
                    Категория

                    <select v-model="category" :disabled="saving">
                        <option value="">Выберите категорию</option>

                        <option v-for="item in availableCategories" :key="item.code" :value="item.code">
                            {{ item.title }}
                        </option>
                    </select>
                </label>

                <label>
                    Тип документа

                    <select v-model="typeId" :disabled="saving || !category" required>
                        <option value="">Выберите тип</option>

                        <option v-for="item in filteredTypes" :key="item.id" :value="item.id">
                            {{ item.name }}
                        </option>
                    </select>
                </label>

                <div v-if="error" class="modal-error" role="alert">
                    {{ error }}
                </div>

                <footer>
                    <button type="button" :disabled="saving" @click="close">
                        Отмена
                    </button>

                    <button type="submit" class="primary" :disabled="saving || !typeId">
                        {{ saving ? 'Сохранение…' : 'Назначить' }}
                    </button>
                </footer>
            </form>
        </div>
    </Teleport>
</template>

<script setup>
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';

import axios from 'axios';
import { documentCategories } from '../documentCategories';

const props = defineProps({
    applicationId: { type: [Number, String], required: true },
    document: { type: Object, required: true },
    types: { type: Array, required: true },
});

const emit = defineEmits(['close', 'saved']);

const dialog = ref(null);
const category = ref('');
const typeId = ref('');
const saving = ref(false);
const error = ref('');

let alive = true;
let previousFocus = null;

const availableTypes = computed(() => {
    return props.types.filter(type => !type.is_photo && !type.is_archive);
});

const availableCategories = computed(() => {
    return documentCategories.filter(item => {
        return availableTypes.value.some(type => {
            return type.category === item.code;
        });
    });
});

const filteredTypes = computed(() => {
    return availableTypes.value.filter(type => {
        return type.category === category.value;
    });
});

watch(category, () => {
    typeId.value = '';
});

function close() {
    if (!saving.value) emit('close');
}

async function save() {
    if (saving.value || !typeId.value) return;

    saving.value = true;
    error.value = '';

    try {
        await axios.patch(
            `/api/applications/${props.applicationId}/documents/${props.document.id}/type`,
            { document_type_id: Number(typeId.value) }
        );

        if (alive) emit('saved');
    } catch (err) {
        if (!alive) return;

        const validation = err.response?.data?.errors;

        error.value = validation
            ? Object.values(validation).flat()[0]
            : err.response?.data?.message
            || 'Не удалось назначить тип.';
    } finally {
        if (alive) saving.value = false;
    }
}

function trapFocus(event) {
    const elements = Array.from(
        dialog.value?.querySelectorAll(
            'select:not(:disabled), button:not(:disabled)'
        ) || []
    );

    const first = elements[0];
    const last = elements[elements.length - 1];

    if (!first) {
        event.preventDefault();
        return;
    }

    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
}

onMounted(async () => {
    previousFocus = window.document.activeElement;

    await nextTick();

    if (alive) dialog.value?.querySelector('select')?.focus();
});

onBeforeUnmount(() => {
    alive = false;

    if (previousFocus?.isConnected) previousFocus.focus();
});
</script>

<style scoped>
.document-modal-overlay {
    position: fixed;
    inset: 0;
    z-index: 12000;
    display: grid;
    place-items: center;
    padding: 16px;
    background: rgb(15 23 42 / 45%);
}

.document-modal {
    width: 420px;
    max-width: 100%;
    max-height: 90dvh;
    overflow-y: auto;
    padding: 18px;
    border-radius: 10px;
    background: #fff;
    box-shadow: 0 20px 60px rgb(15 23 42 / 20%);
}

h6 {
    margin: 0 0 8px;
    font-weight: 700;
}

.file-title {
    color: #64748b;
    font-size: 12px;
    overflow-wrap: anywhere;
}

label {
    display: grid;
    gap: 5px;
    margin: 14px 0;
    color: #475569;
    font-size: 12px;
}

select {
    width: 100%;
    padding: 9px;
    border: 1px solid #cbd5e1;
    border-radius: 5px;
    background: white;
}

footer {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 20px;
}

button {
    padding: 8px 12px;
    border: 1px solid #cbd5e1;
    border-radius: 5px;
    background: white;
}

.primary {
    color: white;
    background: #0284c7;
    border-color: #0284c7;
}

button:disabled {
    opacity: .6;
}

.modal-error {
    color: #b91c1c;
    font-size: 12px;
}
</style>
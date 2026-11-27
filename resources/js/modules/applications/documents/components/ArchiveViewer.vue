<template>
    <section class="archive-viewer">
        <header class="archive-toolbar">
            <div class="archive-heading">
                <i class="bi bi-file-earmark-zip" aria-hidden="true"></i>

                <div>
                    <strong>{{ archive?.name || file.name || 'Архив' }}</strong>

                    <small>
                        {{ statusLabel }}
                        <template v-if="archive?.index_status === 'ready'">
                            · элементов: {{ meta.total }}
                        </template>
                    </small>
                </div>
            </div>

            <button type="button" :disabled="loading" @click="load(meta.current_page)">
                <i class="bi bi-arrow-clockwise" aria-hidden="true"></i>
                Обновить
            </button>
        </header>

        <div v-if="error" class="archive-error" role="alert">
            {{ error }}
        </div>

        <div v-if="loading && !archive" class="archive-message" role="status">
            Загрузка каталога…
        </div>

        <div v-else-if="archive?.index_status === 'failed'" class="archive-message" role="alert">
            <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>

            <p>
                {{
                    archive.index_error
                    || 'Не удалось прочитать содержимое архива.'
                }}
            </p>
        </div>

        <div v-else-if="archive?.index_status !== 'ready'" class="archive-message" role="status">
            <i class="bi bi-hourglass-split" aria-hidden="true"></i>

            <p>{{ statusLabel }}</p>

            <small>
                Каталог обновится автоматически, пока просмотр открыт.
            </small>
        </div>

        <template v-else>
            <div class="archive-table-scroll" :aria-busy="loading">
                <table class="archive-table">
                    <thead>
                        <tr>
                            <th>Файл или папка</th>
                            <th class="size-column">Размер</th>
                            <th class="status-column">Состояние</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr v-for="entry in entries" :key="entry.id">
                            <td>
                                <div class="entry-name">
                                    <i :class="entryIcon(entry)" aria-hidden="true"></i>

                                    <div>
                                        <strong>{{ entry.name }}</strong>

                                        <small>{{ entry.path }}</small>
                                    </div>
                                </div>
                            </td>

                            <td class="size-column">
                                {{
                                    entry.type === 'directory'
                                        ? '—'
                                        : bytes(entry.size)
                                }}
                            </td>

                            <td class="status-column">
                                <span v-if="!entry.is_safe" class="entry-warning" :title="entry.error || ''">
                                    Извлечение запрещено
                                </span>

                                <span v-else-if="entry.type === 'directory'">
                                    Папка
                                </span>

                                <span v-else>
                                    Файл
                                </span>
                            </td>
                        </tr>

                        <tr v-if="!entries.length">
                            <td colspan="3" class="empty-row">
                                Архив не содержит доступных элементов.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <footer class="archive-footer">
                <span>
                    Страница {{ meta.current_page }} из {{ meta.last_page }}
                </span>

                <div>
                    <button type="button" :disabled="loading || meta.current_page <= 1"
                        @click="load(meta.current_page - 1)">
                        ←
                    </button>

                    <button type="button" :disabled="loading
                        || meta.current_page >= meta.last_page
                        " @click="load(meta.current_page + 1)">
                        →
                    </button>
                </div>
            </footer>
        </template>
    </section>
</template>

<script setup>
import {
    computed,
    onBeforeUnmount,
    ref,
    watch,
} from 'vue';

import axios from 'axios';

const props = defineProps({
    applicationId: {
        type: [Number, String],
        required: true,
    },
    documentId: {
        type: [Number, String],
        required: true,
    },
    file: {
        type: Object,
        required: true,
    },
    active: {
        type: Boolean,
        default: true,
    },
});

const archive = ref(null);
const entries = ref([]);
const loading = ref(false);
const error = ref('');

const meta = ref({
    current_page: 1,
    last_page: 1,
    total: 0,
});

let controller = null;
let timer = null;
let sequence = 0;
let disposed = false;
let pollingAttempts = 0;

const statusLabel = computed(() => {
    return {
        pending: 'Ожидает индексации',
        queued: 'Ожидает индексации',
        processing: 'Подготовка каталога',
        ready: 'Каталог готов',
        failed: 'Ошибка индексации',
    }[archive.value?.index_status]
        || 'Архив ещё не проиндексирован';
});

function clearTimer() {
    window.clearTimeout(timer);
    timer = null;
}

function scheduleRefresh() {
    clearTimer();

    if (
        disposed
        || !props.active
        || pollingAttempts >= 60
        || !['pending', 'queued', 'processing'].includes(
            archive.value?.index_status
        )
    ) {
        return;
    }

    timer = window.setTimeout(() => {
        pollingAttempts += 1;
        load(meta.value.current_page);
    }, 3000);
}

async function load(page = 1) {
    clearTimer();
    controller?.abort();

    const currentSequence = ++sequence;
    const requestController = new AbortController();

    controller = requestController;
    loading.value = true;
    error.value = '';

    try {
        const response = await axios.get(
            `/api/applications/${props.applicationId}`
            + `/documents/${props.documentId}`
            + `/archives/${props.file.id}/entries`,
            {
                params: {
                    page,
                    per_page: 100,
                },
                signal: requestController.signal,
                headers: {
                    Accept: 'application/json',
                },
            }
        );

        if (disposed || currentSequence !== sequence) return;

        const payload = response.data;

        if (
            !Array.isArray(payload?.data)
            || !payload.archive
            || !payload.meta
        ) {
            throw new Error('Некорректный ответ каталога архива.');
        }

        archive.value = payload.archive;
        entries.value = payload.data;
        meta.value = payload.meta;

        scheduleRefresh();
    } catch (requestError) {
        if (
            disposed
            || currentSequence !== sequence
            || requestController.signal.aborted
        ) {
            return;
        }

        error.value = requestError.response?.data?.message
            || requestError.message
            || 'Не удалось загрузить каталог архива.';

        if ([401, 403, 404].includes(requestError.response?.status)) {
            entries.value = [];
            archive.value = null;
        }
    } finally {
        if (!disposed && currentSequence === sequence) {
            loading.value = false;
            controller = null;
        }
    }
}

function entryIcon(entry) {
    if (!entry.is_safe) return 'bi bi-shield-exclamation';
    if (entry.type === 'directory') return 'bi bi-folder-fill';

    return {
        pdf: 'bi bi-file-earmark-pdf',
        jpg: 'bi bi-file-earmark-image',
        jpeg: 'bi bi-file-earmark-image',
        png: 'bi bi-file-earmark-image',
        webp: 'bi bi-file-earmark-image',
        gif: 'bi bi-file-earmark-image',
        txt: 'bi bi-file-earmark-text',
        docx: 'bi bi-file-earmark-word',
        xlsx: 'bi bi-file-earmark-excel',
        zip: 'bi bi-file-earmark-zip',
        rar: 'bi bi-file-earmark-zip',
        '7z': 'bi bi-file-earmark-zip',
        tar: 'bi bi-file-earmark-zip',
    }[entry.extension] || 'bi bi-file-earmark';
}

function bytes(value) {
    const size = Number(value);

    if (!Number.isFinite(size) || size < 0) return '—';
    if (size < 1024) return `${size} Б`;

    const units = ['КиБ', 'МиБ', 'ГиБ', 'ТиБ'];
    const index = Math.min(
        Math.floor(Math.log(size) / Math.log(1024)) - 1,
        units.length - 1
    );

    return (
        (size / 1024 ** (index + 1)).toLocaleString('ru-RU', {
            maximumFractionDigits: 1,
        })
        + ' '
        + units[index]
    );
}

watch(
    () => [
        props.applicationId,
        props.documentId,
        props.file.id,
    ],
    () => {
        archive.value = null;
        entries.value = [];
        pollingAttempts = 0;
        meta.value = {
            current_page: 1,
            last_page: 1,
            total: 0,
        };

        if (props.active) load();
    },
    { immediate: true }
);

watch(
    () => props.active,
    active => {
        if (active) {
            pollingAttempts = 0;
            load(meta.value.current_page);
        } else {
            clearTimer();
            sequence += 1;
            controller?.abort();
            controller = null;
            loading.value = false;
        }
    }
);

onBeforeUnmount(() => {
    disposed = true;
    sequence += 1;
    clearTimer();
    controller?.abort();
});
</script>

<style scoped>
.archive-viewer {
    display: flex;
    flex-direction: column;
    height: 100%;
    min-height: 0;
    background: #ffffff;
    color: #334155;
}

.archive-toolbar,
.archive-footer {
    display: flex;
    flex-shrink: 0;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 10px 13px;
    background: #f8fafc;
}

.archive-toolbar {
    border-bottom: 1px solid #e2e8f0;
}

.archive-heading,
.entry-name {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
}

.archive-heading>i {
    color: #d97706;
    font-size: 25px;
}

.archive-heading div,
.entry-name div {
    min-width: 0;
}

.archive-heading strong {
    display: block;
    overflow-wrap: anywhere;
    font-size: 13px;
}

.archive-heading small,
.entry-name small {
    display: block;
    margin-top: 3px;
    color: #64748b;
    font-size: 10px;
    overflow-wrap: anywhere;
}

.archive-viewer button {
    min-width: 34px;
    min-height: 34px;
    padding: 5px 9px;
    border: 1px solid #cbd5e1;
    border-radius: 5px;
    background: #ffffff;
    color: #475569;
    font-size: 11px;
    cursor: pointer;
}

.archive-viewer button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.archive-viewer button:focus-visible {
    outline: 2px solid #0284c7;
    outline-offset: 2px;
}

.archive-table-scroll {
    flex: 1;
    min-height: 0;
    overflow: auto;
}

.archive-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
}

.archive-table th {
    position: sticky;
    top: 0;
    z-index: 1;
    background: #eff6ff;
    color: #475569;
    font-size: 11px;
    text-align: left;
}

.archive-table td,
.archive-table th {
    padding: 9px 12px;
    border-bottom: 1px solid #e2e8f0;
    vertical-align: middle;
}

.entry-name>i {
    flex-shrink: 0;
    color: #0284c7;
    font-size: 22px;
}

.entry-name strong {
    font-weight: 600;
    overflow-wrap: anywhere;
}

.size-column {
    width: 105px;
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
}

.status-column {
    width: 155px;
    font-size: 10px;
}

.entry-warning {
    color: #b45309;
}

.archive-message {
    display: flex;
    flex: 1;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    gap: 12px;
    padding: 24px;
    text-align: center;
    font-size: 13px;
}

.archive-message>i {
    color: #d97706;
    font-size: 30px;
}

.archive-message p {
    margin: 0;
}

.archive-message small {
    color: #64748b;
}

.archive-error {
    padding: 9px 13px;
    background: #fef2f2;
    color: #b91c1c;
    font-size: 12px;
}

.empty-row {
    padding: 30px !important;
    color: #64748b;
    text-align: center;
}

.archive-footer {
    border-top: 1px solid #e2e8f0;
    font-size: 11px;
}

.archive-footer>div {
    display: flex;
    gap: 6px;
}

@media (max-width: 600px) {
    .status-column {
        width: 100px;
    }

    .archive-table td,
    .archive-table th {
        padding: 8px;
    }
}
</style>
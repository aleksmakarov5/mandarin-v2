<template>
    <Teleport to="body">
        <Transition name="document-window">

            <div v-show="!minimized" class="preview-overlay" @click.self="emit('minimize')"
                @keydown.esc.stop.prevent="emit('close')">
                <section ref="dialog" class="preview-modal" role="dialog" aria-modal="true"
                    aria-label="Просмотр документа" tabindex="-1" @keydown.tab="trapFocus">
                    <header class="preview-header">
                        <div class="preview-heading">
                            <strong>
                                {{ document.title || 'Просмотр документа' }}
                            </strong>

                            <small v-if="currentFile">
                                {{ currentFile.name }}
                            </small>
                        </div>

                        <div class="preview-actions">
                            <button type="button" v-if="!isPhotoGallery"
                                :disabled="loading || downloading || !currentFile" aria-label="Скачать файл"
                                title="Скачать" @click="download">
                                <i class="bi bi-download" aria-hidden="true"></i>
                            </button>
                            <button type="button" title="Свернуть просмотр" aria-label="Свернуть просмотр документа"
                                @click="emit('minimize')">
                                <i class="bi bi-dash-lg" aria-hidden="true"></i>
                            </button>
                            <button type="button" class="close-button" aria-label="Закрыть" @click="emit('close')">
                                ×
                            </button>
                        </div>
                    </header>

                    <!-- Для нескольких файлов — переключатель, не список плиток -->
                    <nav v-if="!isPhotoGallery && meta.total > 1" class="preview-navigation"
                        aria-label="Переключение файлов документа">
                        <button type="button" :disabled="loading || position <= 1" @click="move(-1)">
                            ←
                        </button>

                        <span>Файл {{ position }} из {{ meta.total }}</span>

                        <button type="button" :disabled="loading || position >= meta.total" @click="move(1)">
                            →
                        </button>
                    </nav>

                    <div v-if="downloadError" class="download-error" role="alert">
                        {{ downloadError }}
                    </div>

                    <main class="preview-body" :aria-busy="loading">

                        <PhotoGallery v-if="isPhotoGallery" :application-id="applicationId" :document="document"
                            :active="!minimized" />

                        <div v-else-if="loading" class="preview-message" role="status">
                            Подготовка документа…
                        </div>

                        <div v-else-if="error" class="preview-message preview-error" role="alert">
                            <p>{{ error }}</p>

                            <button type="button" @click="loadPage(meta.current_page)">
                                Повторить
                            </button>
                        </div>

                        <ArchiveViewer v-else-if="preview?.kind === 'archive' && currentFile"
                            :key="`${applicationId}-${document.id}-${currentFile.id}`" :application-id="applicationId"
                            :document-id="document.id" :file="currentFile" :active="!minimized" />
                        <iframe v-else-if="preview?.kind === 'pdf'" :key="preview.url" class="pdf-viewer"
                            :src="preview.url" :title="currentFile?.name || 'PDF-документ'"
                            referrerpolicy="no-referrer"></iframe>

                        <div v-else-if="preview?.kind === 'image'" class="image-viewer">
                            <img :key="preview.url" :src="preview.url"
                                :alt="currentFile?.name || 'Изображение документа'" referrerpolicy="no-referrer"
                                @error="imageError" />
                        </div>
                        <div v-else-if="preview?.kind === 'text'" class="text-viewer">
                            <pre>{{ preview.text }}</pre>
                        </div>
                        <div v-else class="preview-message">
                            Доступных файлов пока нет.
                        </div>
                    </main>
                </section>
            </div>
        </Transition>
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
import PhotoGallery from '../gallery/PhotoGallery.vue';
import ArchiveViewer from './ArchiveViewer.vue';

const props = defineProps({
    applicationId: {
        type: [Number, String],
        required: true,
    },
    document: {
        type: Object,
        required: true,
    },
    minimized: {
        type: Boolean,
        default: false,
    },
});

function isArchiveFile(file) {
    const extension = String(
        file.extension
        || String(file.name || file.original_name || '')
            .split('.')
            .pop()
        || ''
    ).toLowerCase();

    return [
        'zip',
        'rar',
        '7z',
        'tar',
        'gz',
        'tgz',
        'bz2',
        'xz',
    ].includes(extension);
}
const isPhotoGallery = computed(() => {
    return props.document.is_photo === true
        || props.document.type?.is_photo === true
        || props.document.document_type?.is_photo === true;
});

const emit = defineEmits([
    'close',
    'minimize',
]);

const dialog = ref(null);
const files = ref([]);
const selectedIndex = ref(0);

const preview = ref(null);
const loading = ref(false);
const error = ref('');

const downloading = ref(false);
const downloadError = ref('');

const meta = ref({
    current_page: 1,
    last_page: 1,
    total: 0,
});

const currentFile = computed(() => {
    return files.value[selectedIndex.value] || null;
});

const position = computed(() => {
    return (meta.value.current_page - 1) * 50
        + selectedIndex.value
        + 1;
});

let controller = null;
let downloadController = null;
let sequence = 0;
let previousFocus = null;
let disposed = false;

function safeUrl(value) {
    if (typeof value !== 'string' || !value) {
        throw new Error('Сервер не вернул адрес файла.');
    }

    const url = new URL(value, window.location.origin);

    if (url.origin !== window.location.origin) {
        throw new Error('Недопустимый адрес файла.');
    }

    return url.href;
}

async function requestPreview(file, signal) {
    if (isArchiveFile(file)) {
        return {
            kind: 'archive',
        };
    }
    for (let attempt = 0; attempt < 120; attempt += 1) {
        const response = await axios.post(
            `/api/applications/${props.applicationId}`
            + `/documents/${props.document.id}`
            + `/files/${file.id}/preview-link`,
            {
                // Только первое обращение разрешает новую попытку.
                retry: attempt === 0,
            },
            {
                signal,
                headers: {
                    Accept: 'application/json',
                },
            }
        );

        const data = response.data?.data;

        if (
            response.status === 202
            && data?.status === 'processing'
        ) {
            await waitForPreview(2000, signal);
            continue;
        }

        if (data?.kind === 'text') {
            return {
                kind: 'text',
                text: String(data.text ?? ''),
            };
        }

        if (!['pdf', 'image'].includes(data?.kind)) {
            throw new Error(
                'Неподдерживаемое представление файла.'
            );
        }

        return {
            kind: data.kind,
            url: safeUrl(data.url),
        };
    }

    throw new Error(
        'Подготовка занимает больше времени. '
        + 'Попробуйте открыть документ позднее.'
    );
}

function waitForPreview(milliseconds, signal) {
    return new Promise((resolve, reject) => {
        if (signal.aborted) {
            reject(new DOMException('Отменено', 'AbortError'));
            return;
        }

        const onAbort = () => {
            window.clearTimeout(timer);
            signal.removeEventListener('abort', onAbort);

            reject(new DOMException('Отменено', 'AbortError'));
        };

        const timer = window.setTimeout(() => {
            signal.removeEventListener('abort', onAbort);
            resolve();
        }, milliseconds);

        signal.addEventListener('abort', onAbort, {
            once: true,
        });
    });
}

async function loadPage(page = 1, index = 0) {
    controller?.abort();

    const requestSequence = ++sequence;
    const requestController = new AbortController();

    controller = requestController;

    loading.value = true;
    preview.value = null;
    error.value = '';
    downloadError.value = '';

    try {
        const response = await axios.get(
            `/api/applications/${props.applicationId}`
            + `/documents/${props.document.id}/files`,
            {
                params: { page },
                signal: requestController.signal,
                headers: {
                    Accept: 'application/json',
                },
            }
        );

        if (disposed || requestSequence !== sequence) return;

        files.value = response.data.data || [];
        meta.value = response.data.meta;

        selectedIndex.value = index === -1
            ? Math.max(0, files.value.length - 1)
            : index;

        if (!currentFile.value) return;

        const result = await requestPreview(
            currentFile.value,
            requestController.signal
        );

        if (disposed || requestSequence !== sequence) return;

        preview.value = result;
    } catch (requestError) {
        if (
            disposed
            || requestSequence !== sequence
            || requestError.code === 'ERR_CANCELED'
            || requestError.name === 'AbortError'
        ) {
            return;
        }

        error.value = requestError.response?.data?.message
            || requestError.message
            || 'Не удалось открыть документ.';
    } finally {
        if (!disposed && requestSequence === sequence) {
            loading.value = false;
            controller = null;
        }
    }
}

async function selectFile(index) {
    controller?.abort();

    const requestSequence = ++sequence;
    const requestController = new AbortController();

    controller = requestController;
    selectedIndex.value = index;

    loading.value = true;
    error.value = '';
    downloadError.value = '';
    preview.value = null;

    try {
        const result = await requestPreview(
            currentFile.value,
            requestController.signal
        );

        if (disposed || requestSequence !== sequence) return;

        preview.value = result;
    } catch (requestError) {
        if (
            disposed
            || requestSequence !== sequence
            || requestError.code === 'ERR_CANCELED'
            || requestError.name === 'AbortError'
        ) {
            return;
        }

        error.value = requestError.response?.data?.message
            || requestError.message
            || 'Не удалось открыть документ.';
    } finally {
        if (!disposed && requestSequence === sequence) {
            loading.value = false;
            controller = null;
        }
    }
}

function move(direction) {
    if (loading.value) return;

    const index = selectedIndex.value + direction;

    if (index < 0 && meta.value.current_page > 1) {
        loadPage(meta.value.current_page - 1, -1);
    } else if (
        index >= files.value.length
        && meta.value.current_page < meta.value.last_page
    ) {
        loadPage(meta.value.current_page + 1, 0);
    } else if (index >= 0 && index < files.value.length) {
        selectFile(index);
    }
}

function imageError() {
    preview.value = null;
    error.value =
        'Не удалось отобразить изображение. '
        + 'Повторите открытие документа.';
}

async function download() {
    const file = currentFile.value;

    if (!file || downloading.value) return;

    downloading.value = true;
    downloadError.value = '';

    const requestController = new AbortController();
    downloadController = requestController;

    try {
        const response = await axios.post(
            file.download_link_url,
            {},
            {
                signal: requestController.signal,
                headers: {
                    Accept: 'application/json',
                },
            }
        );

        if (disposed) return;

        const link = window.document.createElement('a');

        link.href = safeUrl(response.data?.data?.url);
        link.referrerPolicy = 'no-referrer';
        link.hidden = true;

        window.document.body.appendChild(link);
        link.click();
        link.remove();
    } catch (requestError) {
        if (disposed || requestError.code === 'ERR_CANCELED') return;

        downloadError.value = requestError.response?.data?.message
            || 'Не удалось начать скачивание.';
    } finally {
        if (!disposed) {
            downloading.value = false;
            downloadController = null;
        }
    }
}

function trapFocus(event) {
    const elements = Array.from(
        dialog.value?.querySelectorAll(
            'button:not(:disabled), iframe'
        ) || []
    );

    const first = elements[0];
    const last = elements[elements.length - 1];
    const active = window.document.activeElement;

    if (!first) {
        event.preventDefault();
        dialog.value?.focus();
        return;
    }

    if (active === dialog.value) {
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

onMounted(async () => {
    previousFocus = window.document.activeElement;

    await nextTick();

    if (disposed) return;

    if (!props.minimized) {
        dialog.value?.focus({ preventScroll: true });
    }
    if (!isPhotoGallery.value) {
        loadPage();
    }
});

onBeforeUnmount(() => {
    disposed = true;
    sequence++;

    controller?.abort();
    downloadController?.abort();

    if (previousFocus?.isConnected) {
        previousFocus.focus();
    }
});

watch(
    () => props.minimized,
    async minimized => {
        if (minimized) return;

        await nextTick();

        if (!disposed && !props.minimized) {
            dialog.value?.focus({ preventScroll: true });
        }
    }
);
</script>

<style scoped>
.preview-overlay {
    position: fixed;
    inset: 0;
    z-index: 12000;
    display: grid;
    place-items: center;
    padding: 14px;
    background: rgb(15 23 42 / 65%);
}

.preview-modal {
    display: flex;
    flex-direction: column;
    width: min(1200px, 100%);
    height: calc(100dvh - 28px);
    min-height: 0;
    overflow: hidden;
    border-radius: 9px;
    background: #ffffff;
    box-shadow: 0 20px 70px rgb(0 0 0 / 30%);
    outline: none;
}

.preview-header {
    display: flex;
    flex-shrink: 0;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 10px 13px;
    border-bottom: 1px solid #e2e8f0;
}

.preview-heading {
    min-width: 0;
    overflow-wrap: anywhere;
}

.preview-heading strong {
    color: #1e293b;
    font-size: 13px;
}

.preview-heading small {
    display: block;
    margin-top: 3px;
    color: #64748b;
    font-size: 10px;
}

.preview-actions {
    display: flex;
    flex-shrink: 0;
    align-items: center;
    gap: 6px;
}

.preview-actions button,
.preview-navigation button,
.preview-message button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 34px;
    min-height: 34px;
    padding: 5px 9px;
    border: 1px solid #cbd5e1;
    border-radius: 5px;
    background: #ffffff;
    color: #475569;
    cursor: pointer;
}

.preview-actions .close-button {
    font-size: 24px;
    line-height: 1;
}

.preview-navigation {
    display: flex;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    gap: 12px;
    padding: 5px 10px;
    border-bottom: 1px solid #e2e8f0;
    background: #f8fafc;
    color: #475569;
    font-size: 11px;
}

.preview-body {
    flex: 1;
    min-height: 0;
    overflow: hidden;
    background: #e2e8f0;
}

.pdf-viewer {
    display: block;
    width: 100%;
    height: 100%;
    border: 0;
    background: #ffffff;
}

.image-viewer {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    overflow: auto;
    padding: 12px;
    box-sizing: border-box;
    background: #18212f;
}

.image-viewer img {
    display: block;
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}

.preview-message {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 12px;
    height: 100%;
    box-sizing: border-box;
    padding: 25px;
    color: #475569;
    font-size: 13px;
    text-align: center;
}

.preview-error,
.download-error {
    color: #b91c1c;
}

.download-error {
    padding: 7px 12px;
    background: #fef2f2;
    font-size: 12px;
}

button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

button:focus-visible {
    outline: 2px solid #38bdf8;
    outline-offset: 2px;
}

@media (max-width: 650px) {
    .preview-overlay {
        padding: 0;
    }

    .preview-modal {
        width: 100%;
        height: 100dvh;
        border-radius: 0;
    }

    .preview-actions button {
        min-width: 40px;
        min-height: 40px;
    }
}

.document-window-enter-active,
.document-window-leave-active {
    transition: opacity 200ms ease;
    pointer-events: none;
}

.document-window-enter-active .preview-modal,
.document-window-leave-active .preview-modal {
    transition:
        transform 200ms ease,
        opacity 200ms ease;
    transform-origin: bottom right;
}

.document-window-enter-from,
.document-window-leave-to {
    opacity: 0;
}

.document-window-enter-from .preview-modal,
.document-window-leave-to .preview-modal {
    opacity: 0;
    transform: translateY(65px) scale(0.94);
}

@media (prefers-reduced-motion: reduce) {

    .document-window-enter-active,
    .document-window-leave-active,
    .document-window-enter-active .preview-modal,
    .document-window-leave-active .preview-modal {
        transition: none;
    }
}

.text-viewer {
    height: 100%;
    overflow: auto;
    padding: 20px;
    box-sizing: border-box;
    background: #ffffff;
}

.text-viewer pre {
    margin: 0;
    color: #1e293b;
    font-family: Consolas, "Courier New", monospace;
    font-size: 13px;
    line-height: 1.6;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
    tab-size: 4;
}
</style>
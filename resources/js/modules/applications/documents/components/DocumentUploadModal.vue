<template>
    <Teleport to="body">
        <div v-if="opened" v-show="!minimized" class="upload-overlay" @click.self="minimize"
            @keydown.esc.stop.prevent="minimize">
            <section ref="dialog" class="upload-modal" role="dialog" aria-modal="true" aria-label="Загрузка файлов"
                tabindex="-1" @keydown.tab="trapFocus">
                <header class="upload-header">
                    <div>
                        <h3>Загрузка файлов</h3>
                        <div class="upload-subtitle">
                            {{ contextLabel }}
                        </div>
                    </div>

                    <div class="upload-window-actions">
                        <button type="button" class="window-action" title="Свернуть и продолжить работу"
                            aria-label="Свернуть окно загрузки" @click="minimize">
                            <i class="bi bi-dash-lg" aria-hidden="true"></i>
                        </button>

                        <button type="button" class="window-action window-action-close" title="Закрыть окно загрузки"
                            aria-label="Закрыть окно загрузки" @click="close">
                            ×
                        </button>
                    </div>
                </header>

                <div class="upload-body">
                    <template v-if="!prepared">
                        <template v-if="context.placement === 'typed'">
                            <label>
                                Тип документа *

                                <select v-model="context.documentTypeId" :disabled="!context.availableTypes.length">
                                    <option value="">
                                        {{
                                            context.availableTypes.length
                                                ? 'Выберите тип документа'
                                                : 'Нет доступных типов'
                                        }}
                                    </option>

                                    <option v-for="type in context.availableTypes" :key="type.id"
                                        :value="String(type.id)">
                                        {{ type.name }}
                                    </option>
                                </select>
                            </label>

                            <div v-if="!context.availableTypes.length" class="upload-error" role="alert">
                                Для этой категории нет типов, разрешённых для загрузки.
                                Проверьте справочник типов документов.
                            </div>
                            <label v-if="!context.documentId">
                                Название документа или комплекта

                                <input v-model="title" type="text" maxlength="255" placeholder="Необязательно" />
                            </label>

                            <label v-if="!context.documentId">
                                Дата документа

                                <input v-model="documentDate" type="date" />
                            </label>
                        </template>

                        <div class="file-selector">
                            <strong>Выберите файлы</strong>

                            <span>
                                Документы, фотографии или архивы.
                                Можно выбрать несколько файлов.
                            </span>

                            <input ref="fileInput" type="file" :multiple="isPhotoUpload" :accept="fileAccept" :disabled="context.placement === 'typed'
                                && !selectedType
                                " aria-label="Выберите файлы для загрузки" @change="selectFiles" />
                        </div>

                        <div v-if="selectedFiles.length" class="selection-info">
                            Выбрано: {{ selectedFiles.length }} ·
                            {{ bytes(selectedBytes) }}
                        </div>

                        <p class="upload-note">
                            {{ placementNote }}
                        </p>
                    </template>

                    <template v-else>
                        <div class="overall-heading">
                            <strong>
                                Исходников принято:
                                {{ acceptedCount }} / {{ rows.length }}
                            </strong>

                            <span>{{ percent }}%</span>
                        </div>

                        <progress :value="sentBytes" :max="totalBytes || 1"
                            aria-label="Прогресс передачи файлов"></progress>

                        <div class="progress-note">
                            {{ bytes(sentBytes) }} / {{ bytes(totalBytes) }}
                            <span v-if="registering">
                                · Регистрация пакета…
                            </span>
                        </div>

                        <div class="file-list">
                            <div v-for="row in rows" :key="row.key" class="file-row">
                                <div class="file-info">
                                    <strong :title="row.name">
                                        {{ row.name }}
                                    </strong>

                                    <small>
                                        {{ bytes(row.size) }} ·
                                        {{ statusLabel(row.status) }}
                                    </small>

                                    <span v-if="row.error" class="file-error">
                                        {{ row.error }}
                                    </span>
                                </div>

                                <span class="file-percent" :class="{
                                    accepted: row.status === 'uploaded',
                                    failed: row.status === 'failed'
                                }">
                                    {{
                                        row.status === 'uploaded'
                                            ? '✓'
                                            : filePercent(row) + '%'
                                    }}
                                </span>
                            </div>
                        </div>

                        <div v-if="allAccepted" class="upload-success" role="status">
                            Все исходники получены и собраны.
                            Обработка содержимого и публикация документов
                            подключаются следующим этапом.
                        </div>

                        <p v-else class="upload-note">
                            100% передачи не означает готовность документа.
                            После передачи сервер собирает исходный файл.
                        </p>
                    </template>

                    <div v-if="localError || error" class="upload-error" role="alert">
                        {{ localError || error }}
                    </div>
                </div>

                <footer class="upload-footer">
                    <button v-if="prepared && !running" type="button" class="secondary-button" @click="changeFiles">
                        {{ allAccepted ? 'Новая загрузка' : 'Изменить файлы' }}
                    </button>
                    <button type="button" class="secondary-button" @click="close">
                        {{ allAccepted ? 'Закрыть' : 'Закрыть окно' }}
                    </button>

                    <button v-if="running" type="button" class="secondary-button" @click="pause">
                        Остановить
                    </button>

                    <button v-else-if="!allAccepted" type="button" class="primary-button" :disabled="!prepared
                        && (
                            !selectedFiles.length
                            || (
                                context.placement === 'typed'
                                && !selectedType
                            )
                        )
                        " @click="startUpload">
                        {{
                            prepared
                                ? (failedCount ? 'Повторить ошибки' : 'Продолжить')
                                : 'Начать загрузку'
                        }}
                    </button>
                </footer>
            </section>
        </div>
        <aside v-if="opened && minimized" class="upload-dock" aria-label="Свёрнутая загрузка файлов">
            <button ref="dockRestoreButton" type="button" class="upload-dock-restore" title="Развернуть загрузчик"
                @click="restore">
                <div class="upload-dock-heading">
                    <span class="upload-dock-title">
                        <i class="bi bi-cloud-upload" aria-hidden="true"></i>

                        {{
                            allAccepted
                                ? 'Исходники приняты'
                                : running
                                    ? 'Загрузка файлов'
                                    : 'Загрузка приостановлена'
                        }}
                    </span>

                    <i class="bi bi-chevron-up" aria-hidden="true"></i>
                </div>

                <div class="upload-dock-context">
                    {{ contextLabel }}
                </div>

                <template v-if="prepared">
                    <progress :value="sentBytes" :max="totalBytes || 1"
                        aria-label="Прогресс передачи файлов"></progress>

                    <div class="upload-dock-meta">
                        <span>
                            Принято: {{ acceptedCount }} / {{ rows.length }}
                        </span>

                        <span>{{ percent }}%</span>
                    </div>
                </template>

                <div v-if="localError || error || failedCount" class="upload-dock-error">
                    Есть ошибки — откройте окно загрузки
                </div>
            </button>

            <button type="button" class="upload-dock-close" title="Закрыть окно загрузки"
                aria-label="Закрыть окно загрузки" @click="close">
                ×
            </button>
        </aside>
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

import { useDocumentUpload } from '../composables/useDocumentUpload';

const emit = defineEmits(['sources-uploaded']);

const opened = ref(false);
const minimized = ref(false);
const dockRestoreButton = ref(null);
const dialog = ref(null);

const selectedFiles = ref([]);
const title = ref('');
const documentDate = ref('');
const localError = ref('');

const context = reactive({
    applicationId: null,
    placement: 'unassigned',

    documentTypeId: '',
    documentId: null,

    categoryLabel: '',
    typeLabel: '',
    isPhoto: false,

    availableTypes: [],
});

const selectedType = computed(() => {
    return context.availableTypes.find(type => {
        return String(type.id)
            === String(context.documentTypeId);
    }) || null;
});

const isPhotoUpload = computed(() => {
    if (selectedType.value) {
        return [true, 1, '1'].includes(
            selectedType.value.is_photo
        );
    }

    return context.isPhoto === true;
});

let previousFocus = null;
let notifiedBatchId = null;

const {
    rows,
    batch,
    prepared,

    running,
    registering,
    error,

    totalBytes,
    sentBytes,
    percent,
    acceptedCount,
    failedCount,
    allAccepted,

    prepare,
    start,
    pause,
    reset,
} = useDocumentUpload();

const selectedBytes = computed(() => {
    return selectedFiles.value.reduce(
        (sum, file) => sum + file.size,
        0
    );
});

const fileInput = ref(null);

const photoExtensions = [
    'jpg',
    'jpeg',
    'png',
    'webp',
    'heic',
    'heif',
    'avif',
    'tif',
    'tiff',
];

const archiveExtensions = ['zip', 'rar', '7z'];

const fileAccept = computed(() => {
    if (!isPhotoUpload.value) return '';

    return [
        ...photoExtensions,
        ...archiveExtensions,
    ].map(extension => `.${extension}`).join(',');
});

const contextLabel = computed(() => {
    if (context.placement === 'unassigned') {
        return 'Нераспределённые документы';
    }

    return [
        context.categoryLabel,
        selectedType.value?.name || context.typeLabel,
    ].filter(Boolean).join(' · ');
});

const placementNote = computed(() => {
    if (context.placement === 'unassigned') {
        return (
            'Каждый исходный файл предназначен для отдельного '
            + 'нераспределённого документа.'
        );
    }

    if (isPhotoUpload.value) {
        return context.documentId
            ? 'Добавление фотографий в выбранный комплект.'
            : 'Выберите несколько фотографий или один готовый архив.';
    }

    return 'Один файл — один документ. Название можно изменить.';
});
function clearSelection() {
    reset();

    selectedFiles.value = [];
    localError.value = '';
    notifiedBatchId = null;

    if (fileInput.value) {
        fileInput.value.value = '';
    }
}

async function changeFiles() {
    if (running.value) return;

    /*
     * При ошибке регистрации batch может быть ещё null.
     * Если запрос завершился сетевой ошибкой, сервер мог
     * успеть создать пакет — сброс не удаляет его автоматически.
     */
    if (
        prepared.value
        && !allAccepted.value
        && !window.confirm(
            'Сбросить текущий выбор и выбрать другие файлы? '
            + 'Уже принятые сервером части не будут удалены. '
            + 'Для продолжения прежней загрузки нажмите «Отмена».'
        )
    ) {
        return;
    }

    clearSelection();

    await nextTick();

    if (
        context.placement === 'typed'
        && !selectedType.value
    ) {
        dialog.value?.querySelector('select')?.focus();
    } else {
        fileInput.value?.focus();
    }
}

function selectFiles(event) {
    const files = Array.from(event.target.files || []);

    selectedFiles.value = [];
    localError.value = '';

    function reject(message) {
        localError.value = message;
        event.target.value = '';
    }

    if (!isPhotoUpload.value && files.length > 1) {
        reject('Документы загружаются по одному файлу.');
        return;
    }

    if (isPhotoUpload.value) {
        const extensionOf = file => {
            return file.name
                .split('.')
                .pop()
                ?.toLowerCase() || '';
        };

        const allowed = [
            ...photoExtensions,
            ...archiveExtensions,
        ];

        const invalid = files.filter(file => {
            return !allowed.includes(extensionOf(file));
        });

        if (invalid.length) {
            reject(
                'Выберите изображения или один архив. '
                + `Не подходит: ${invalid[0].name}`
            );
            return;
        }

        const hasArchive = files.some(file => {
            return archiveExtensions.includes(extensionOf(file));
        });

        if (hasArchive && files.length !== 1) {
            reject(
                'Архив загружается отдельно. '
                + 'Выберите один архив либо несколько фотографий.'
            );
            return;
        }
    }

    selectedFiles.value = files;
}

async function open(parameters) {
    /*
     * Не переключаем контекст во время активной отправки.
     */
    if (running.value) {
        opened.value = true;
        minimized.value = false;
        await nextTick();
        dialog.value?.focus();

        return;
    }

    if (
        prepared.value
        && !allAccepted.value
        && !window.confirm(
            'Есть незавершённая загрузка. '
            + 'Начать новую вместо неё? '
            + 'Уже принятые части останутся на сервере. '
            + '«Отмена» — вернуться к прежней загрузке.'
        )
    ) {
        opened.value = true;
        minimized.value = false;
        await nextTick();
        dialog.value?.focus();

        return;
    }

    clearSelection();

    title.value = parameters.title || '';
    documentDate.value = parameters.documentDate || todayLocal();

    Object.assign(context, {
        applicationId: parameters.applicationId,
        placement: parameters.placement || 'unassigned',

        documentTypeId: parameters.documentTypeId
            ? String(parameters.documentTypeId)
            : '',

        documentId: parameters.documentId || null,

        categoryLabel: parameters.categoryLabel || '',
        typeLabel: parameters.typeLabel || '',
        isPhoto: parameters.isPhoto === true,

        availableTypes: Array.isArray(parameters.availableTypes)
            ? parameters.availableTypes
            : [],
    });

    previousFocus = document.activeElement;
    opened.value = true;
    minimized.value = false;
    await nextTick();

    if (
        context.placement === 'typed'
        && !title.value.trim()
    ) {
        title.value = suggestedTitle.value;
    }

    if (context.placement === 'typed') {
        dialog.value?.querySelector('select')?.focus();
    } else {
        fileInput.value?.focus();
    }
}

async function startUpload() {
    if (running.value) return;

    localError.value = '';

    try {
        if (!prepared.value) {
            if (
                context.placement === 'typed'
                && !selectedType.value
            ) {
                localError.value = 'Выберите тип документа.';
                return;
            }

            prepare(selectedFiles.value, {
                ...context,

                documentTypeId: selectedType.value?.id
                    ?? context.documentTypeId,

                title: title.value,
                documentDate: documentDate.value,
            });
        }

        await start();

        if (
            allAccepted.value
            && batch.value?.id
            && notifiedBatchId !== batch.value.id
        ) {
            notifiedBatchId = batch.value.id;

            emit('sources-uploaded', {
                batchId: batch.value.id,
                applicationId: batch.value.application_id,
            });
        }
    } catch (uploadError) {
        localError.value = uploadError.message;
    }
}

function close() {
    if (
        running.value
        && !window.confirm(
            'Остановить передачу и закрыть окно? '
            + 'Принятые части останутся на сервере.'
        )
    ) {
        return;
    }

    pause();
    opened.value = false;
    minimized.value = false;
    if (
        previousFocus instanceof HTMLElement
        && previousFocus.isConnected
    ) {
        previousFocus.focus();
    }
}

function bytes(value) {
    const amount = Number(value) || 0;

    if (amount < 1024) return `${amount} Б`;

    const units = ['КиБ', 'МиБ', 'ГиБ', 'ТиБ'];
    const index = Math.min(
        Math.floor(Math.log(amount) / Math.log(1024)) - 1,
        units.length - 1
    );

    return (
        (amount / 1024 ** (index + 1)).toLocaleString('ru-RU', {
            maximumFractionDigits: 1,
        })
        + ' '
        + units[index]
    );
}

function filePercent(row) {
    return row.size > 0
        ? Math.min(
            100,
            Math.floor(row.sentBytes / row.size * 100)
        )
        : 0;
}

function statusLabel(status) {
    return {
        pending: 'В очереди',
        uploading: 'Передача',
        assembling: 'Сборка на сервере',
        uploaded: 'Исходник принят',
        paused: 'Остановлено',
        failed: 'Ошибка',
    }[status] || status;
}

function trapFocus(event) {
    const elements = Array.from(
        dialog.value?.querySelectorAll(
            'button: not(: disabled), input: not(: disabled), select: not(: disabled)'
        ) || []
    );

    if (!elements.length) {
        event.preventDefault();
        dialog.value?.focus();
        return;
    }

    const first = elements[0];
    const last = elements[elements.length - 1];
    const active = document.activeElement;

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

onBeforeUnmount(() => {
    if (
        opened.value
        && previousFocus instanceof HTMLElement
        && previousFocus.isConnected
    ) {
        previousFocus.focus();
    }
});

watch(
    () => context.documentTypeId,
    () => {
        if (prepared.value) return;

        selectedFiles.value = [];
        localError.value = '';

        if (fileInput.value) {
            fileInput.value.value = '';
        }
    }
);
function todayLocal() {
    const date = new Date();

    return [
        date.getFullYear(),
        String(date.getMonth() + 1).padStart(2, '0'),
        String(date.getDate()).padStart(2, '0'),
    ].join('-');
}

const suggestedTitle = computed(() => {
    const name = selectedType.value?.name || context.typeLabel;

    if (!name) return '';

    const date = documentDate.value || todayLocal();
    const match = date.match(/^(\d{4})-(\d{2})-(\d{2})$/);

    return match
        ? `${name} · ${match[3]}.${match[2]}.${match[1]}`
        : name;
});

watch(suggestedTitle, (next, previous) => {
    if (prepared.value) return;

    /*
     * Автоматически обновляем только предложенное название.
     * Введённое пользователем не перезаписываем.
     */
    if (!title.value.trim() || title.value === previous) {
        title.value = next;
    }
});

async function minimize() {
    if (!opened.value) return;

    /*
     * Не вызываем pause() и не отменяем запросы.
     * Только скрываем большое окно.
     */
    minimized.value = true;

    await nextTick();

    dockRestoreButton.value?.focus({
        preventScroll: true,
    });
}

async function restore() {
    if (!opened.value) return;

    minimized.value = false;

    await nextTick();

    dialog.value?.focus({
        preventScroll: true,
    });
}
defineExpose({ open });
</script>

<style scoped>
.upload-overlay {
    position: fixed;
    inset: 0;
    z-index: 12000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 14px;
    background: rgb(15 23 42 / 48%);
}

.upload-modal {
    display: flex;
    flex-direction: column;
    width: 660px;
    max-width: 100%;
    max-height: calc(100dvh - 28px);
    overflow: hidden;
    border-radius: 10px;
    background: #ffffff;
    box-shadow: 0 20px 60px rgb(15 23 42 / 25%);
    outline: none;
}

.upload-header,
.upload-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 12px 16px;
    background: #f8fafc;
}

.upload-header {
    border-bottom: 1px solid #e2e8f0;
}

.upload-header h3 {
    margin: 0;
    color: #0f172a;
    font-size: 15px;
}

.upload-subtitle {
    margin-top: 3px;
    color: #64748b;
    font-size: 12px;
}

.close-button {
    border: 0;
    background: transparent;
    color: #64748b;
    font-size: 25px;
    cursor: pointer;
}

.upload-body {
    padding: 16px;
    overflow-y: auto;
}

.upload-body label {
    display: flex;
    flex-direction: column;
    gap: 5px;
    margin-bottom: 12px;
    color: #475569;
    font-size: 12px;
}

.upload-body input[type="text"],
.upload-body input[type="date"] {
    padding: 8px 10px;
    border: 1px solid #cbd5e1;
    border-radius: 5px;
    font: inherit;
}

.file-selector {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 20px;
    border: 1px dashed #7dd3fc;
    border-radius: 7px;
    background: #f0f9ff;
    font-size: 13px;
}

.file-selector span,
.selection-info,
.progress-note {
    color: #64748b;
    font-size: 12px;
}

.file-selector input {
    max-width: 100%;
}

.selection-info {
    margin-top: 10px;
}

.upload-note {
    margin: 12px 0 0;
    color: #64748b;
    font-size: 12px;
    line-height: 1.5;
}

.overall-heading {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 9px;
    font-size: 13px;
}

progress {
    display: block;
    width: 100%;
    height: 10px;
    accent-color: #0284c7;
}

.progress-note {
    margin-top: 6px;
}

.file-list {
    max-height: 340px;
    margin-top: 14px;
    overflow-y: auto;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
}

.file-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 9px 11px;
    border-bottom: 1px solid #e2e8f0;
}

.file-row:last-child {
    border-bottom: 0;
}

.file-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 0;
}

.file-info strong {
    overflow: hidden;
    color: #334155;
    font-size: 12px;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.file-info small {
    color: #64748b;
    font-size: 11px;
}

.file-percent {
    flex-shrink: 0;
    color: #0284c7;
    font-size: 12px;
    font-variant-numeric: tabular-nums;
}

.accepted {
    color: #15803d;
}

.failed,
.file-error {
    color: #b91c1c;
}

.file-error {
    font-size: 11px;
    overflow-wrap: anywhere;
}

.upload-success,
.upload-error {
    margin-top: 12px;
    padding: 10px 12px;
    border-radius: 6px;
    font-size: 12px;
    line-height: 1.5;
}

.upload-success {
    background: #f0fdf4;
    color: #166534;
}

.upload-error {
    background: #fef2f2;
    color: #b91c1c;
}

.upload-footer {
    justify-content: flex-end;
    flex-wrap: wrap;
    border-top: 1px solid #e2e8f0;
}

.upload-footer button {
    min-height: 36px;
    padding: 7px 12px;
    border-radius: 5px;
    font-size: 12px;
    cursor: pointer;
}

.primary-button {
    border: 1px solid #0284c7;
    background: #0284c7;
    color: #ffffff;
}

.secondary-button {
    border: 1px solid #cbd5e1;
    background: #ffffff;
    color: #475569;
}

button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

button:focus-visible,
input:focus-visible {
    outline: 2px solid #38bdf8;
    outline-offset: 2px;
}

@media (max-width: 575px) {

    .upload-header,
    .upload-body,
    .upload-footer {
        padding: 12px;
    }

    .file-selector {
        padding: 14px;
    }

    .upload-footer button {
        min-height: 42px;
    }
}

.upload-body select {
    width: 100%;
    min-height: 38px;
    padding: 8px 10px;
    border: 1px solid #cbd5e1;
    border-radius: 5px;
    background: #ffffff;
    color: #334155;
    font: inherit;
}

.upload-body select:focus-visible {
    outline: 2px solid #38bdf8;
    outline-offset: 2px;
}

.upload-body select:disabled {
    background: #f8fafc;
    color: #94a3b8;
}

.upload-window-actions {
    display: flex;
    flex-shrink: 0;
    align-items: center;
    gap: 4px;
}

.window-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;

    width: 32px;
    height: 32px;
    padding: 0;

    border: 1px solid #cbd5e1;
    border-radius: 5px;
    background: #ffffff;
    color: #475569;
    font-size: 16px;
    cursor: pointer;
}

.window-action:hover {
    background: #eff6ff;
    color: #0284c7;
}

.window-action-close {
    font-size: 24px;
}

.window-action-close:hover {
    background: #fef2f2;
    color: #dc2626;
}

/*
 * Панель загрузки находится выше панели
 * свёрнутого просмотрщика документов.
 */
.upload-dock {
    position: fixed;
    right: 12px;

    bottom: calc(var(--document-viewer-bottom-offset, 0px) + env(safe-area-inset-bottom, 0px) + 84px);

    z-index: 11910;

    display: flex;
    align-items: stretch;

    width: 340px;
    max-width: calc(100vw - 24px);

    overflow: hidden;
    border: 1px solid #bae6fd;
    border-top: 3px solid #0284c7;
    border-radius: 8px;

    background: #ffffff;
    box-shadow: 0 6px 24px rgb(15 23 42 / 20%);
}

.upload-dock-restore {
    display: block;
    flex: 1;
    min-width: 0;
    padding: 9px 11px;

    border: 0;
    background: transparent;
    color: #334155;
    text-align: left;
    cursor: pointer;
}

.upload-dock-restore:hover {
    background: #f0f9ff;
}

.upload-dock-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    font-size: 12px;
}

.upload-dock-title {
    display: flex;
    align-items: center;
    gap: 7px;
    font-weight: 700;
}

.upload-dock-title>i {
    color: #0284c7;
    font-size: 18px;
}

.upload-dock-context {
    margin-top: 4px;
    overflow: hidden;
    color: #64748b;
    font-size: 10px;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.upload-dock progress {
    width: 100%;
    height: 7px;
    margin-top: 8px;
    accent-color: #0284c7;
}

.upload-dock-meta {
    display: flex;
    justify-content: space-between;
    gap: 8px;
    margin-top: 4px;
    color: #64748b;
    font-size: 10px;
    font-variant-numeric: tabular-nums;
}

.upload-dock-error {
    margin-top: 5px;
    color: #b91c1c;
    font-size: 10px;
}

.upload-dock-close {
    flex: 0 0 36px;
    padding: 0;

    border: 0;
    border-left: 1px solid #e2e8f0;
    background: #ffffff;
    color: #64748b;
    font-size: 24px;
    cursor: pointer;
}

.upload-dock-close:hover {
    background: #fef2f2;
    color: #dc2626;
}

.upload-dock button:focus-visible,
.window-action:focus-visible {
    outline: 2px solid #0284c7;
    outline-offset: -3px;
}

@media (pointer: coarse) {
    .window-action {
        width: 40px;
        height: 40px;
    }

    .upload-dock-close {
        flex-basis: 44px;
    }
}
</style>
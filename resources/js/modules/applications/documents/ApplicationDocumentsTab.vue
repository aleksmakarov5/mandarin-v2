<template>
    <div class="documents-module">
        <header class="documents-header">
            <div>
                <h5 class="documents-title">
                    <i class="bi bi-folder2-open me-2"></i>
                    Документы дела
                </h5>

                <small class="documents-subtitle">
                    Документы, печатные формы и комплекты фотографий
                </small>
            </div>

            <div class="header-actions">
                <button type="button" class="doc-button" :disabled="loading" @click="loadAll">
                    <i class="bi bi-arrow-clockwise me-1"></i>
                    Обновить
                </button>

                <button v-if="permissions.manage" type="button" class="doc-button primary"
                    :disabled="loading || !applicationId" title="Загрузить файлы в нераспределённые документы"
                    @click="openUnassignedUpload">
                    <i class="bi bi-upload me-1"></i>
                    Загрузить
                </button>
            </div>
        </header>

        <div v-if="error" class="documents-error" role="alert">
            {{ error }}
        </div>
        <div v-if="documentDownloadError" class="documents-error" role="alert">
            {{ documentDownloadError }}
        </div>

        <div v-if="loading && !hasLoadedData" class="documents-loading">
            <span class="spinner-border spinner-border-sm me-2"></span>
            Загрузка документов…
        </div>

        <template v-else>
            <div class="documents-grid">
                <section v-for="section in sections" :key="section.code" class="document-section" :class="{
                    'document-section-unassigned':
                        section.code === 'unassigned',
                }">
                    <header class="section-header" :class="`section-${section.color}`">
                        <div class="section-title-wrap">
                            <span class="section-icon">
                                <i :class="`bi ${section.icon}`"></i>
                            </span>

                            <div>
                                <strong>{{ section.title }}</strong>

                                <small>
                                    {{ documentCountLabel(section.code) }}
                                </small>
                            </div>
                        </div>

                        <div class="section-actions">
                            <button v-if="
                                section.code !== 'unassigned'
                                && permissions.manage
                            " type="button" class="doc-button primary" :disabled="loading || !applicationId"
                                title="Выбрать тип документа и загрузить файлы" @click="openSectionUpload(section)">
                                <i class="bi bi-upload me-1"></i>
                                Загрузить
                            </button>

                            <button v-if="
                                section.code !== 'unassigned'
                                && permissions.manage
                            " type="button" class="doc-button" disabled
                                title="Генератор шаблонов подключим следующим этапом">
                                <i class="bi bi-file-earmark-word me-1"></i>
                                Создать
                            </button>
                        </div>
                    </header>

                    <div class="section-body">
                        <div v-if="itemsFor(section.code).length" class="document-tiles-grid">
                            <DocumentTile v-for="item in itemsFor(section.code)" :key="item.id" :item="item"
                                :manage="permissions.manage === true && !documentActionBusy"
                                @assign-type="assigning = $event" @open="openDocumentViewer" @rename="renameDocument"
                                @delete="deleteDocument" :downloading="downloadingIds.has(Number(item.id))"
                                @download="downloadFromTile" />
                        </div>

                        <div v-if="
                            !loading
                            && !itemsFor(section.code).length
                        " class="section-empty">
                            {{ section.code === 'unassigned'
                                ? 'Нераспределённых документов нет.'
                                : 'В этой категории пока нет документов.' }}
                        </div>

                        <footer v-if="
                            metaFor(section.code).last_page > 1
                        " class="pagination-bar">
                            <button type="button" class="doc-button" :disabled="metaFor(section.code).current_page <= 1
                                " @click="
                                    loadSection(
                                        section.code,
                                        metaFor(section.code).current_page - 1
                                    )
                                    ">
                                Назад
                            </button>

                            <span>
                                {{ metaFor(section.code).current_page }}
                                /
                                {{ metaFor(section.code).last_page }}
                            </span>

                            <button type="button" class="doc-button" :disabled="metaFor(section.code).current_page
                                >= metaFor(section.code).last_page
                                " @click="
                                    loadSection(
                                        section.code,
                                        metaFor(section.code).current_page + 1
                                    )
                                    ">
                                Далее
                            </button>
                        </footer>
                    </div>
                </section>
            </div>
        </template>

        <DocumentAssignTypeModal v-if="assigning && applicationId" :key="`${applicationId}-${assigning.id}`"
            :application-id="applicationId" :document="assigning" :types="types" @close="assigning = null"
            @saved="onAssigned" />
        <DocumentFilesModal v-if="openedDocument && applicationId"
            :key="`document-files-${applicationId}-${openedDocument.id}`" :application-id="applicationId"
            :document="openedDocument" @close="openedDocument = null" />
    </div>
</template>

<script setup>
import {
    computed,
    reactive, onBeforeUnmount,
    ref,
    watch,
} from 'vue';
import { downloadDocument }
    from './services/downloadDocument';
import axios from 'axios';

import { documentRequestKey }
    from './services/documentRequestKey';

import { useDocumentViewer }
    from './composables/useDocumentViewer';

import { documentCategories } from './documentCategories';
import { useDocuments } from './composables/useDocuments';
import DocumentTile from './components/DocumentTile.vue';


import DocumentAssignTypeModal
    from './components/DocumentAssignTypeModal.vue';

import DocumentFilesModal
    from './components/DocumentFilesModal.vue';
import { useDocumentUploadWindow }
    from './composables/useDocumentUploadWindow';

const {
    uploadModalRef,
    lastUploadedBatch,
} = useDocumentUploadWindow();
const props = defineProps({
    applicationId: {
        type: [Number, String],
        required: true,
    },
});

const { open: openDocumentViewer, document: viewedDocument,

    close: closeDocumentViewer, } = useDocumentViewer();

const documentActionBusy = ref(false);
const downloadingIds = ref(new Set());
const documentDownloadError = ref('');

const downloadControllers = new Map();

async function downloadFromTile(item) {
    const id = Number(item.id);

    if (downloadControllers.has(id)) return;

    const controller = new AbortController();

    downloadControllers.set(id, controller);

    downloadingIds.value = new Set([
        ...downloadingIds.value,
        id,
    ]);

    documentDownloadError.value = '';

    try {
        await downloadDocument({
            applicationId: applicationId.value,
            documentId: id,
            signal: controller.signal,
        });
    } catch (err) {
        if (
            controller.signal.aborted
            || err.code === 'ERR_CANCELED'
            || err.name === 'AbortError'
        ) {
            return;
        }

        documentDownloadError.value =
            err.response?.data?.message
            || err.message
            || 'Не удалось скачать документ.';
    } finally {
        downloadControllers.delete(id);

        const next = new Set(downloadingIds.value);
        next.delete(id);
        downloadingIds.value = next;
    }
}

function stopDownloadWaiting() {
    for (const controller of downloadControllers.values()) {
        controller.abort();
    }

    downloadControllers.clear();
    downloadingIds.value = new Set();
}

watch(
    () => props.applicationId,
    stopDownloadWaiting
);

onBeforeUnmount(stopDownloadWaiting);
async function renameDocument(item) {
    if (documentActionBusy.value || !permissions.value.manage) return;

    const entered = window.prompt(
        'Название документа:',
        item.title || ''
    );

    if (entered === null) return;

    const title = entered.trim();

    if (!title) {
        window.alert('Название не должно быть пустым.');
        return;
    }

    if (title.length > 255) {
        window.alert('Название должно содержать не более 255 символов.');
        return;
    }

    if (title === item.title) return;

    documentActionBusy.value = true;

    try {
        await axios.patch(
            `/api/applications/${applicationId.value}`
            + `/documents/${item.id}/title`,
            {
                request_key: documentRequestKey(),
                title,
            }
        );

        /*
         * Если документ открыт, обновляем также заголовок
         * постоянного просмотрщика.
         */
        if (Number(viewedDocument.value?.id) === Number(item.id)) {
            openDocumentViewer({
                ...viewedDocument.value,
                title,
            });
        }

        await loadAll();
    } catch (err) {
        window.alert(
            err.response?.data?.message
            || 'Не удалось переименовать документ.'
        );
    } finally {
        documentActionBusy.value = false;
    }
}

async function deleteDocument(item) {
    if (documentActionBusy.value || !permissions.value.manage) return;

    if (!window.confirm(
        `Удалить «${item.title || 'Документ'}» вместе со всеми файлами?`
    )) {
        return;
    }

    documentActionBusy.value = true;

    try {
        await axios.delete(
            `/api/applications/${applicationId.value}`
            + `/documents/${item.id}`,
            {
                data: {
                    request_key: documentRequestKey(),
                },
            }
        );

        if (Number(viewedDocument.value?.id) === Number(item.id)) {
            closeDocumentViewer();
        }

        await loadAll();
    } catch (err) {
        window.alert(
            err.response?.data?.message
            || 'Не удалось удалить документ.'
        );
    } finally {
        documentActionBusy.value = false;
    }
}
const {
    applicationId,
    itemsByCategory,
    metaByCategory,
    types,
    counts,
    permissions,
    loading,
    error,
    loadAll,
    loadSection,
} = useDocuments(() => props.applicationId);

const assigning = ref(null);

const sections = [
    {
        code: 'unassigned',
        title: 'Нераспределённые',
        icon: 'bi-inbox',
        color: 'slate',
    },
    ...documentCategories,
];

const selectedTypeIds = reactive(
    Object.fromEntries(
        documentCategories.map(category => [
            category.code,
            '',
        ])
    )
);

const hasLoadedData = computed(() => {
    return Object.keys(itemsByCategory.value).length > 0;
});

/*
 * Сервер передаёт allows_upload как boolean.
 * Показываем только типы, разрешённые для ручной загрузки.
 */
function uploadTypesFor(categoryCode) {
    return types.value.filter(type => {
        return type.category === categoryCode
            && type.allows_upload === true;
    });
}

function selectedUploadType(categoryCode) {
    const selectedId = selectedTypeIds[categoryCode];

    return uploadTypesFor(categoryCode).find(type => {
        return String(type.id) === String(selectedId);
    }) || null;
}

function openUnassignedUpload() {
    if (
        permissions.value.manage !== true
        || !applicationId.value
        || loading.value
    ) {
        return;
    }

    uploadModalRef.value?.open({
        applicationId: applicationId.value,
        placement: 'unassigned',
    });
}

function openSectionUpload(section) {
    if (
        permissions.value.manage !== true
        || !applicationId.value
        || loading.value
    ) {
        return;
    }

    uploadModalRef.value?.open({
        applicationId: applicationId.value,
        placement: 'typed',

        categoryLabel: section.title,

        availableTypes: types.value.filter(type => {
            const allowsUpload = [
                true,
                1,
                '1',
            ].includes(type.allows_upload);

            return type.category === section.code
                && allowsUpload;
        }),
    });
}

function itemsFor(code) {
    return itemsByCategory.value[code] || [];
}

function metaFor(code) {
    return metaByCategory.value[code] || {
        current_page: 1,
        last_page: 1,
        total: 0,
    };
}

watch(lastUploadedBatch, batch => {
    if (
        !batch
        || Number(batch.applicationId)
        !== Number(applicationId.value)
    ) {
        return;
    }

    loadAll();
});

function documentCountLabel(code) {
    const count = Number(counts.value[code] || 0);

    if (count === 0) {
        return 'Нет документов';
    }

    if (count % 10 === 1 && count % 100 !== 11) {
        return `${count} документ`;
    }

    if (
        count % 10 >= 2
        && count % 10 <= 4
        && ![12, 13, 14].includes(count % 100)
    ) {
        return `${count} документа`;
    }

    return `${count} документов`;
}

function formatDate(value) {
    if (!value) return 'Дата не указана';

    const match = String(value).match(
        /^(\d{4})-(\d{2})-(\d{2})/
    );

    return match
        ? `${match[3]}.${match[2]}.${match[1]}`
        : '—';
}

async function onAssigned() {
    assigning.value = null;
    await loadAll();
}

function resetTypeSelection() {
    for (const code of Object.keys(selectedTypeIds)) {
        selectedTypeIds[code] = '';
    }
}

/*
 * Если список типов обновился, не сохраняем выбор
 * удалённого или больше не разрешённого типа.
 */
watch(types, () => {
    for (const code of Object.keys(selectedTypeIds)) {
        if (
            selectedTypeIds[code]
            && !selectedUploadType(code)
        ) {
            selectedTypeIds[code] = '';
        }
    }
});

watch(
    () => props.applicationId,
    () => {
        assigning.value = null;
        resetTypeSelection();
    }
);

watch(
    () => permissions.value.manage,
    allowed => {
        if (allowed !== true) {
            assigning.value = null;
            resetTypeSelection();
        }
    }
);

defineExpose({
    loadAll,
});
</script>

<style scoped>
.documents-module {
    width: 100%;
    min-width: 0;
    padding: 8px;
}

.documents-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 10px;
    padding: 10px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #ffffff;
    box-shadow: 0 1px 3px rgb(15 23 42 / 5%);
}

.documents-title {
    margin: 0;
    color: #0f172a;
    font-size: 15px;
    font-weight: 750;
}

.documents-title i {
    color: #0284c7;
}

.documents-subtitle {
    display: block;
    margin-top: 3px;
    color: #64748b;
    font-size: 10px;
}

.header-actions,
.section-actions,
.row-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
}

.doc-button {
    padding: 6px 9px;
    border: 1px solid #cbd5e1;
    border-radius: 5px;
    background: #ffffff;
    color: #334155;
    font-size: 10px;
    white-space: nowrap;
}

.doc-button.primary {
    border-color: #0284c7;
    background: #0284c7;
    color: #ffffff;
}

.doc-button:not(:disabled):hover {
    background: #f1f5f9;
    cursor: pointer;
}

.doc-button.primary:not(:disabled):hover {
    background: #0369a1;
}

.doc-button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.documents-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    align-items: start;
    gap: 9px;
}

.document-section {
    min-width: 0;
    overflow: hidden;
    border: 1px solid #dbe4ec;
    border-radius: 8px;
    background: #ffffff;
    box-shadow: 0 1px 3px rgb(15 23 42 / 5%);
}

.document-section-unassigned {
    grid-column: 1 / -1;
    border-color: #cbd5e1;
}

.section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    min-height: 48px;
    padding: 7px 10px;
    border-bottom: 1px solid #dbe4ec;
}

.section-title-wrap {
    display: flex;
    align-items: center;
    min-width: 0;
    gap: 8px;
}

.section-title-wrap strong {
    display: block;
    color: #1e293b;
    font-size: 12px;
    font-weight: 750;
}

.section-title-wrap small {
    display: block;
    margin-top: 2px;
    color: #64748b;
    font-size: 10px;
}

.section-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
    width: 28px;
    height: 28px;
    border-radius: 6px;
    color: #ffffff;
    font-size: 14px;
}

.section-slate {
    background: #f8fafc;
}

.section-slate .section-icon {
    background: #475569;
}

.section-blue {
    background: #eff6ff;
}

.section-blue .section-icon {
    background: #0284c7;
}

.section-indigo {
    background: #eef2ff;
}

.section-indigo .section-icon {
    background: #4f46e5;
}

.section-orange {
    background: #fff7ed;
}

.section-orange .section-icon {
    background: #ea580c;
}

.section-violet {
    background: #f5f3ff;
}

.section-violet .section-icon {
    background: #7c3aed;
}

.section-green {
    background: #ecfdf5;
}

.section-green .section-icon {
    background: #059669;
}

.section-body {
    min-width: 0;
}

.document-row {
    display: flex;
    align-items: center;
    gap: 9px;
    min-height: 55px;
    padding: 8px 10px;
    border-bottom: 1px solid #f1f5f9;
}

.document-symbol {
    display: grid;
    place-items: center;
    flex: 0 0 auto;
    width: 31px;
    height: 31px;
    border-radius: 6px;
    background: #eff6ff;
    color: #0284c7;
}

.document-info {
    flex: 1 1 auto;
    min-width: 0;
}

.document-info>strong {
    display: block;
    overflow-wrap: anywhere;
    color: #1e293b;
    font-size: 11px;
}

.document-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 3px 9px;
    margin-top: 3px;
    color: #64748b;
    font-size: 9px;
}

.processing {
    color: #b45309;
}

.failed {
    color: #b91c1c;
}

.section-empty,
.documents-loading,
.documents-error {
    padding: 18px 10px;
    color: #64748b;
    font-size: 11px;
    text-align: center;
}

.documents-error {
    margin-bottom: 8px;
    border: 1px solid #fecaca;
    border-radius: 6px;
    background: #fef2f2;
    color: #b91c1c;
    text-align: left;
}

.pagination-bar {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 8px;
    font-size: 10px;
}

@media (max-width: 900px) {
    .documents-grid {
        grid-template-columns: 1fr;
    }

    .document-section-unassigned {
        grid-column: auto;
    }
}

@media (max-width: 650px) {

    .documents-header,
    .section-header {
        align-items: flex-start;
        flex-direction: column;
    }

    .header-actions,
    .section-actions {
        width: 100%;
    }

    .header-actions .doc-button,
    .section-actions .doc-button {
        flex: 1 1 auto;
        min-height: 34px;
    }

    .document-row {
        align-items: flex-start;
        flex-wrap: wrap;
    }

    .row-actions {
        width: 100%;
        padding-left: 40px;
    }

    .row-actions .doc-button {
        min-height: 32px;
    }
}

.upload-type-select {
    flex: 1 1 150px;
    width: 180px;
    min-width: 0;
    max-width: 240px;
    min-height: 29px;
    padding: 5px 7px;
    border: 1px solid #cbd5e1;
    border-radius: 5px;
    background: #ffffff;
    color: #334155;
    font-family: inherit;
    font-size: 10px;
}

.upload-type-select:disabled {
    background: #f8fafc;
    color: #94a3b8;
    cursor: not-allowed;
}

.upload-type-select:focus-visible,
.doc-button:focus-visible {
    outline: 2px solid #38bdf8;
    outline-offset: 2px;
}

.section-actions {
    align-items: center;
    justify-content: flex-end;
    min-width: 0;
}

@media (max-width: 650px) {
    .upload-type-select {
        flex: 1 1 100%;
        width: 100%;
        max-width: none;
        min-height: 38px;
        font-size: 12px;
    }
}

/* Компактная сетка внутри категорий */
.document-tiles-grid,
.document-section-unassigned .document-tiles-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill,
            minmax(min(100%, 260px), 1fr));
    align-items: start;
    gap: 5px;
    padding: 6px;
}

@media (max-width: 650px) {

    .document-tiles-grid,
    .document-section-unassigned .document-tiles-grid {
        grid-template-columns: minmax(0, 1fr);
        gap: 5px;
        padding: 5px;
    }
}
</style>
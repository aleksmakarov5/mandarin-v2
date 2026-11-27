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

                <button v-if="permissions.manage" type="button" class="doc-button primary" disabled
                    title="Общая загрузка будет подключена следующим этапом">
                    <i class="bi bi-upload me-1"></i>
                    Загрузить файлы
                </button>
            </div>
        </header>

        <div v-if="error" class="documents-error" role="alert">
            {{ error }}
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
                            " type="button" class="doc-button primary" disabled
                                title="Загрузчик с выбором типа подключим следующим этапом">
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
                        <article v-for="item in itemsFor(section.code)" :key="item.id" class="document-row">
                            <div class="document-symbol">
                                <i :class="item.type?.is_photo
                                    ? 'bi bi-images'
                                    : 'bi bi-file-earmark-text'"></i>
                            </div>

                            <div class="document-info">
                                <strong>{{ item.title }}</strong>

                                <div class="document-meta">
                                    <span>
                                        {{ item.type?.name || 'Без типа' }}
                                    </span>

                                    <span>
                                        {{ formatDate(
                                            item.document_date
                                            || item.created_at
                                        ) }}
                                    </span>

                                    <span v-if="item.type?.is_photo">
                                        {{ item.photos_count }} фото
                                    </span>

                                    <span v-else>
                                        Файлов: {{ item.files_count }}
                                    </span>

                                    <span v-if="item.processing_count" class="processing">
                                        Обрабатывается:
                                        {{ item.processing_count }}
                                    </span>

                                    <span v-if="item.failed_count" class="failed">
                                        С ошибкой:
                                        {{ item.failed_count }}
                                    </span>
                                </div>
                            </div>

                            <div class="row-actions">
                                <button v-if="
                                    !item.type
                                    && permissions.manage
                                " type="button" class="doc-button" @click="assigning = item">
                                    Назначить тип
                                </button>

                                <button type="button" class="doc-button" disabled :title="item.type?.is_photo
                                    ? 'Галерея подключается следующим этапом'
                                    : 'Просмотр файлов подключается следующим этапом'">
                                    {{ item.type?.is_photo
                                        ? 'Галерея'
                                        : 'Открыть' }}
                                </button>
                            </div>
                        </article>

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
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';

import { documentCategories } from './documentCategories';
import { useDocuments } from './composables/useDocuments';

import DocumentAssignTypeModal
    from './components/DocumentAssignTypeModal.vue';

const props = defineProps({
    applicationId: {
        type: [Number, String],
        required: true,
    },
});

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

const hasLoadedData = computed(() => {
    return Object.keys(itemsByCategory.value).length > 0;
});

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

watch(
    () => props.applicationId,
    () => {
        assigning.value = null;
    }
);

watch(
    () => permissions.value.manage,
    value => {
        if (!value) assigning.value = null;
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
</style>
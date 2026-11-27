<template>
    <section class="photo-gallery">
        <header class="gallery-toolbar">
            <div class="gallery-title">
                <strong>Фотографии</strong>
                <span>{{ photos.length }} фото</span>
            </div>

            <div class="toolbar-actions">
                <button type="button" :disabled="loading || orderSaving || photoActionBusy" @click="load">
                    <i class="bi bi-arrow-clockwise"></i>
                    Обновить
                </button>

                <button v-if="canManage" type="button" class="button-primary" @click="uploadMore">
                    <i class="bi bi-plus-lg"></i>
                    Загрузить ещё
                </button>
            </div>
        </header>

        <div v-if="selectedCount" class="selection-bar">
            <strong>Выбрано: {{ selectedCount }}</strong>

            <button type="button" @click="selectAll">
                Выбрать все
            </button>

            <button type="button" @click="clearSelection">
                Снять выделение
            </button>
            <template v-if="canManage">
                <button type="button" :disabled="photoActionBusy || orderSaving" @click="openPhotoTransfer('copy')">
                    <i class="bi bi-copy" aria-hidden="true"></i>
                    Копировать
                </button>

                <button type="button" :disabled="photoActionBusy || orderSaving" @click="openPhotoTransfer('move')">
                    <i class="bi bi-folder-symlink" aria-hidden="true"></i>
                    Переместить
                </button>

                <button type="button" class="photo-delete-button" :disabled="photoActionBusy || orderSaving"
                    @click="deleteSelectedPhotos">
                    <i class="bi bi-trash3" aria-hidden="true"></i>
                    Удалить
                </button>
            </template>
        </div>
        <div v-if="transferOpen" class="photo-transfer-panel">
            <strong>
                {{
                    transferAction === 'copy'
                        ? 'Копирование фотографий'
                        : 'Перемещение фотографий'
                }}
                · {{ actionPhotoIds.length }}
            </strong>

            <span v-if="transferLoading">Загрузка комплектов…</span>

            <template v-else>
                <label>
                    Комплект назначения

                    <select v-model="transferTargetId" :disabled="photoActionBusy">
                        <option value="">Выберите комплект</option>

                        <option v-for="target in transferTargets" :key="target.id" :value="target.id">
                            {{ target.title }} ·
                            {{ target.photos_count }} фото ·
                            №{{ target.id }}
                        </option>
                    </select>
                </label>

                <small v-if="!transferTargets.length">
                    Других фотокомплектов в этом деле нет.
                    Сначала создайте комплект через загрузку фотографий.
                </small>

                <div class="toolbar-actions">
                    <button type="button" :disabled="!transferTargetId
                        || photoActionBusy
                        || Boolean(pendingPhotoRequest)
                        " @click="confirmPhotoTransfer">
                        {{ photoActionBusy ? 'Выполняется…' : 'Подтвердить' }}
                    </button>

                    <button type="button" :disabled="photoActionBusy" @click="transferOpen = false">
                        Отмена
                    </button>
                </div>
            </template>
        </div>

        <div v-if="photoActionError" class="gallery-error" role="alert">
            {{ photoActionError }}

            <button v-if="pendingPhotoRequest" type="button" :disabled="photoActionBusy" @click="retryPhotoAction">
                Повторить тот же запрос
            </button>
        </div>

        <div v-if="error || orderError" class="gallery-error" role="alert">
            {{ error || orderError }}
        </div>

        <div v-if="loading" class="gallery-empty" role="status">
            Загрузка галереи…
        </div>

        <template v-else-if="!error">
            <!-- Одна фотография -->
            <PhotoFullscreenViewer v-if="currentPhoto" :photo="currentPhoto" :src="currentPresentation?.screen || null"
                :error="currentPresentation?.screenError || ''" :index="currentIndex" :total="photos.length"
                :manageable="canManage" :busy="photoActionBusy || orderSaving" :active="active" @previous="move(-1)"
                @next="move(1)" @close="closePhoto" @minimize="minimizeViewer"
                @image-error="markMediaError($event, 'screen')" @delete="deleteCurrentPhoto"
                @move="transferCurrentPhoto('move', $event)" @copy="transferCurrentPhoto('copy', $event)" />

            <!-- Сетка -->
            <div class="grid-scroll">
                <div v-if="!photos.length" class="gallery-empty">
                    В этом комплекте пока нет фотографий.
                </div>

                <div ref="grid" class="photo-grid">
                    <PhotoGalleryTile v-for="photo in photos" :key="photo.id" :photo="photo"
                        :presentation="presentations[photo.id]" :selected="selectedIds.has(Number(photo.id))"
                        :manageable="canManage" :disabled="orderSaving" @open="openPhoto" @select="toggleSelection"
                        @image-error="markMediaError($event, 'thumbnail')" />
                </div>
            </div>
        </template>

        <footer v-if="!loading && !currentPhoto" class="gallery-status">
            <span v-if="orderSaving" role="status">
                Сохраняем порядок…
            </span>

            <span v-else-if="orderError">
                Порядок не сохранён
            </span>

            <span v-else>
                {{
                    canManage
                        ? 'Перетаскивайте фото за ручку справа сверху'
                        : 'Нажмите на фото для просмотра'
                }}
            </span>
        </footer>
    </section>
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
import PhotoFullscreenViewer
    from './components/PhotoFullscreenViewer.vue';
import Sortable from 'sortablejs';

import PhotoGalleryTile from './components/PhotoGalleryTile.vue';
import { usePhotoGallery } from './composables/usePhotoGallery';

import { useDocumentUploadWindow }
    from '../composables/useDocumentUploadWindow';
import { useDocumentViewer }
    from '../composables/useDocumentViewer';

import axios from 'axios';

import { documentRequestKey }
    from '../services/documentRequestKey';

const props = defineProps({
    applicationId: {
        type: [Number, String],
        required: true,
    },
    document: {
        type: Object,
        required: true,
    },
    active: {
        type: Boolean,
        default: true,
    },
});

const {
    photos,
    info,
    presentations,
    loading,
    error,
    orderSaving,
    orderError,
    selectedIds,
    selectedCount,
    canManage,
    load, markMediaError,
    toggleSelection,
    selectAll,
    clearSelection,
    reorder,
} = usePhotoGallery(
    () => props.applicationId,
    () => props.document.id,
    () => props.active
);

const photoActionBusy = ref(false);
const photoActionError = ref('');

const transferAction = ref('');
const transferTargets = ref([]);
const transferTargetId = ref('');

const transferOpen = ref(false);
const transferLoading = ref(false);

/*
 * Снимок выделения и версии на момент открытия действия.
 */
const actionPhotoIds = ref([]);
const actionRevision = ref(0);

/*
 * Храним тело последней операции, чтобы сетевой повтор
 * не создал повторные копии.
 */
const pendingPhotoRequest = ref(null);

function actionMessage(err) {
    const validation = err.response?.data?.errors;

    return validation
        ? Object.values(validation).flat()[0]
        : err.response?.data?.message
        || err.message
        || 'Не удалось выполнить действие.';
}

async function sendPhotoRequest(payload) {
    if (photoActionBusy.value) return;

    photoActionBusy.value = true;
    photoActionError.value = '';

    pendingPhotoRequest.value = payload;

    try {
        await axios.post(
            `/api/applications/${props.applicationId}`
            + `/documents/${props.document.id}/photo-actions`,
            payload
        );

        pendingPhotoRequest.value = null;

        transferOpen.value = false;
        clearSelection();

        await load();
    } catch (err) {
        photoActionError.value = actionMessage(err);

        /*
         * При однозначном отказе повтор прежней операции
         * бессмысленен. При сетевой ошибке сохраняем ключ.
         */
        if (
            err.response
            && err.response.status >= 400
            && err.response.status < 500
        ) {
            pendingPhotoRequest.value = null;
        }
    } finally {
        photoActionBusy.value = false;
    }
}

function retryPhotoAction() {
    if (pendingPhotoRequest.value) {
        sendPhotoRequest(pendingPhotoRequest.value);
    }
}

async function deleteSelectedPhotos() {
    if (
        !canManage.value
        || !selectedCount.value
        || orderSaving.value
        || photoActionBusy.value
    ) {
        return;
    }

    if (!window.confirm(
        `Удалить выбранные фотографии (${selectedCount.value}) `
        + 'из этого комплекта?'
    )) {
        return;
    }

    await sendPhotoRequest({
        request_key: documentRequestKey(),
        action: 'delete',
        revision: info.value.content_revision,
        photo_ids: [...selectedIds.value],
    });
}

async function openPhotoTransfer(action, explicitIds = null) {
    const ids = explicitIds === null
        ? [...selectedIds.value]
        : explicitIds;

    if (
        !['copy', 'move'].includes(action)
        || !canManage.value
        || !ids.length
        || loading.value
        || orderSaving.value
        || photoActionBusy.value
        || pendingPhotoRequest.value
    ) {
        return;
    }

    const existingIds = new Set(
        photos.value.map(photo => Number(photo.id))
    );

    const requestedIds = [...new Set(ids.map(Number))];

    if (requestedIds.some(id => !existingIds.has(id))) {
        photoActionError.value =
            'Фотография больше не доступна. Обновите галерею.';
        return;
    }

    transferAction.value = action;
    transferTargetId.value = '';
    transferTargets.value = [];

    actionPhotoIds.value = requestedIds;
    actionRevision.value = info.value.content_revision;

    photoActionError.value = '';
    transferLoading.value = true;
    transferOpen.value = true;

    try {
        const response = await axios.get(
            `/api/applications/${props.applicationId}`
            + `/documents/${props.document.id}/photo-destinations`
        );

        transferTargets.value = response.data.data || [];
    } catch (err) {
        photoActionError.value = actionMessage(err);
    } finally {
        transferLoading.value = false;
    }
}

async function confirmPhotoTransfer() {
    const target = transferTargets.value.find(item => {
        return Number(item.id) === Number(transferTargetId.value);
    });

    if (!target || photoActionBusy.value) return;

    await sendPhotoRequest({
        request_key: documentRequestKey(),
        action: transferAction.value,
        revision: actionRevision.value,
        photo_ids: actionPhotoIds.value,
        target_document_id: target.id,
        target_revision: target.content_revision,
    });
}

const {
    uploadModalRef,
    lastUploadedBatch,
} = useDocumentUploadWindow();
const { minimize: minimizeViewer } = useDocumentViewer();
const grid = ref(null);
const openedPhotoId = ref(null);


let sortable = null;

const currentIndex = computed(() => {
    return photos.value.findIndex(
        photo => Number(photo.id) === openedPhotoId.value
    );
});

const currentPhoto = computed(() => {
    return currentIndex.value >= 0
        ? photos.value[currentIndex.value]
        : null;
});

const currentPresentation = computed(() => {
    if (!currentPhoto.value) return null;

    return presentations.value[currentPhoto.value.id] ?? null;
});

function openPhoto(id) {
    openedPhotoId.value = Number(id);

}

async function deleteCurrentPhoto(photoId) {
    if (
        !canManage.value
        || !info.value
        || photoActionBusy.value
        || orderSaving.value
        || pendingPhotoRequest.value
    ) {
        return;
    }

    const id = Number(photoId);
    const photo = photos.value.find(item => Number(item.id) === id);

    if (!photo) return;

    if (!window.confirm(
        `Удалить фотографию «${photo.original_name}» из комплекта?`
    )) {
        return;
    }

    /*
     * После успешного удаления открываем соседнюю фотографию.
     * При ошибке остаёмся на текущей.
     */
    const index = photos.value.findIndex(item => Number(item.id) === id);

    const neighbourId = photos.value[index + 1]?.id
        ?? photos.value[index - 1]?.id
        ?? null;

    await sendPhotoRequest({
        request_key: documentRequestKey(),
        action: 'delete',
        revision: info.value.content_revision,
        photo_ids: [id],
    });

    const stillExists = photos.value.some(item => Number(item.id) === id);

    if (!stillExists) {
        const neighbour = photos.value.find(item => {
            return Number(item.id) === Number(neighbourId);
        });

        openedPhotoId.value = neighbour
            ? Number(neighbour.id)
            : null;
    } else if (photoActionError.value) {
        // Ошибка находится в панели галереи — делаем её видимой.
        closePhoto();
    }
}

async function transferCurrentPhoto(action, photoId) {
    /*
     * Открываем существующую панель выбора комплекта.
     * Полноэкранное фото закрываем, но саму галерею не закрываем.
     */
    closePhoto();

    await openPhotoTransfer(action, [Number(photoId)]);
}

function closePhoto() {
    openedPhotoId.value = null;

}

function move(direction) {
    const next = photos.value[currentIndex.value + direction];

    if (next) openPhoto(next.id);
}


async function uploadMore() {
    if (
        !canManage.value
        || !info.value
        || !uploadModalRef.value
    ) {
        return;
    }

    await minimizeViewer();

    await uploadModalRef.value.open({
        applicationId: props.applicationId,
        placement: 'typed',

        documentTypeId: info.value.document_type_id,
        documentId: props.document.id,

        title: props.document.title,
        documentDate: props.document.document_date || '',

        typeLabel: props.document.type?.name
            || 'Фотографии',

        categoryLabel: 'Архивы фото',
        isPhoto: true,
    });
}

/*
 * Сборка исходников и публикация ещё разделены.
 * Событие обновляет список, но недавно принятые фото
 * могут появиться после последующего «Обновить».
 */
watch(lastUploadedBatch, batch => {
    if (
        Number(batch?.applicationId) === Number(props.applicationId)
        && !orderSaving.value
    ) {
        load();
    }
});

watch(
    [
        grid,
        loading,
        canManage,
        orderSaving,
        photoActionBusy,
        transferOpen,
        () => props.active,
    ],
    async () => {
        sortable?.destroy();
        sortable = null;

        await nextTick();

        if (
            !grid.value
            || !canManage.value
            || !props.active
            || loading.value
            || orderSaving.value
            || photoActionBusy.value
            || transferOpen.value
        ) {
            return;
        }

        sortable = Sortable.create(grid.value, {
            animation: 160,
            draggable: '.photo-tile',
            handle: '.drag-handle',
            dataIdAttr: 'data-id',
            delay: 160,
            delayOnTouchOnly: true,
            touchStartThreshold: 5,
            ghostClass: 'photo-drag-ghost',

            onEnd() {
                const ids = sortable.toArray();

                /*
                 * Сначала возвращаем DOM в порядок Vue,
                 * затем меняем реактивный массив.
                 */
                sortable.sort(
                    photos.value.map(photo => String(photo.id)),
                    false
                );

                reorder(ids);
            },
        });
    },
    { flush: 'post' }
);



onMounted(() => {

});

onBeforeUnmount(() => {
    sortable?.destroy();

});
</script>

<style scoped>
.photo-gallery {
    display: flex;
    flex-direction: column;
    height: 100%;
    min-height: 0;
    background: #f8fafc;
}

.gallery-toolbar,
.toolbar-actions,
.selection-bar,
.single-toolbar,
.single-footer,
.zoom-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}

.gallery-toolbar {
    flex-shrink: 0;
    justify-content: space-between;
    flex-wrap: wrap;
    padding: 11px 14px;
    border-bottom: 1px solid #e2e8f0;
    background: #ffffff;
}

.gallery-title {
    display: flex;
    align-items: baseline;
    gap: 9px;
    color: #334155;
    font-size: 13px;
}

.gallery-title span {
    color: #64748b;
    font-size: 11px;
}

.photo-gallery button {
    min-height: 32px;
    padding: 5px 9px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    background: #ffffff;
    color: #475569;
    font-size: 11px;
    cursor: pointer;
}

.photo-gallery button:hover:not(:disabled) {
    background: #eff6ff;
}

.photo-gallery button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.photo-gallery button:focus-visible {
    outline: 2px solid #0284c7;
    outline-offset: 2px;
}

.photo-gallery .button-primary {
    border-color: #ea580c;
    background: #ea580c;
    color: #ffffff;
}

.photo-gallery .button-primary:hover {
    background: #c2410c;
}

.selection-bar {
    flex-shrink: 0;
    flex-wrap: wrap;
    padding: 8px 14px;
    border-bottom: 1px solid #fed7aa;
    background: #fff7ed;
    color: #9a3412;
    font-size: 11px;
}

.gallery-error {
    padding: 9px 14px;
    background: #fef2f2;
    color: #b91c1c;
    font-size: 12px;
}

.grid-scroll {
    flex: 1;
    min-height: 0;
    overflow: auto;
    padding: 14px;
}

.photo-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(175px, 1fr));
    gap: 13px;
}

.photo-grid :deep(.photo-drag-ghost) {
    opacity: 0.35;
}

.gallery-empty {
    display: grid;
    flex: 1;
    place-items: center;
    min-height: 150px;
    padding: 20px;
    color: #64748b;
    font-size: 13px;
}

.gallery-status {
    flex-shrink: 0;
    padding: 7px 14px;
    border-top: 1px solid #e2e8f0;
    background: #ffffff;
    color: #64748b;
    font-size: 10px;
}

.single-view {
    display: flex;
    flex: 1;
    flex-direction: column;
    min-height: 0;
    background: #111827;
}

.single-toolbar,
.single-footer {
    flex-shrink: 0;
    justify-content: space-between;
    padding: 8px 10px;
    background: #1e293b;
    color: #e2e8f0;
    font-size: 11px;
}

.single-stage {
    flex: 1;
    min-height: 0;
    overflow: hidden;
}

.image-scroll {
    width: 100%;
    height: 100%;
    overflow: auto;
}

.image-canvas {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 100%;
    min-height: 100%;
}

.image-canvas img {
    display: block;
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}

.single-message {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    gap: 10px;
    height: 100%;
    padding: 20px;
    color: #cbd5e1;
    text-align: center;
    font-size: 12px;
}

.single-footer span {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

@media (max-width: 600px) {
    .photo-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 9px;
    }

    .grid-scroll {
        padding: 10px;
    }

    .single-toolbar {
        flex-wrap: wrap;
    }

    .single-footer span {
        display: none;
    }

    .photo-gallery button {
        min-height: 38px;
    }
}

.photo-transfer-panel {
    display: flex;
    flex-direction: column;
    flex-shrink: 0;
    gap: 10px;
    padding: 12px 14px;
    border-bottom: 1px solid #cbd5e1;
    background: #eff6ff;
    color: #334155;
    font-size: 12px;
}

.photo-transfer-panel label {
    display: flex;
    flex-direction: column;
    gap: 5px;
    margin: 0;
}

.photo-transfer-panel select {
    width: 100%;
    max-width: 600px;
    min-height: 38px;
    padding: 7px 9px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    background: white;
    color: #334155;
}

.photo-gallery .photo-delete-button {
    border-color: #fecaca;
    color: #b91c1c;
}
</style>
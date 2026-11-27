<template>
    <section class="photo-gallery">
        <header class="gallery-toolbar">
            <div class="gallery-title">
                <strong>Фотографии</strong>
                <span>{{ photos.length }} фото</span>
            </div>

            <div class="toolbar-actions">
                <button type="button" :disabled="loading || orderSaving" @click="load">
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
        </div>

        <div v-if="error || orderError" class="gallery-error" role="alert">
            {{ error || orderError }}
        </div>

        <div v-if="loading" class="gallery-empty" role="status">
            Загрузка галереи…
        </div>

        <template v-else-if="!error">
            <!-- Одна фотография -->
            <section v-if="currentPhoto" class="single-view" aria-label="Просмотр фотографии"
                @touchstart.passive="touchStart" @touchend.passive="touchEnd">
                <div class="single-toolbar">
                    <button type="button" @click="closePhoto">
                        ← В сетку
                    </button>

                    <span>
                        {{ currentIndex + 1 }} / {{ photos.length }}
                    </span>

                    <div class="zoom-actions">
                        <button type="button" aria-label="Уменьшить" :disabled="zoom <= 1"
                            @click="zoom = Math.max(1, zoom - 0.5)">−</button>

                        <button type="button" title="Сбросить масштаб" @click="zoom = 1">
                            {{ Math.round(zoom * 100) }}%
                        </button>

                        <button type="button" aria-label="Увеличить" :disabled="zoom >= 3"
                            @click="zoom = Math.min(3, zoom + 0.5)">+</button>
                    </div>
                </div>

                <div class="single-stage">
                    <div v-if="
                        currentPresentation?.screen
                        && !currentPresentation?.screenError
                    " class="image-scroll">
                        <div class="image-canvas" :style="{
                            width: `${zoom * 100}%`,
                            height: `${zoom * 100}%`,
                        }">
                            <img :src="currentPresentation.screen" :alt="currentPhoto.original_name"
                                referrerpolicy="no-referrer" draggable="false"
                                @error="markMediaError(currentPhoto.id, 'screen')" />
                        </div>
                    </div>

                    <div v-else class="single-message">
                        <p>
                            {{
                                currentPresentation?.screenError
                                || 'Основное изображение недоступно.'
                            }}
                        </p>

                        <button type="button" :disabled="loading || orderSaving" @click="load">
                            Обновить галерею
                        </button>
                    </div>
                </div>

                <footer class="single-footer">
                    <button type="button" :disabled="currentIndex <= 0" @click="move(-1)">
                        ← Предыдущая
                    </button>

                    <span :title="currentPhoto.original_name">
                        {{ currentPhoto.original_name }}
                    </span>

                    <button type="button" :disabled="currentIndex >= photos.length - 1" @click="move(1)">
                        Следующая →
                    </button>
                </footer>
            </section>

            <!-- Сетка -->
            <div v-else class="grid-scroll">
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

import Sortable from 'sortablejs';

import PhotoGalleryTile from './components/PhotoGalleryTile.vue';
import { usePhotoGallery } from './composables/usePhotoGallery';

import { useDocumentUploadWindow }
    from '../composables/useDocumentUploadWindow';
import { useDocumentViewer }
    from '../composables/useDocumentViewer';

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

const {
    uploadModalRef,
    lastUploadedBatch,
} = useDocumentUploadWindow();
const { minimize: minimizeViewer } = useDocumentViewer();
const grid = ref(null);
const openedPhotoId = ref(null);
const zoom = ref(1);

let sortable = null;
let touch = null;

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
    zoom.value = 1;
}

function closePhoto() {
    openedPhotoId.value = null;
    zoom.value = 1;
}

function move(direction) {
    const next = photos.value[currentIndex.value + direction];

    if (next) openPhoto(next.id);
}



function touchStart(event) {
    if (zoom.value !== 1 || event.touches.length !== 1) {
        touch = null;
        return;
    }

    touch = {
        x: event.touches[0].clientX,
        y: event.touches[0].clientY,
    };
}

function touchEnd(event) {
    if (!touch || zoom.value !== 1) return;

    const point = event.changedTouches[0];
    const dx = point.clientX - touch.x;
    const dy = point.clientY - touch.y;

    touch = null;

    if (Math.abs(dx) > 65 && Math.abs(dx) > Math.abs(dy) * 1.5) {
        move(dx < 0 ? 1 : -1);
    }
}

function keyboard(event) {
    if (!props.active || !currentPhoto.value) return;

    if (
        event.target instanceof HTMLElement
        && event.target.closest(
            'input, textarea, select, [contenteditable="true"]'
        )
    ) {
        return;
    }

    if (event.key === 'ArrowLeft') {
        event.preventDefault();
        move(-1);
    }

    if (event.key === 'ArrowRight') {
        event.preventDefault();
        move(1);
    }

    /*
     * Escape оставляем внешнему просмотрщику:
     * не меняем существующее управление окном.
     */
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
    window.addEventListener('keydown', keyboard);
});

onBeforeUnmount(() => {
    sortable?.destroy();
    window.removeEventListener('keydown', keyboard);
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
</style>
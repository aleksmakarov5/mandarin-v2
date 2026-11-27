<template>
    <Teleport to="body">
        <Transition name="photo-window">

            <section v-show="active" ref="viewer" class="photo-fullscreen" role="dialog" aria-modal="true"
                aria-label="Просмотр фотографии" tabindex="-1" @keydown.stop="handleKeyboard"
                @wheel.prevent="handleWheel">
                <!-- Затемнение: нажатие возвращает к сетке -->
                <div class="fullscreen-backdrop" @click="emit('close')"></div>

                <!-- Фотография -->
                <div ref="stage" class="fullscreen-stage" :class="{
                    'is-zoomed': zoom > 1,
                    'is-dragging': dragging
                }" @pointerdown="startPointer" @pointermove="movePointer" @pointerup="endPointer"
                    @pointercancel="cancelPointer" @dblclick.prevent="toggleZoom">
                    <Transition :name="`photo-slide-${slideDirection}`" mode="out-in">
                        <div :key="photo.id" class="photo-slide-frame">
                            <img v-if="src && !error" class="fullscreen-image" :src="src" :alt="photo.original_name"
                                :style="imageStyle" referrerpolicy="no-referrer" draggable="false" @load="imageLoaded"
                                @error="emit('image-error', photo.id)" />

                            <div v-else class="fullscreen-message">
                                {{ error || 'Подготовка фотографии…' }}
                            </div>
                        </div>
                    </Transition>
                </div>

                <!-- Имя и действия с одной фотографией -->
                <header class="fullscreen-info">
                    <div class="photo-name">
                        {{ photo.original_name }}
                    </div>

                    <div class="photo-position">
                        {{ index + 1 }} / {{ total }}
                    </div>

                    <div v-if="manageable" class="photo-actions">
                        <button type="button" class="icon-button danger" title="Удалить эту фотографию"
                            aria-label="Удалить эту фотографию" :disabled="busy" @click="emit('delete', photo.id)">
                            <i class="bi bi-trash3" aria-hidden="true"></i>
                        </button>

                        <button type="button" class="icon-button" title="Переместить в другой комплект"
                            aria-label="Переместить фотографию" :disabled="busy" @click="emit('move', photo.id)">
                            <i class="bi bi-folder-symlink" aria-hidden="true"></i>
                        </button>

                        <button type="button" class="icon-button" title="Скопировать в другой комплект"
                            aria-label="Скопировать фотографию" :disabled="busy" @click="emit('copy', photo.id)">
                            <i class="bi bi-files" aria-hidden="true"></i>
                        </button>
                    </div>
                </header>

                <!-- Управление окном -->
                <div class="fullscreen-window-actions">
                    <button type="button" class="icon-button" title="Свернуть просмотр" aria-label="Свернуть просмотр"
                        @click="emit('minimize')">
                        <i class="bi bi-dash-lg" aria-hidden="true"></i>
                    </button>

                    <button ref="closeButton" type="button" class="icon-button close-button" title="Вернуться к сетке"
                        aria-label="Вернуться к сетке" @click="emit('close')">
                        <i class="bi bi-x-lg" aria-hidden="true"></i>
                    </button>
                </div>

                <!-- Стрелки -->
                <button type="button" class="navigation-arrow previous" title="Предыдущая фотография"
                    aria-label="Предыдущая фотография" :disabled="index <= 0 || busy" @click="emit('previous')">
                    <i class="bi bi-chevron-left" aria-hidden="true"></i>
                </button>

                <button type="button" class="navigation-arrow next" title="Следующая фотография"
                    aria-label="Следующая фотография" :disabled="index >= total - 1 || busy" @click="emit('next')">
                    <i class="bi bi-chevron-right" aria-hidden="true"></i>
                </button>

                <!-- Масштаб -->
                <footer class="fullscreen-zoom">
                    <button type="button" title="Уменьшить" aria-label="Уменьшить" :disabled="zoom <= 1"
                        @click="setZoom(zoom - 0.5)">
                        <i class="bi bi-dash" aria-hidden="true"></i>
                    </button>

                    <button type="button" class="zoom-value" title="Вписать фотографию в экран" @click="setZoom(1)">
                        {{ Math.round(zoom * 100) }}%
                    </button>

                    <button type="button" title="Увеличить" aria-label="Увеличить" :disabled="zoom >= 5"
                        @click="setZoom(zoom + 0.5)">
                        <i class="bi bi-plus" aria-hidden="true"></i>
                    </button>
                </footer>
            </section>
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

const props = defineProps({
    photo: {
        type: Object,
        required: true,
    },
    src: {
        type: String,
        default: null,
    },
    error: {
        type: String,
        default: '',
    },
    index: {
        type: Number,
        required: true,
    },
    total: {
        type: Number,
        required: true,
    },
    manageable: {
        type: Boolean,
        default: false,
    },
    busy: {
        type: Boolean,
        default: false,
    },
    active: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits([
    'close',
    'minimize',
    'previous',
    'next',
    'delete',
    'move',
    'copy',
    'image-error',
]);

const viewer = ref(null);
const stage = ref(null);
const closeButton = ref(null);

const zoom = ref(1);
const offsetX = ref(0);
const offsetY = ref(0);
const dragging = ref(false);
const slideDirection = ref('next');

watch(
    () => props.index,
    (nextIndex, previousIndex) => {
        slideDirection.value = nextIndex >= previousIndex
            ? 'next'
            : 'previous';
    },
    { flush: 'sync' }
);
let gesture = null;
let previousFocus = null;
let observer = null;
let disposed = false;

let imageWidth = 0;
let imageHeight = 0;

const imageStyle = computed(() => ({
    transform:
        `translate(${offsetX.value}px, ${offsetY.value}px) `
        + `scale(${zoom.value})`,
}));

function clampOffsets() {
    if (!stage.value || !imageWidth || !imageHeight) return;

    const rect = stage.value.getBoundingClientRect();

    const fit = Math.min(
        rect.width / imageWidth,
        rect.height / imageHeight,
        1
    );

    const shownWidth = imageWidth * fit * zoom.value;
    const shownHeight = imageHeight * fit * zoom.value;

    const maxX = Math.max(0, (shownWidth - rect.width) / 2);
    const maxY = Math.max(0, (shownHeight - rect.height) / 2);

    offsetX.value = Math.max(-maxX, Math.min(maxX, offsetX.value));
    offsetY.value = Math.max(-maxY, Math.min(maxY, offsetY.value));
}

function imageLoaded(event) {
    imageWidth = event.target.naturalWidth;
    imageHeight = event.target.naturalHeight;

    clampOffsets();
}

function setZoom(value) {
    zoom.value = Math.max(1, Math.min(5, value));

    if (zoom.value === 1) {
        offsetX.value = 0;
        offsetY.value = 0;
    }

    clampOffsets();
}

function toggleZoom() {
    setZoom(zoom.value === 1 ? 2 : 1);
}

function handleWheel(event) {
    if (!event.deltaY) return;

    setZoom(zoom.value + (event.deltaY < 0 ? 0.25 : -0.25));
}

function startPointer(event) {
    if (!event.isPrimary || event.button !== 0) return;

    gesture = {
        id: event.pointerId,
        x: event.clientX,
        y: event.clientY,
        offsetX: offsetX.value,
        offsetY: offsetY.value,
    };

    dragging.value = zoom.value > 1;

    stage.value?.setPointerCapture(event.pointerId);
}

function movePointer(event) {
    if (!gesture || gesture.id !== event.pointerId) return;
    if (zoom.value <= 1) return;

    offsetX.value =
        gesture.offsetX + event.clientX - gesture.x;

    offsetY.value =
        gesture.offsetY + event.clientY - gesture.y;

    clampOffsets();
}

function endPointer(event) {
    if (!gesture || gesture.id !== event.pointerId) return;

    const dx = event.clientX - gesture.x;
    const dy = event.clientY - gesture.y;

    if (
        zoom.value === 1
        && !props.busy
        && Math.abs(dx) > 65
        && Math.abs(dx) > Math.abs(dy) * 1.5
    ) {
        if (dx < 0 && props.index < props.total - 1) {
            emit('next');
        } else if (dx > 0 && props.index > 0) {
            emit('previous');
        }
    }

    cancelPointer();
}

function cancelPointer() {
    if (
        gesture
        && stage.value?.hasPointerCapture(gesture.id)
    ) {
        stage.value.releasePointerCapture(gesture.id);
    }

    gesture = null;
    dragging.value = false;
}

function trapFocus(event) {
    const buttons = Array.from(
        viewer.value?.querySelectorAll('button:not(:disabled)') || []
    ).filter(button => button.getClientRects().length > 0);

    if (!buttons.length) {
        event.preventDefault();
        viewer.value?.focus();
        return;
    }

    const first = buttons[0];
    const last = buttons[buttons.length - 1];
    const activeElement = document.activeElement;

    if (
        activeElement === viewer.value
        || !viewer.value?.contains(activeElement)
    ) {
        event.preventDefault();
        (event.shiftKey ? last : first).focus();
    } else if (event.shiftKey && activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && activeElement === last) {
        event.preventDefault();
        first.focus();
    }
}

function handleKeyboard(event) {
    if (event.key === 'Tab') {
        trapFocus(event);
        return;
    }

    if (event.key === 'Escape') {
        event.preventDefault();
        emit('close');
        return;
    }

    if (event.key === '+' || event.key === '=') {
        event.preventDefault();
        setZoom(zoom.value + 0.5);
        return;
    }

    if (event.key === '-') {
        event.preventDefault();
        setZoom(zoom.value - 0.5);
        return;
    }

    if (event.key === '0') {
        event.preventDefault();
        setZoom(1);
        return;
    }

    if (props.busy) return;

    if (event.key === 'ArrowLeft' && props.index > 0) {
        event.preventDefault();
        emit('previous');
    }

    if (
        event.key === 'ArrowRight'
        && props.index < props.total - 1
    ) {
        event.preventDefault();
        emit('next');
    }
}

watch(
    () => props.photo.id,
    () => {
        cancelPointer();

        zoom.value = 1;
        offsetX.value = 0;
        offsetY.value = 0;

        imageWidth = 0;
        imageHeight = 0;
    },
    { flush: 'sync' }
);

watch(
    () => props.active,
    async active => {
        if (!active) {
            cancelPointer();
            return;
        }

        await nextTick();

        if (!disposed) {
            closeButton.value?.focus({ preventScroll: true });
            clampOffsets();
        }
    }
);

onMounted(async () => {
    previousFocus = document.activeElement;

    observer = new ResizeObserver(clampOffsets);

    if (stage.value) observer.observe(stage.value);

    await nextTick();

    if (!disposed && props.active) {
        closeButton.value?.focus({ preventScroll: true });
    }
});

onBeforeUnmount(() => {
    disposed = true;
    observer?.disconnect();
    cancelPointer();

    if (
        previousFocus?.isConnected
        && previousFocus.getClientRects().length
    ) {
        previousFocus.focus({ preventScroll: true });
    }
});
</script>

<style scoped>
.photo-fullscreen {
    position: fixed;
    inset: 0;
    z-index: 12020;
    overflow: hidden;
    color: #ffffff;
    isolation: isolate;
    outline: none;
}

.fullscreen-backdrop {
    position: absolute;
    inset: 0;
    background: rgb(12 17 25 / 90%);
    backdrop-filter: blur(3px);
}

.fullscreen-stage {
    position: absolute;
    inset: 14px 78px 68px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    touch-action: none;
}

.fullscreen-image {
    display: block;
    flex-shrink: 0;
    width: auto;
    height: auto;
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
    transform-origin: center;
    user-select: none;
    pointer-events: none;
    box-shadow: 0 12px 45px rgb(0 0 0 / 25%);
}

.is-zoomed {
    cursor: grab;
}

.is-dragging {
    cursor: grabbing;
}

.fullscreen-info {
    position: absolute;
    top: max(18px, env(safe-area-inset-top, 0px));
    left: max(18px, env(safe-area-inset-left, 0px));
    z-index: 2;
    max-width: min(420px, calc(100vw - 140px));
    padding: 9px 11px;
    border-radius: 8px;
    background: rgb(15 23 42 / 65%);
}

.photo-name {
    overflow: hidden;
    font-size: 16px;
    font-weight: 750;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.photo-position {
    margin-top: 3px;
    color: #cbd5e1;
    font-size: 11px;
}

.photo-actions {
    display: flex;
    gap: 6px;
    margin-top: 9px;
}

.fullscreen-window-actions {
    position: absolute;
    top: max(18px, env(safe-area-inset-top, 0px));
    right: max(18px, env(safe-area-inset-right, 0px));
    z-index: 3;
    display: flex;
    gap: 7px;
}

.icon-button,
.navigation-arrow,
.fullscreen-zoom button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    border: 1px solid rgb(255 255 255 / 22%);
    border-radius: 7px;
    background: rgb(15 23 42 / 70%);
    color: #ffffff;
    cursor: pointer;
}

.icon-button {
    width: 36px;
    height: 36px;
    font-size: 17px;
}

.icon-button:hover:not(:disabled),
.navigation-arrow:hover:not(:disabled),
.fullscreen-zoom button:hover:not(:disabled) {
    background: #334155;
}

.icon-button.danger:hover:not(:disabled) {
    border-color: #ef4444;
    background: #b91c1c;
}

.navigation-arrow {
    position: absolute;
    top: 50%;
    z-index: 2;
    width: 46px;
    height: 66px;
    transform: translateY(-50%);
    font-size: 30px;
}

.previous {
    left: 14px;
}

.next {
    right: 14px;
}

.fullscreen-zoom {
    position: absolute;
    bottom: calc(14px + env(safe-area-inset-bottom, 0px));
    left: 50%;
    z-index: 2;
    display: flex;
    gap: 5px;
    padding: 5px;
    border: 1px solid rgb(255 255 255 / 15%);
    border-radius: 10px;
    background: rgb(15 23 42 / 75%);
    transform: translateX(-50%);
}

.fullscreen-zoom button {
    width: 38px;
    height: 34px;
    border: 0;
    font-size: 19px;
}

.fullscreen-zoom .zoom-value {
    width: 66px;
    font-size: 12px;
    font-variant-numeric: tabular-nums;
}

.fullscreen-message {
    padding: 20px;
    color: #cbd5e1;
    font-size: 13px;
    text-align: center;
}

button:disabled {
    opacity: 0.3;
    cursor: not-allowed;
}

button:focus-visible {
    outline: 2px solid #fb923c;
    outline-offset: 3px;
}

@media (max-width: 650px) {
    .fullscreen-stage {
        inset: 120px 8px 75px;
    }

    .fullscreen-info {
        top: max(10px, env(safe-area-inset-top, 0px));
        left: 10px;
        max-width: calc(100vw - 115px);
        padding: 7px 9px;
    }

    .photo-name {
        font-size: 12px;
    }

    .fullscreen-window-actions {
        top: max(10px, env(safe-area-inset-top, 0px));
        right: 10px;
        gap: 4px;
    }

    .icon-button {
        width: 39px;
        height: 39px;
    }

    .navigation-arrow {
        width: 34px;
        height: 52px;
        font-size: 23px;
    }

    .previous {
        left: 6px;
    }

    .next {
        right: 6px;
    }
}

/*
 * Анимируем обёртку, а не изображение:
 * transform самой фотографии уже используется для масштаба
 * и перемещения.
 */
.photo-slide-frame {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    min-width: 0;
    min-height: 0;
}

.photo-slide-next-enter-active,
.photo-slide-next-leave-active,
.photo-slide-previous-enter-active,
.photo-slide-previous-leave-active {
    transition:
        opacity 140ms ease,
        transform 140ms ease;
}

/* Следующая: новое фото появляется справа */
.photo-slide-next-enter-from {
    opacity: 0;
    transform: translateX(45px);
}

.photo-slide-next-leave-to {
    opacity: 0;
    transform: translateX(-45px);
}

/* Предыдущая: новое фото появляется слева */
.photo-slide-previous-enter-from {
    opacity: 0;
    transform: translateX(-45px);
}

.photo-slide-previous-leave-to {
    opacity: 0;
    transform: translateX(45px);
}

.photo-fullscreen {
    transform-origin: bottom right;
}

.photo-window-enter-active {
    transition:
        opacity 220ms ease,
        transform 220ms cubic-bezier(0.16, 1, 0.3, 1);
    pointer-events: none;
}

.photo-window-leave-active {
    transition:
        opacity 180ms ease,
        transform 180ms ease-in;
    pointer-events: none;
}

.photo-window-enter-from,
.photo-window-leave-to {
    opacity: 0;
    transform: translateY(70px) scale(0.92);
}

@media (prefers-reduced-motion: reduce) {

    .photo-slide-next-enter-active,
    .photo-slide-next-leave-active,
    .photo-slide-previous-enter-active,
    .photo-slide-previous-leave-active,
    .photo-window-enter-active,
    .photo-window-leave-active {
        transition: none;
    }
}
</style>
<template>
    <article class="photo-tile" :class="{ selected }" :data-id="photo.id">
        <div class="tile-controls">
            <label class="selection-control">
                <input type="checkbox" :checked="selected" :aria-label="`Выбрать фото ${photo.position}`"
                    @change="emit('select', photo.id)" />
            </label>

            <button v-if="manageable" type="button" class="drag-handle" title="Перетащить фотографию"
                aria-label="Перетащить фотографию" :disabled="disabled">
                <i class="bi bi-grip-vertical" aria-hidden="true"></i>
            </button>
        </div>

        <button type="button" class="photo-open" :aria-label="`Открыть фото ${photo.position}`"
            @click="emit('open', photo.id)">
            <img v-if="
                presentation?.thumbnail
                && !presentation?.thumbnailError
            " :src="presentation.thumbnail" :alt="photo.original_name" loading="lazy" decoding="async"
                referrerpolicy="no-referrer" draggable="false" @error="emit('image-error', photo.id)" />

            <span v-else class="photo-placeholder failed">
                <i class="bi bi-exclamation-circle" aria-hidden="true"></i>

                {{
                    presentation?.thumbnailError
                        ? 'Ошибка загрузки'
                        : 'Миниатюра отсутствует'
                }}
            </span>


        </button>

        <footer class="tile-footer">
            <strong>{{ String(photo.position).padStart(3, '0') }}</strong>

            <span :title="photo.original_name">
                {{ photo.original_name }}
            </span>


        </footer>
    </article>
</template>

<script setup>
defineProps({
    photo: {
        type: Object,
        required: true,
    },
    presentation: {
        type: Object,
        default: null,
    },
    selected: {
        type: Boolean,
        default: false,
    },
    manageable: {
        type: Boolean,
        default: false,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits([
    'open',
    'select',
    'image-error',
]);
</script>

<style scoped>
.photo-tile {
    position: relative;
    min-width: 0;
    overflow: hidden;
    border: 1px solid #dbe3ec;
    border-radius: 10px;
    background: #ffffff;
    box-shadow: 0 2px 6px rgb(15 23 42 / 4%);
}

.photo-tile.selected {
    border-color: #f97316;
    box-shadow: 0 0 0 2px #fdba74;
}

.tile-controls {
    position: absolute;
    top: 7px;
    right: 7px;
    left: 7px;
    z-index: 1;
    display: flex;
    align-items: center;
    justify-content: space-between;
    pointer-events: none;
}

.selection-control,
.drag-handle {
    display: grid;
    place-items: center;
    width: 32px;
    height: 32px;
    margin: 0;
    border: 1px solid #e2e8f0;
    border-radius: 7px;
    background: rgb(255 255 255 / 95%);
    pointer-events: auto;
}

.selection-control input {
    width: 17px;
    height: 17px;
    margin: 0;
    accent-color: #ea580c;
}

.drag-handle {
    color: #475569;
    font-size: 19px;
    cursor: grab;
    touch-action: none;
}

.photo-open {
    display: block;
    width: 100%;
    padding: 0;
    aspect-ratio: 4 / 3;
    overflow: hidden;
    border: 0;
    background: #edf2f7;
    cursor: pointer;
}

.photo-open img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.photo-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    gap: 8px;
    height: 100%;
    padding: 8px;
    color: #64748b;
    font-size: 11px;
}

.photo-placeholder i {
    font-size: 27px;
}

.failed {
    color: #b45309;
}

.tile-footer {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px;
    font-size: 10px;
}

.tile-footer strong {
    color: #0369a1;
    font-variant-numeric: tabular-nums;
}

.tile-footer span {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    color: #64748b;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.tile-footer button {
    border: 0;
    background: transparent;
    color: #0284c7;
    font-size: 19px;
}
</style>
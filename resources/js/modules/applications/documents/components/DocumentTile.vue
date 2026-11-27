<template>
    <article class="document-tile" :class="{
        'document-tile-photo': isPhoto,
        'document-tile-error': item.failed_count > 0,
    }">
        <!-- Иконка слева -->
        <div class="tile-file-icon" :class="`file-icon-${fileKind}`" aria-hidden="true">
            <i :class="fileIcon"></i>
            <span class="file-format">{{ formatLabel }}</span>
        </div>

        <!-- Название и сведения справа от иконки -->
        <div class="tile-content">
            <button type="button" class="tile-name tile-open-button" title="Открыть список файлов"
                @click="emit('open', item)">
                {{ item.title || 'Без названия' }}
            </button>

            <div v-if="item.type?.name && item.type.name !== item.title" class="tile-type">
                {{ item.type.name }}
            </div>

            <div class="tile-details">
                <span>
                    {{
                        isPhoto
                            ? `Фото: ${item.photos_count ?? 0}`
                            : `Файлов: ${item.files_count ?? 0}`
                    }}
                </span>

                <span class="detail-separator">·</span>

                <span>{{ formattedDate }}</span>
            </div>

            <div v-if="
                !item.type
                || item.processing_count
                || item.failed_count
            " class="tile-states">
                <span v-if="!item.type" class="tile-state tile-state-unassigned">
                    Без типа
                </span>

                <span v-if="item.processing_count" class="tile-state tile-state-processing" role="status">
                    Обработка: {{ item.processing_count }}
                </span>

                <span v-if="item.failed_count" class="tile-state tile-state-failed">
                    Ошибок: {{ item.failed_count }}
                </span>
            </div>
        </div>

        <!-- Действия справа сверху -->
        <div class="tile-actions">
            <button v-if="manage" type="button" class="tile-action" :disabled="Boolean(item.type)" :title="item.type
                ? 'Редактирование пока не подключено'
                : 'Назначить тип документа'" aria-label="Редактировать тип документа"
                @click="emit('assign-type', item)">
                <i class="bi bi-pencil" aria-hidden="true"></i>
            </button>

            <button v-if="manage" type="button" class="tile-action tile-action-danger" disabled
                title="Удаление пока не подключено" aria-label="Удалить документ">
                <i class="bi bi-trash3" aria-hidden="true"></i>
            </button>

            <button type="button" class="tile-action" :disabled="!Number(item.files_count)"
                title="Выбрать файл для скачивания" aria-label="Открыть файлы для скачивания"
                @click="emit('open', item)">
                <i class="bi bi-download" aria-hidden="true"></i>
            </button>
        </div>
    </article>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    item: {
        type: Object,
        required: true,
    },

    manage: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits([
    'assign-type',
    'open',
]);

const isPhoto = computed(() => {
    return props.item.type?.is_photo === true;
});

/*
 * В текущем API нет расширения физического файла.
 * Определение по названию используется только для оформления,
 * не для проверки содержимого и не для разрешения скачивания.
 */
const extension = computed(() => {
    return String(
        props.item.primary_file?.extension || ''
    ).toLowerCase();
});
const photoExtensionsForIcon = [
    'jpg', 'jpeg', 'png', 'webp',
    'heic', 'heif', 'avif', 'tif', 'tiff',
];
const fileKind = computed(() => {
    if (
        props.item.presentation === 'archive'
        || ['zip', 'rar', '7z'].includes(extension.value)
    ) {
        return 'archive';
    }

    if (
        props.item.presentation === 'gallery'
        || isPhoto.value
    ) {
        return 'photo';
    }

    if (extension.value === 'pdf') return 'pdf';

    if (['doc', 'docx', 'odt', 'rtf'].includes(extension.value)) {
        return 'word';
    }

    if (['xls', 'xlsx', 'csv', 'ods'].includes(extension.value)) {
        return 'excel';
    }

    if (photoExtensionsForIcon.includes(extension.value)) {
        return 'image';
    }

    return 'document';
});

const fileIcon = computed(() => {
    return {
        photo: 'bi bi-images',
        archive: 'bi bi-file-earmark-zip',
        pdf: 'bi bi-file-earmark-pdf',
        word: 'bi bi-file-earmark-word',
        excel: 'bi bi-file-earmark-excel',
        image: 'bi bi-file-earmark-image',
        document: 'bi bi-file-earmark-text',
    }[fileKind.value];
});

const formatLabel = computed(() => {
    if (fileKind.value === 'photo') return 'ФОТО';

    return extension.value
        ? extension.value.toUpperCase()
        : 'ДОКУМЕНТ';
});

const formattedDate = computed(() => {
    const value = props.item.document_date
        || props.item.created_at;

    if (!value) return 'Без даты';

    const match = String(value).match(
        /^(\d{4})-(\d{2})-(\d{2})/
    );

    return match
        ? `${match[3]}.${match[2]}.${match[1]}`
        : 'Без даты';
});
</script>

<style scoped>
.document-tile {
    position: relative;
    display: flex;
    align-items: flex-start;
    gap: 8px;
    min-width: 0;
    box-sizing: border-box;
    padding: 7px;
    border: 1px solid #e2e8f0;
    border-radius: 5px;
    background: #ffffff;
    transition:
        border-color 0.15s ease,
        background-color 0.15s ease;
}

.document-tile:hover {
    border-color: #94cce8;
    background: #f8fcff;
}

.document-tile-photo {
    background: #faf8ff;
    border-color: #e9ddff;
}

.document-tile-error {
    border-color: #f1aeb5;
}

/* Иконка формата слева */
.tile-file-icon {
    display: flex;
    flex: 0 0 32px;
    flex-direction: column;
    align-items: center;
    gap: 3px;
    padding-top: 2px;
    color: #64748b;
}

.tile-file-icon>i {
    font-size: 28px;
    line-height: 1;
}

.file-format {
    max-width: 100%;
    padding: 1px 3px;
    border-radius: 3px;
    background: #f1f5f9;
    font-size: 6px;
    font-weight: 800;
    line-height: 1.2;
    overflow-wrap: anywhere;
    text-align: center;
}

.file-icon-pdf {
    color: #dc3545;
}

.file-icon-pdf .file-format {
    background: #fce8ea;
}

.file-icon-word {
    color: #2563eb;
}

.file-icon-word .file-format {
    background: #e8f0ff;
}

.file-icon-excel {
    color: #198754;
}

.file-icon-excel .file-format {
    background: #e5f5ec;
}

.file-icon-archive {
    color: #d99a08;
}

.file-icon-archive .file-format {
    background: #fff4d6;
}

.file-icon-image {
    color: #0891b2;
}

.file-icon-image .file-format {
    background: #e0f7fa;
}

.file-icon-photo {
    color: #7c3aed;
}

.file-icon-photo .file-format {
    background: #eee5ff;
}

/* Текст справа */
.tile-content {
    flex: 1 1 auto;
    min-width: 0;
}

/* Резервируем место справа под кнопки */
.tile-name {
    min-height: 23px;
    padding-right: 76px;
    color: #1e293b;
    font-size: 10px;
    font-weight: 650;
    line-height: 1.3;
    overflow-wrap: anywhere;
    text-align: left;
}

.tile-type {
    margin-top: 2px;
    color: #64748b;
    font-size: 8px;
    line-height: 1.25;
    overflow-wrap: anywhere;
}

.tile-details {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 2px 5px;
    margin-top: 3px;
    color: #64748b;
    font-size: 8px;
    line-height: 1.25;
    font-variant-numeric: tabular-nums;
}

.detail-separator {
    color: #cbd5e1;
}

/* Действия справа сверху */
.tile-actions {
    position: absolute;
    top: 4px;
    right: 4px;
    display: flex;
    gap: 1px;
}

.tile-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 23px;
    height: 23px;
    padding: 0;
    border: 1px solid transparent;
    border-radius: 4px;
    background: transparent;
    color: #475569;
    font-size: 12px;
    cursor: pointer;
}

.tile-action:hover:not(:disabled) {
    border-color: #bae6fd;
    background: #e0f2fe;
    color: #0284c7;
}

.tile-action-danger:hover:not(:disabled) {
    border-color: #fecaca;
    background: #fef2f2;
    color: #dc2626;
}

.tile-action:disabled {
    opacity: 0.35;
    cursor: not-allowed;
}

.tile-action:focus-visible {
    outline: 2px solid #38bdf8;
    outline-offset: 1px;
}

/* Состояния — небольшие метки, а не отдельные полосы */
.tile-states {
    display: flex;
    flex-wrap: wrap;
    gap: 3px;
    margin-top: 3px;
}

.tile-state {
    padding: 1px 4px;
    border-radius: 3px;
    font-size: 8px;
    line-height: 1.25;
}

.tile-state-unassigned {
    background: #f1f5f9;
    color: #64748b;
}

.tile-state-processing {
    background: #fff7ed;
    color: #b45309;
}

.tile-state-failed {
    background: #fef2f2;
    color: #b91c1c;
}

@media (pointer: coarse) {
    .tile-action {
        width: 32px;
        height: 32px;
        font-size: 14px;
    }

    .tile-name {
        min-height: 32px;
        padding-right: 103px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .document-tile {
        transition: none;
    }
}

/* Действия отдельной узкой колонкой справа */
.tile-actions {
    position: static;
    display: flex;
    flex-direction: column;
    align-items: center;
    flex: 0 0 auto;
    gap: 1px;
    margin-left: auto;
}

/* Больше не резервируем место для горизонтальных кнопок */
.tile-name {
    min-height: 0;
    padding-right: 0;
}

.tile-content {
    flex: 1 1 auto;
    min-width: 0;
}

/* Компактные кнопки на компьютере */
.tile-action {
    width: 23px;
    height: 23px;
    flex-shrink: 0;
}

/* На телефоне сохраняем удобную область нажатия */
@media (pointer: coarse) {
    .tile-action {
        width: 32px;
        height: 32px;
    }

    .tile-name {
        min-height: 0;
        padding-right: 0;
    }
}

/* Компактная горизонтальная карточка */
.document-tile {
    min-height: 0;
    padding: 4px 5px;
    gap: 7px;
    align-items: flex-start;
}

/* Иконка файла слева */
.tile-file-icon {
    flex-basis: 28px;
    gap: 2px;
    padding-top: 2px;
}

.tile-file-icon>i {
    font-size: 26px;
}

/* Название крупнее, без резервных отступов */
.tile-name {
    min-height: 0;
    padding-right: 0;
    font-size: 12px;
    font-weight: 650;
    line-height: 1.2;
}

.tile-type {
    margin-top: 2px;
    font-size: 10px;
    line-height: 1.2;
}

.tile-details {
    margin-top: 3px;
    gap: 2px 5px;
    font-size: 10px;
    line-height: 1.2;
}

.tile-states {
    margin-top: 2px;
}

.tile-state {
    padding: 1px 3px;
    font-size: 9px;
}

/* Узкий столбик действий без промежутков */
.tile-actions {
    position: static;
    flex: 0 0 20px;
    flex-direction: column;
    align-items: center;
    gap: 0;
    margin-left: auto;
}

.tile-action {
    width: 20px;
    height: 18px;
    min-height: 0;
    padding: 0;
    font-size: 11px;
    line-height: 1;
}

/* На сенсорном экране кнопки оставляем удобными для нажатия */
@media (pointer: coarse) {
    .tile-actions {
        flex-basis: 32px;
    }

    .tile-action {
        width: 32px;
        height: 32px;
    }

    .tile-name {
        min-height: 0;
        padding-right: 0;
    }
}

.tile-open-button {
    display: block;
    width: 100%;
    margin: 0;
    padding: 0;
    border: 0;
    background: transparent;
    font-family: inherit;
    text-align: left;
    cursor: pointer;
}

.tile-open-button:hover {
    color: #0284c7;
}

.tile-open-button:focus-visible {
    outline: 2px solid #38bdf8;
    outline-offset: 2px;
    border-radius: 2px;
}
</style>
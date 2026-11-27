<template>
    <div class="document-type-row">
        <div class="document-type-main">
            <div class="document-type-icon">
                <i :class="iconClass"></i>
            </div>

            <div class="document-type-info">
                <div class="document-type-name">
                    {{ type.name }}
                </div>

                <div class="document-type-meta">
                    <span>
                        {{ fileCountLabel }}
                    </span>

                    <span v-if="lastUpdated">
                        Обновлено: {{ formatDate(lastUpdated) }}
                    </span>

                    <span v-if="type.allows_generation" class="type-badge generated">
                        Печатная форма
                    </span>

                    <span v-if="type.is_archive" class="type-badge archive">
                        Архив фото
                    </span>
                </div>
            </div>
        </div>

        <div class="document-type-actions">
            <button v-if="type.is_archive && fileCount > 0" type="button" class="btn btn-sm btn-outline-primary"
                @click="$emit('gallery', type)">
                <i class="bi bi-images me-1"></i>
                Галерея
            </button>

            <button v-if="fileCount > 0" type="button" class="btn btn-sm btn-light border" @click="$emit('open', type)">
                <i class="bi bi-folder2-open me-1"></i>
                Открыть
            </button>

            <button v-if="type.allows_upload" type="button" class="btn btn-sm btn-primary"
                @click="$emit('upload', type)">
                <i class="bi bi-upload me-1"></i>
                Загрузить
            </button>

            <button v-if="type.allows_generation" type="button" class="btn btn-sm btn-outline-success"
                @click="$emit('generate', type)">
                <i class="bi bi-file-earmark-word me-1"></i>
                Создать
            </button>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    type: {
        type: Object,
        required: true,
    },
});

defineEmits([
    'upload',
    'open',
    'gallery',
    'generate',
]);

const files = computed(() => {
    return (props.type.documents || [])
        .flatMap(document => document.files || [])
        .filter(file => file.is_current !== false);
});

const displayFiles = computed(() => {
    return files.value.filter(file => {
        const isSourceFile = [
            'original',
            'generated_docx',
            'generated_pdf',
        ].includes(file.file_role);

        if (!isSourceFile) return false;

        if (props.type.is_photo) {
            return String(file.mime_type || '')
                .startsWith('image/');
        }

        return true;
    });
});

const fileCount = computed(() => displayFiles.value.length);

const fileCountLabel = computed(() => {
    const count = fileCount.value;

    if (props.type.is_photo) {
        return `${count} фото`;
    }

    const remainder100 = count % 100;
    const remainder10 = count % 10;

    const word = remainder100 >= 11 && remainder100 <= 14
        ? 'файлов'
        : remainder10 === 1
            ? 'файл'
            : remainder10 >= 2 && remainder10 <= 4
                ? 'файла'
                : 'файлов';

    return `${count} ${word}`;
});

const lastUpdated = computed(() => {
    const documents = props.type.documents || [];

    return documents.length
        ? documents[0].updated_at
        : null;
});

const iconClass = computed(() => {
    if (props.type.is_archive) {
        return 'bi bi-images';
    }

    if (props.type.is_generated) {
        return 'bi bi-file-earmark-text';
    }

    return 'bi bi-file-earmark';
});

function formatDate(value) {
    if (!value) return '';

    const date = new Date(value);

    return date.toLocaleDateString('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
}
</script>

<style scoped>
.document-type-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    min-height: 54px;
    padding: 7px 9px;
    border-bottom: 1px solid #e2e8f0;
    background: #ffffff;
}

.document-type-row:last-child {
    border-bottom: 0;
}

.document-type-main {
    display: flex;
    align-items: center;
    min-width: 0;
    gap: 9px;
}

.document-type-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
    width: 30px;
    height: 30px;
    border-radius: 6px;
    background: #eff6ff;
    color: #0369a1;
    font-size: 15px;
}

.document-type-info {
    min-width: 0;
}

.document-type-name {
    overflow: hidden;
    color: #1e293b;
    font-size: 12px;
    font-weight: 650;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.document-type-meta {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 5px 10px;
    color: #64748b;
    font-size: 10px;
}

.type-badge {
    padding: 1px 5px;
    border-radius: 4px;
    font-size: 9px;
    font-weight: 600;
}

.type-badge.generated {
    background: #ecfdf5;
    color: #047857;
}

.type-badge.archive {
    background: #f5f3ff;
    color: #6d28d9;
}

.document-type-actions {
    display: flex;
    flex: 0 0 auto;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 4px;
}

.document-type-actions .btn {
    font-size: 10px;
    white-space: nowrap;
}

@media (max-width: 700px) {
    .document-type-row {
        align-items: flex-start;
        flex-direction: column;
    }

    .document-type-actions {
        width: 100%;
        justify-content: flex-start;
    }

    .document-type-actions .btn {
        min-height: 32px;
    }
}
</style>
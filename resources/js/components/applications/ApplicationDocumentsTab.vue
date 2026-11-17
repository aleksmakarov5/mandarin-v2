<template>
    <div class="documents-tab-wrapper">
        <!-- ВЕРХНЯЯ ПАНЕЛЬ: ГЕНЕРАЦИЯ БЛАНКОВ И КНОПКА ЗАГРУЗКИ -->
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-body p-3">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <!-- Генерация печатных бланков -->
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <span class="small fw-bold text-muted text-uppercase me-1">Сформировать бланк:</span>
                        <button class="btn btn-sm btn-outline-dark d-flex align-items-center"
                            @click="generateDocument('inspection')" :disabled="generatingDoc === 'inspection'">
                            <i class="bi bi-file-earmark-pdf text-danger me-1"></i>
                            <span>Акт осмотра</span>
                        </button>
                        <button class="btn btn-sm btn-outline-dark d-flex align-items-center"
                            @click="generateDocument('direction')" :disabled="generatingDoc === 'direction'">
                            <i class="bi bi-file-earmark-text text-primary me-1"></i>
                            <span>Направление на СТО</span>
                        </button>
                        <button class="btn btn-sm btn-outline-dark d-flex align-items-center"
                            @click="generateDocument('acceptance')" :disabled="generatingDoc === 'acceptance'">
                            <i class="bi bi-file-earmark-check text-success me-1"></i>
                            <span>Акт приема-передачи</span>
                        </button>
                    </div>

                    <!-- Кнопка вызова скрытого инпута -->
                    <button class="btn btn-sm btn-primary fw-semibold d-flex align-items-center px-3"
                        @click="triggerFileInput">
                        <i class="bi bi-cloud-arrow-up-fill me-1"></i>
                        <span>Загрузить файлы</span>
                    </button>
                    <input ref="fileInputRef" type="file" multiple accept="image/*,.pdf,.doc,.docx,.xls,.xlsx"
                        class="d-none" @change="handleFilesSelected" />
                </div>
            </div>
        </div>

        <!-- ЗОНА DRAG & DROP ДЛЯ ПЕРЕТАСКИВАНИЯ ФАЙЛОВ -->
        <div class="upload-dropzone p-4 mb-3 text-center border rounded-3 bg-white"
            :class="{ 'dropzone-active': isDragging }" @dragover.prevent="isDragging = true"
            @dragleave.prevent="isDragging = false" @drop.prevent="handleDrop">
            <i class="bi bi-images text-primary fs-2 d-block mb-1"></i>
            <div class="fw-semibold small text-dark">Перетащите сюда фото повреждений или сканы документов</div>
            <div class="text-muted" style="font-size: 0.72rem;">Поддерживаются JPG, PNG, PDF, DOCX (до 25 МБ на файл)
            </div>
        </div>

        <!-- ТАБЫ ФИЛЬТРАЦИИ ФАЙЛОВ ПО КАТЕГОРИЯМ -->
        <div class="d-flex align-items-center justify-content-between mb-2">
            <div class="btn-group btn-group-sm">
                <button class="btn" :class="currentCategory === 'all' ? 'btn-dark' : 'btn-outline-secondary'"
                    @click="currentCategory = 'all'">
                    Все ({{ documents.length }})
                </button>
                <button class="btn" :class="currentCategory === 'photos' ? 'btn-dark' : 'btn-outline-secondary'"
                    @click="currentCategory = 'photos'">
                    Фото повреждений ({{ photoCount }})
                </button>
                <button class="btn" :class="currentCategory === 'docs' ? 'btn-dark' : 'btn-outline-secondary'"
                    @click="currentCategory = 'docs'">
                    Документы СК и СТО ({{ docCount }})
                </button>
            </div>

            <div class="text-muted small" v-if="uploading">
                <span class="spinner-border spinner-border-sm text-primary me-1" role="status"></span>
                Загрузка...
            </div>
        </div>

        <!-- СЕТКА ФАЙЛОВ -->
        <div v-if="filteredDocuments.length > 0" class="row g-2">
            <div v-for="file in filteredDocuments" :key="file.id" class="col-6 col-sm-4 col-md-3 col-xl-2">
                <div class="card h-100 file-card border shadow-sm">
                    <!-- ПРЕВЬЮ ДЛЯ ИЗОБРАЖЕНИЙ -->
                    <div v-if="isImage(file)" class="file-thumb ratio ratio-4x3 bg-light cursor-pointer"
                        @click="openPreview(file)">
                        <img :src="file.url" :alt="file.name" class="object-fit-cover rounded-top" />
                    </div>

                    <!-- ИКОНКА ДЛЯ ДОКУМЕНТОВ -->
                    <div v-else
                        class="file-thumb ratio ratio-4x3 bg-light d-flex align-items-center justify-content-center cursor-pointer"
                        @click="downloadFile(file)">
                        <i class="bi bi-file-earmark-pdf text-danger fs-1" v-if="file.name.endsWith('.pdf')"></i>
                        <i class="bi bi-file-earmark-word text-primary fs-1" v-else-if="file.name.includes('.doc')"></i>
                        <i class="bi bi-file-earmark-excel text-success fs-1"
                            v-else-if="file.name.includes('.xls')"></i>
                        <i class="bi bi-file-earmark text-secondary fs-1" v-else></i>
                    </div>

                    <!-- ИНФО О ФАЙЛЕ И КНОПКИ -->
                    <div class="card-body p-2 d-flex flex-column justify-content-between">
                        <div class="small fw-semibold text-truncate mb-1" :title="file.name">
                            {{ file.name }}
                        </div>
                        <div class="d-flex align-items-center justify-content-between text-muted"
                            style="font-size: 0.68rem;">
                            <span>{{ formatFileSize(file.size) }}</span>
                            <div class="d-flex gap-1">
                                <a :href="file.url" target="_blank" download
                                    class="btn btn-link btn-xs p-0 text-secondary" title="Скачать">
                                    <i class="bi bi-download"></i>
                                </a>
                                <button class="btn btn-link btn-xs p-0 text-danger" @click="deleteFile(file.id)"
                                    title="Удалить">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ПУСТОЙ СПИСОК -->
        <div v-else class="text-center py-5 bg-white rounded border text-muted">
            <i class="bi bi-folder-x fs-2 d-block mb-1 text-secondary"></i>
            Файлы и фотографии отсутствуют
        </div>

        <!-- МОДАЛЬНОЕ ОКНО ПРЕДПРОСМОТРА ФОТО -->
        <Teleport to="body">
            <div v-if="previewUrl" class="photo-lightbox-backdrop" @click="previewUrl = null">
                <div class="photo-lightbox-content" @click.stop>
                    <img :src="previewUrl" alt="Предпросмотр фото" class="img-fluid rounded shadow-lg" />
                    <button class="btn btn-sm btn-dark position-absolute top-0 end-0 m-2 rounded-circle"
                        @click="previewUrl = null">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';

const props = defineProps({
    applicationId: {
        type: [Number, String],
        required: true,
    },
    initialDocuments: {
        type: Array,
        default: () => [],
    },
});

const fileInputRef = ref(null);
const isDragging = ref(false);
const uploading = ref(false);
const generatingDoc = ref(null);
const previewUrl = ref(null);
const currentCategory = ref('all');

const documents = ref([...props.initialDocuments]);

const photoCount = computed(() => documents.value.filter((d) => isImage(d)).length);
const docCount = computed(() => documents.value.filter((d) => !isImage(d)).length);

const filteredDocuments = computed(() => {
    if (currentCategory.value === 'photos') {
        return documents.value.filter((d) => isImage(d));
    }
    if (currentCategory.value === 'docs') {
        return documents.value.filter((d) => !isImage(d));
    }
    return documents.value;
});

const isImage = (file) => {
    const ext = (file.name || '').split('.').pop().toLowerCase();
    return ['jpg', 'jpeg', 'png', 'webp', 'bmp'].includes(ext);
};

const triggerFileInput = () => {
    fileInputRef.value?.click();
};

const handleFilesSelected = (e) => {
    if (e.target.files?.length) {
        uploadFiles(Array.from(e.target.files));
    }
};

const handleDrop = (e) => {
    isDragging.value = false;
    if (e.dataTransfer?.files?.length) {
        uploadFiles(Array.from(e.dataTransfer.files));
    }
};

const uploadFiles = async (filesList) => {
    uploading.value = true;
    const formData = new FormData();
    filesList.forEach((file) => {
        formData.append('files[]', file);
    });

    try {
        const res = await axios.post(`/api/applications/${props.applicationId}/documents`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        if (res.data?.data) {
            documents.value.unshift(...res.data.data);
        }
    } catch (err) {
        console.error('Ошибка загрузки файлов:', err);
        alert('Не удалось загрузить файлы');
    } finally {
        uploading.value = false;
    }
};

const deleteFile = async (id) => {
    if (!confirm('Удалить этот файл?')) return;
    try {
        await axios.delete(`/api/applications/${props.applicationId}/documents/${id}`);
        documents.value = documents.value.filter((d) => d.id !== id);
    } catch (err) {
        console.error('Ошибка удаления файла:', err);
        alert('Не удалось удалить файл');
    }
};

const generateDocument = async (type) => {
    generatingDoc.value = type;
    try {
        const res = await axios.post(
            `/api/applications/${props.applicationId}/generate-doc`,
            { type },
            { responseType: 'blob' }
        );
        const blob = new Blob([res.data], { type: 'application/pdf' });
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `Бланк_${type}_дело_${props.applicationId}.pdf`;
        link.click();
        window.URL.revokeObjectURL(url);
    } catch (err) {
        console.error('Ошибка генерации документа:', err);
        alert('Ошибка генерации документа');
    } finally {
        generatingDoc.value = null;
    }
};

const openPreview = (file) => {
    previewUrl.value = file.url;
};

const downloadFile = (file) => {
    window.open(file.url, '_blank');
};

const formatFileSize = (bytes) => {
    if (!bytes) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
};
</script>

<style scoped>
.upload-dropzone {
    border: 2px dashed #cbd5e1 !important;
    cursor: pointer;
    transition: all 0.15s ease-in-out;
}

.upload-dropzone:hover,
.dropzone-active {
    border-color: #2563eb !important;
    background-color: #eff6ff !important;
}

.file-card {
    transition: transform 0.1s ease, box-shadow 0.1s ease;
}

.file-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08) !important;
}

.cursor-pointer {
    cursor: pointer;
}

/* Lightbox полноэкранного просмотра */
.photo-lightbox-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.85);
    z-index: 999999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.photo-lightbox-content {
    position: relative;
    max-width: 90vw;
    max-height: 90vh;
}

.photo-lightbox-content img {
    max-height: 85vh;
    object-fit: contain;
}
</style>
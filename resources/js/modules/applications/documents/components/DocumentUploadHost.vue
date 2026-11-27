<template>
    <DocumentUploadModal v-if="applicationId" :key="`document-upload-${applicationId}`" ref="uploadModalRef"
        @sources-uploaded="notifySourcesUploaded" />
</template>

<script setup>
import DocumentUploadModal from './DocumentUploadModal.vue';

import { useDocumentUploadWindow }
    from '../composables/useDocumentUploadWindow';

defineProps({
    applicationId: {
        type: [Number, String],
        default: null,
    },
});

const {
    uploadModalRef,
    notifySourcesUploaded,
} = useDocumentUploadWindow();
</script>
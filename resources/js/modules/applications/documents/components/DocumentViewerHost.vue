<template>
    <!--
        При сворачивании компонент НЕ размонтируется.
        Его содержимое скрывается внутри самой модалки.
    -->
    <DocumentFilesModal v-if="document" :key="`${document.application_id}-${document.id}`"
        :application-id="document.application_id" :document="document" :minimized="minimized" @minimize="minimize"
        @close="close" />

    <Teleport to="body">
        <DocumentViewerDock v-if="document && minimized" :document="document" @restore="restore" @close="close" />
    </Teleport>
</template>

<script setup>
import DocumentFilesModal from './DocumentFilesModal.vue';
import DocumentViewerDock from './DocumentViewerDock.vue';

import { useDocumentViewer } from '../composables/useDocumentViewer';

const {
    document,
    minimized,
    minimize,
    restore,
    close,
} = useDocumentViewer();
</script>
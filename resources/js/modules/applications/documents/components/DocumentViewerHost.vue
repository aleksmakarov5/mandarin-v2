<template>
    <!--
        При сворачивании компонент НЕ размонтируется.
        Его содержимое скрывается внутри самой модалки.
    -->
    <DocumentFilesModal v-if="document" :key="`${document.application_id}-${document.id}`"
        :application-id="document.application_id" :document="document" :minimized="minimized" @minimize="minimize"
        @close="close" />

    <Teleport to="body">
        <Transition name="viewer-dock">

            <DocumentViewerDock v-if="document && minimized" :document="document" @restore="restore" @close="close" />
        </Transition>
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
<style scoped>
.viewer-dock-enter-active,
.viewer-dock-leave-active {
    transition:
        opacity 180ms ease,
        transform 180ms ease;
}

.viewer-dock-enter-from,
.viewer-dock-leave-to {
    opacity: 0;
    transform: translateY(18px);
}

@media (prefers-reduced-motion: reduce) {

    .viewer-dock-enter-active,
    .viewer-dock-leave-active {
        transition: none;
    }
}
</style>
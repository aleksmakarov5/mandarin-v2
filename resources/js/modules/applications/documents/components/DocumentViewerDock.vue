<template>
    <aside class="viewer-dock" aria-label="Свёрнутый документ">
        <button ref="restoreButton" type="button" class="dock-restore"
            :title="`Развернуть: ${document.title || 'Документ'}`" @click="emit('restore')">
            <i class="bi bi-file-earmark-text dock-icon" aria-hidden="true"></i>

            <span class="dock-text">
                <strong>{{ document.title || 'Документ' }}</strong>
                <small>Нажмите, чтобы развернуть</small>
            </span>

            <i class="bi bi-chevron-up" aria-hidden="true"></i>
        </button>

        <button type="button" class="dock-close" title="Закрыть просмотр" aria-label="Закрыть просмотр документа"
            @click="emit('close')">
            ×
        </button>
    </aside>
</template>

<script setup>
import { onMounted, ref } from 'vue';

defineProps({
    document: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['restore', 'close']);

const restoreButton = ref(null);

onMounted(() => {
    restoreButton.value?.focus({ preventScroll: true });
});
</script>

<style scoped>
.viewer-dock {
    position: fixed;
    right: 16px;
    bottom: calc(var(--document-viewer-bottom-offset, 0px) + env(safe-area-inset-bottom, 0px) + 12px);
    z-index: 11900;

    display: flex;
    align-items: stretch;

    width: 340px;
    max-width: calc(100vw - 24px);

    overflow: hidden;
    border: 1px solid #cbd5e1;
    border-top: 3px solid #f97316;
    border-radius: 8px;
    background: #ffffff;
    box-shadow: 0 6px 24px rgb(15 23 42 / 20%);
}

.dock-restore {
    display: flex;
    flex: 1;
    align-items: center;
    gap: 10px;

    min-width: 0;
    padding: 9px 11px;

    border: 0;
    background: transparent;
    color: #334155;
    text-align: left;
    cursor: pointer;
}

.dock-restore:hover {
    background: #fff7ed;
}

.dock-icon {
    color: #0284c7;
    font-size: 23px;
}

.dock-text {
    flex: 1;
    min-width: 0;
}

.dock-text strong {
    display: block;
    overflow: hidden;
    font-size: 12px;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.dock-text small {
    display: block;
    margin-top: 3px;
    color: #64748b;
    font-size: 10px;
}

.dock-close {
    flex: 0 0 38px;
    border: 0;
    border-left: 1px solid #e2e8f0;
    background: #ffffff;
    color: #64748b;
    font-size: 23px;
    cursor: pointer;
}

.dock-close:hover {
    background: #fef2f2;
    color: #dc2626;
}

button:focus-visible {
    outline: 2px solid #0284c7;
    outline-offset: -3px;
}

@media (max-width: 767.98px) {
    .viewer-dock {
        right: 12px;
        width: min(340px, calc(100vw - 24px));
    }

    .dock-close {
        flex-basis: 44px;
    }

    .dock-restore {
        min-height: 48px;
    }
}
</style>
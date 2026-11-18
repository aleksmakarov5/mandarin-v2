<template>
    <Teleport to="body">
        <div v-if="tooltip.visible" class="floating-cell-tooltip shadow-lg"
            :style="{ top: tooltip.y + 'px', left: tooltip.x + 'px' }">
            {{ tooltip.text }}
        </div>
    </Teleport>
</template>

<script setup>
defineProps({
    tooltip: Object,
});
</script>

<style>
.floating-cell-tooltip {
    position: fixed !important;
    transform: translateX(-50%) !important;
    background-color: #ffffff !important;
    color: #0f172a !important;
    border: 1px solid #cbd5e1 !important;
    border-radius: 4px;
    padding: 4px 8px;
    font-size: 0.72rem;
    font-weight: 500;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
    white-space: nowrap;
    box-shadow: 0 10px 25px -3px rgba(0, 0, 0, 0.18), 0 4px 6px -2px rgba(0, 0, 0, 0.08) !important;
    z-index: 9999999 !important;
    pointer-events: none;
    animation: tooltipFadeIn 0.12s ease-out;
}

.floating-cell-tooltip::after {
    content: "";
    position: absolute;
    top: 100%;
    left: 50%;
    transform: translateX(-50%);
    border-width: 5px;
    border-style: solid;
    border-color: #ffffff transparent transparent transparent;
}

@keyframes tooltipFadeIn {
    from {
        opacity: 0;
        transform: translate(-50%, 4px);
    }

    to {
        opacity: 1;
        transform: translate(-50%, 0);
    }
}
</style>
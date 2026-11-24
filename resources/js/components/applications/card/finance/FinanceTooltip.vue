<template>
    <section ref="anchor" class="finance-tooltip-anchor" tabindex="0" :aria-describedby="opened ? tooltipId : undefined"
        @mouseenter="scheduleOpen" @mouseleave="scheduleClose" @focusin="open" @focusout="handleFocusOut">


        <slot />

        <Teleport to="body">
            <div v-if="opened" :id="tooltipId" ref="popup" class="finance-tooltip-popup" role="tooltip"
                :style="position" @mouseenter="cancelClose" @mouseleave="scheduleClose">
                <div class="tooltip-heading">{{ heading }}</div>

                <div v-for="(row, index) in rows" :key="index" class="tooltip-row"
                    :class="{ 'tooltip-row-total': row.total }">
                    <span>{{ row.label }}</span>
                    <strong>{{ row.value }}</strong>
                </div>

                <div v-if="note" class="tooltip-note">
                    {{ note }}
                </div>
            </div>
        </Teleport>
    </section>
</template>

<script setup>
import {
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
} from 'vue';

defineProps({
    heading: {
        type: String,
        required: true,
    },
    rows: {
        type: Array,
        default: () => [],
    },
    note: {
        type: String,
        default: '',
    },
});

const anchor = ref(null);
const popup = ref(null);
const opened = ref(false);
const pinned = ref(false);

const tooltipId = `finance-tooltip-${Math.random()
    .toString(36)
    .slice(2)}`;

const position = ref({
    left: '0px',
    top: '0px',
    visibility: 'hidden',
});

let openTimer = null;
let closeTimer = null;
let destroyed = false;

function cancelClose() {
    clearTimeout(closeTimer);
}

function updatePosition() {
    if (!opened.value || !anchor.value || !popup.value) return;

    const rect = anchor.value.getBoundingClientRect();

    if (
        rect.bottom < 0
        || rect.top > window.innerHeight
        || rect.right < 0
        || rect.left > window.innerWidth
    ) {
        close();
        return;
    }

    const margin = 12;
    const gap = 9;
    const width = popup.value.offsetWidth;
    const height = popup.value.offsetHeight;

    const left = Math.max(
        margin,
        Math.min(rect.left, window.innerWidth - width - margin)
    );

    const below = rect.bottom + gap;
    const above = rect.top - height - gap;

    let top = below;

    if (below + height > window.innerHeight - margin) {
        top = above >= margin
            ? above
            : Math.max(
                margin,
                window.innerHeight - height - margin
            );
    }

    position.value = {
        left: `${left}px`,
        top: `${top}px`,
        visibility: 'visible',
    };
}

async function open() {
    clearTimeout(openTimer);
    cancelClose();

    opened.value = true;

    await nextTick();

    if (!destroyed && opened.value) {
        updatePosition();
    }
}

function close() {
    clearTimeout(openTimer);
    cancelClose();

    opened.value = false;
    pinned.value = false;
}

function scheduleOpen() {
    clearTimeout(openTimer);
    cancelClose();

    openTimer = setTimeout(open, 180);
}

function scheduleClose() {
    clearTimeout(openTimer);

    if (pinned.value) return;

    cancelClose();

    closeTimer = setTimeout(() => {
        if (
            anchor.value?.contains(document.activeElement)
            || anchor.value?.matches(':hover')
            || popup.value?.matches(':hover')
        ) {
            return;
        }

        close();
    }, 140);
}

function handleFocusOut(event) {
    if (anchor.value?.contains(event.relatedTarget)) return;

    scheduleClose();
}

function togglePinned() {
    if (pinned.value) {
        close();
    } else {
        pinned.value = true;
        open();
    }
}

function handleOutside(event) {
    if (
        !anchor.value?.contains(event.target)
        && !popup.value?.contains(event.target)
    ) {
        close();
    }
}

function handleKeydown(event) {
    if (event.key === 'Escape') {
        close();
    }
}

onMounted(() => {
    document.addEventListener('pointerdown', handleOutside);
    document.addEventListener('keydown', handleKeydown);
    window.addEventListener('resize', updatePosition);
    window.addEventListener('scroll', updatePosition, true);
});

onBeforeUnmount(() => {
    destroyed = true;
    close();

    document.removeEventListener('pointerdown', handleOutside);
    document.removeEventListener('keydown', handleKeydown);
    window.removeEventListener('resize', updatePosition);
    window.removeEventListener('scroll', updatePosition, true);
});
</script>

<style scoped>
.finance-tooltip-anchor {
    position: relative;
}

.finance-info-button {
    position: absolute;
    top: 4px;
    right: 5px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    padding: 0;
    border: 0;
    border-radius: 50%;
    background: transparent;
    color: #64748b;
    font-size: 14px;
    cursor: pointer;
}

.finance-info-button:hover,
.finance-info-button[aria-expanded="true"] {
    background: #e2e8f0;
    color: #0369a1;
}

.finance-info-button:focus-visible {
    outline: 2px solid #38bdf8;
    outline-offset: 1px;
}

.finance-tooltip-popup {
    position: fixed;
    z-index: 11000;
    width: 360px;
    max-width: calc(100vw - 24px);
    max-height: calc(100dvh - 24px);
    overflow-y: auto;
    box-sizing: border-box;
    padding: 14px;
    border: 1px solid #dbe4ee;
    border-radius: 12px;
    background: #ffffff;
    color: #334155;
    box-shadow:
        0 16px 40px rgb(15 23 42 / 18%),
        0 3px 8px rgb(15 23 42 / 8%);
    font-family: inherit;
    font-size: 12px;
    line-height: 1.45;
    white-space: normal;
}

.tooltip-heading {
    margin-bottom: 9px;
    color: #0f172a;
    font-size: 13px;
    font-weight: 750;
}

.tooltip-row {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 18px;
    padding: 4px 0;
}

.tooltip-row span {
    min-width: 0;
}

.tooltip-row strong {
    flex-shrink: 0;
    color: #0f172a;
    text-align: right;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
}

.tooltip-row-total {
    margin-top: 5px;
    padding-top: 8px;
    border-top: 1px solid #e2e8f0;
}

.tooltip-row-total strong {
    font-weight: 800;
}

.tooltip-note {
    margin-top: 10px;
    padding: 8px;
    border-radius: 7px;
    background: #f1f5f9;
    color: #64748b;
    font-size: 11px;
}

.finance-tooltip-popup {
    width: 320px;
    padding: 10px;
    border-radius: 9px;
    font-size: 11px;
}

.tooltip-heading {
    margin-bottom: 6px;
    font-size: 12px;
}

.tooltip-row {
    padding: 3px 0;
}

.tooltip-note {
    margin-top: 7px;
    padding: 6px;
    font-size: 10px;
}

.finance-tooltip-anchor {
    position: relative;
    box-sizing: border-box;
}

.finance-info-button {
    position: absolute;
    z-index: 3;
    top: 4px;
    right: 4px;
    width: 18px;
    height: 18px;
    padding: 0;
    line-height: 1;
    font-size: 11px;
}
</style>
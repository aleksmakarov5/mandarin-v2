<template>
    <section class="document-category">
        <button type="button" class="category-header" :class="`category-${category.color}`"
            @click="collapsed = !collapsed">
            <span class="category-title">
                <span class="category-icon">
                    <i :class="`bi ${category.icon}`"></i>
                </span>

                <span>{{ category.title }}</span>

                <span class="category-count">
                    {{ types.length }}
                </span>
            </span>

            <i class="bi bi-chevron-right category-chevron" :class="{ rotated: !collapsed }"></i>
        </button>

        <div v-show="!collapsed" class="category-body">
            <DocumentTypeRow v-for="type in types" :key="type.id" :type="type" @upload="$emit('upload', $event)"
                @open="$emit('open', $event)" @gallery="$emit('gallery', $event)"
                @generate="$emit('generate', $event)" />

            <div v-if="!types.length" class="empty-category">
                Документы этой категории пока не добавлены.
            </div>
        </div>
    </section>
</template>

<script setup>
import { ref } from 'vue';
import DocumentTypeRow from './DocumentTypeRow.vue';

defineProps({
    category: {
        type: Object,
        required: true,
    },

    types: {
        type: Array,
        default: () => [],
    },
});

defineEmits([
    'upload',
    'open',
    'gallery',
    'generate',
]);

const collapsed = ref(false);
</script>

<style scoped>
.document-category {
    overflow: hidden;
    border: 1px solid #dbe4ec;
    border-radius: 7px;
    background: #ffffff;
    box-shadow: 0 1px 3px rgb(15 23 42 / 5%);
}

.category-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    min-height: 42px;
    padding: 5px 9px;
    border: 0;
    border-bottom: 1px solid transparent;
    color: #1e293b;
    font: inherit;
    font-size: 12px;
    font-weight: 700;
    text-align: left;
    cursor: pointer;
}

.category-title {
    display: flex;
    align-items: center;
    gap: 7px;
}

.category-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 25px;
    height: 25px;
    border-radius: 5px;
    color: #ffffff;
}

.category-count {
    min-width: 18px;
    padding: 1px 5px;
    border-radius: 10px;
    background: rgb(255 255 255 / 65%);
    color: #475569;
    font-size: 10px;
    text-align: center;
}

.category-blue {
    background: #eff6ff;
}

.category-blue .category-icon {
    background: #0284c7;
}

.category-indigo {
    background: #eef2ff;
}

.category-indigo .category-icon {
    background: #4f46e5;
}

.category-orange {
    background: #fff7ed;
}

.category-orange .category-icon {
    background: #ea580c;
}

.category-violet {
    background: #f5f3ff;
}

.category-violet .category-icon {
    background: #7c3aed;
}

.category-green {
    background: #ecfdf5;
}

.category-green .category-icon {
    background: #059669;
}

.category-slate {
    background: #f8fafc;
}

.category-slate .category-icon {
    background: #475569;
}

.category-chevron {
    color: #64748b;
    transition: transform 0.18s ease;
}

.category-chevron.rotated {
    transform: rotate(90deg);
}

.category-body {
    border-top: 1px solid #dbe4ec;
}

.empty-category {
    padding: 18px 10px;
    color: #94a3b8;
    font-size: 11px;
    text-align: center;
}
</style>
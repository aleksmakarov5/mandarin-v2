<template>
    <div v-if="pagination.last_page > 1"
        class="d-flex justify-content-between align-items-center px-3 py-2 border-top bg-white pagination-bar shadow-sm">
        <small class="text-muted">
            Показано {{ (pagination.current_page - 1) * 50 + 1 }} - {{ Math.min(pagination.current_page * 50,
                pagination.total) }} из {{ pagination.total }}
        </small>
        <ul class="pagination pagination-sm mb-0">
            <li class="page-item" :class="{ disabled: pagination.current_page === 1 }">
                <button class="page-link" @click="$emit('change', pagination.current_page - 1)">&laquo;</button>
            </li>
            <li v-for="p in pagination.last_page" :key="p" class="page-item"
                :class="{ active: pagination.current_page === p }">
                <button class="page-link" @click="$emit('change', p)">{{ p }}</button>
            </li>
            <li class="page-item" :class="{ disabled: pagination.current_page === pagination.last_page }">
                <button class="page-link" @click="$emit('change', pagination.current_page + 1)">&raquo;</button>
            </li>
        </ul>
    </div>
</template>

<script setup>
defineProps({
    pagination: Object,
});
defineEmits(['change']);
</script>

<style scoped>
.pagination-bar {
    position: sticky;
    bottom: 0;
    z-index: 5;
    border-top: 1px solid #000000 !important;
}
</style>
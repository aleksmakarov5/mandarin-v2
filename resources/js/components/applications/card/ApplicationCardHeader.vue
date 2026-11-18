<template>
    <header class="header border-bottom bg-white p-2 d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-sm btn-outline-secondary" @click="$emit('back')">
                <i class="bi bi-arrow-left"></i> Назад
            </button>
            <span class="fw-bold font-monospace">#{{ applicationId }}</span>
        </div>
        <div v-if="showSave">
            <button class="btn btn-sm btn-primary" :disabled="saving" @click="$emit('save')">
                {{ saving ? 'Сохранение...' : 'Сохранить' }}
            </button>
        </div>
    </header>
</template>



<script setup>

const goBack = () => {
    const fromTab = route.query.from_tab;
    if (fromTab) {
        router.push({ name: fromTab });
    } else {
        router.back();
    }
};
defineProps({
    applicationId: [Number, String],
    application: Object,
    status: Object,
    saving: Boolean,
    showSave: Boolean
});

defineEmits(['back', 'save']);
</script>
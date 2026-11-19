<template>
    <div class="application-estimate-module h-100 overflow-auto">
        <!-- Передаем applicationId из пропсов роутера в компонент сметы -->
        <ApplicationEstimateTab v-if="applicationId" :application-id="applicationId" @updated="onEstimateUpdated" />
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import ApplicationEstimateTab from '../../../components/applications/ApplicationEstimateTab.vue';

const props = defineProps({
    applicationId: {
        type: Number,
        default: null,
    },
});

const emit = defineEmits(['loaded', 'saved', 'updated']);

const route = useRoute();

// Получаем ID дела либо из пропса от ApplicationShow, либо прямо из параметров URL
const currentAppId = computed(() => {
    return props.applicationId || Number(route.params.id);
});

const onEstimateUpdated = () => {
    emit('updated');
};
</script>

<style scoped>
.application-estimate-module {
    min-height: 100%;
}
</style>
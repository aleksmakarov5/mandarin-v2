<template>
    <ApplicationDocumentsTab :application-id="props.applicationId" @upload="emit('upload', $event)"
        @open-file="emit('open-file', $event)" @open-gallery="emit('open-gallery', $event)"
        @generate="emit('generate', $event)" />
</template>

<script setup>
import ApplicationDocumentsTab from './ApplicationDocumentsTab.vue';

const props = defineProps({
    applicationId: {
        type: [Number, String],
        required: true,
    },
});

const emit = defineEmits([
    'upload',
    'open-file',
    'open-gallery',
    'generate',
]);
</script>
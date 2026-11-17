<template>
    <ApplicationGeneralTab ref="generalTab" :application-id="applicationId" @loaded="emit('loaded', $event)"
        @saved="emit('saved', $event)" @error="emit('error', $event)" />
</template>

<script setup>
import { ref } from 'vue';
import ApplicationGeneralTab
    from '../../../components/applications/ApplicationGeneralTab.vue';

defineProps({
    applicationId: {
        type: Number,
        required: true,
    },
});

const emit = defineEmits(['loaded', 'saved', 'error']);
const generalTab = ref(null);

async function save() {
    if (typeof generalTab.value?.save !== 'function') {
        return false;
    }

    return generalTab.value.save();
}

async function reload() {
    if (typeof generalTab.value?.reload === 'function') {
        return generalTab.value.reload();
    }

    return false;
}

defineExpose({
    save,
    reload,
});
</script>
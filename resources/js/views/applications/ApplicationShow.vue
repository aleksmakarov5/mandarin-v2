<template>
    <ApplicationGeneralTab v-if="form?.id" v-show="activeTab === 'general'" ref="generalTab"
        :application-id="Number(form.id)" @loaded="syncGeneralData" @saved="syncGeneralData" />
</template>
<script setup>
import {
    ref,
    reactive,
    computed,
    watch,
    nextTick,
    onMounted,
    onBeforeUnmount,
} from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import ApplicationGeneralTab from '../../components/applications/ApplicationGeneralTab.vue';

const route = useRoute();
const router = useRouter();

const loading = ref(true);
const saving = ref(false);
const activeTab = ref('general');
const form = ref(null);
const generalTab = ref(null);

function syncGeneralData(data) {
    if (form.value) {
        Object.assign(form.value, data);
    }
}

async function saveGeneral() {
    return generalTab.value?.save();
}
</script>
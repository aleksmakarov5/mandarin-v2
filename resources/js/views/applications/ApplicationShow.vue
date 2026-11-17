<template>
    <div class="application-card-layout d-flex flex-column h-100 bg-light">
        <!-- ТЕСТОВЫЙ БЛОК -->
        <div style="background: red; color: white; padding: 10px;">
            ApplicationShow МЕНЯ ВИДНО!
        </div>
        <ApplicationCardHeader :application-id="applicationId" :application="cardData" :status="currentStatus"
            :saving="saving" :show-save="canSaveActiveModule" @back="goBack" @save="saveActiveModule" />

        <ApplicationCardSummary :application="cardData" :insurance-name="currentInsuranceName" />

        <ApplicationCardMenu v-if="hasValidApplicationId" :application-id="applicationId" />

        <div v-if="moduleError" class="alert alert-danger rounded-0 border-start-0 border-end-0 py-2 px-3 mb-0">
            <pre class="module-error mb-0">{{ moduleError }}</pre>
        </div>

        <main class="flex-grow-1 overflow-auto p-2 p-md-3">
            <div v-if="!hasValidApplicationId" class="alert alert-danger">
                В адресе карточки указан некорректный ID дела.
            </div>

            <RouterView v-else v-slot="{ Component }">
                <KeepAlive>
                    <component :is="Component" :key="`${applicationId}:${route.name}`" ref="activeModule"
                        :application-id="applicationId" @loaded="syncCardData" @saved="syncCardData"
                        @error="handleModuleError" />
                </KeepAlive>
            </RouterView>
        </main>
    </div>
</template>

<script setup>
import {
    computed,
    onBeforeUnmount,
    onMounted,
    reactive,
    ref,
    watch,
} from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';

import ApplicationCardHeader
    from '../../components/applications/card/ApplicationCardHeader.vue';
import ApplicationCardSummary
    from '../../components/applications/card/ApplicationCardSummary.vue';
import ApplicationCardMenu
    from '../../components/applications/card/ApplicationCardMenu.vue';

const route = useRoute();
const router = useRouter();

const activeModule = ref(null);
const cardData = ref({});
const saving = ref(false);
const moduleError = ref('');

const options = reactive({
    statuses: [],
    insurance_companies: [],
});

const applicationId = computed(() => Number(route.params.id));

const hasValidApplicationId = computed(() => (
    Number.isInteger(applicationId.value)
    && applicationId.value > 0
));

const canSaveActiveModule = computed(() => (
    route.meta.cardModule === 'general'
));

const currentStatus = computed(() => {
    const statusId = Number(
        cardData.value.id_status ?? cardData.value.status_id
    );

    return options.statuses.find(
        (status) => Number(status.id) === statusId
    ) || cardData.value.status || null;
});

const currentInsuranceName = computed(() => {
    const companyId = Number(cardData.value.id_insuranceCompany);

    const company = options.insurance_companies.find(
        (item) => Number(item.id) === companyId
    );

    return company?.name
        || cardData.value.insurance_company?.name
        || 'Страховая компания не выбрана';
});

function syncCardData(data) {
    if (!data) {
        return;
    }

    cardData.value = {
        ...cardData.value,
        ...data,
    };

    moduleError.value = '';
}

function handleModuleError(error) {
    moduleError.value = typeof error === 'string'
        ? error
        : error?.message || 'Ошибка работы модуля карточки.';
}

async function saveActiveModule() {
    if (
        saving.value
        || !canSaveActiveModule.value
        || typeof activeModule.value?.save !== 'function'
    ) {
        return false;
    }

    saving.value = true;
    moduleError.value = '';

    try {
        return await activeModule.value.save();
    } catch (error) {
        console.error('Ошибка сохранения активного модуля', error);
        handleModuleError(error);
        return false;
    } finally {
        saving.value = false;
    }
}

async function loadCardOptions() {
    try {
        const response = await axios.get(
            '/api/applications/general-options'
        );

        options.statuses = response.data?.statuses || [];
        options.insurance_companies =
            response.data?.insurance_companies || [];
    } catch (error) {
        // Ошибка справочников шапки не блокирует открытие вкладки.
        console.error(
            'Не удалось загрузить справочники Header/Summary',
            error
        );
    }
}

function goBack() {
    router.back();
}

function handleKeyboard(event) {
    if (
        (event.ctrlKey || event.metaKey)
        && event.key.toLowerCase() === 's'
    ) {
        event.preventDefault();
        saveActiveModule();
    }
}

watch(applicationId, () => {
    cardData.value = {};
    moduleError.value = '';
});

onMounted(() => {
    loadCardOptions();
    window.addEventListener('keydown', handleKeyboard);
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleKeyboard);
});
</script>

<style scoped>
.application-card-layout {
    min-height: 0;
    overflow: hidden;
}

.module-error {
    font: inherit;
    font-size: 0.78rem;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
}
</style>
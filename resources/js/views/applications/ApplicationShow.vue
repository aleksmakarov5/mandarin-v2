<template>
    <div class="application-card-layout h-100 bg-light">
        <!-- Эта часть уходит вверх при прокрутке -->
        <div class="application-card-top">
            <ApplicationCardHeader :application-id="applicationId" />

            <ApplicationCardSummary :application="cardData" :insurance-name="currentInsuranceName"
                :insurance-companies="options.insurance_companies" :current-status="currentStatus"
                :all-statuses="options.statuses" :all-finance-statuses="options.finance_statuses"
                :permissions="cardData.permissions?.status ?? {}" @status-change="onStatusChanged"
                @finance-status-change="onFinanceStatusChanged" />

            <ApplicationDoubleProgressBar v-if="cardData?.id" :app-timer="cardData.app_timer || {}"
                :finance-timer="cardData.finance_timer || {}" :finance-status-id="cardData.finance_status_id
                    ?? cardData.id_finance_status
                    " />
        </div>

        <!-- Только меню остаётся сверху -->
        <div class="application-card-sticky-menu">
            <ApplicationCardMenu v-if="hasValidApplicationId" :application-id="applicationId"
                :permissions="cardData.permissions?.menu ?? {}" />
        </div>

        <div v-if="moduleError" class="alert alert-danger rounded-0 border-start-0 border-end-0 py-2 px-3 mb-0">
            <pre class="module-error mb-0">{{ moduleError }}</pre>
        </div>

        <!-- Собственную прокрутку main убираем -->
        <main class="application-card-content p-2 p-md-3">
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
    <DocumentViewerHost />
    <DocumentUploadHost :application-id="applicationId" />
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

import ApplicationCardHeader from '../../components/applications/card/ApplicationCardHeader.vue';
import ApplicationCardSummary from '../../components/applications/card/ApplicationCardSummary.vue';
import ApplicationCardMenu from '../../components/applications/card/ApplicationCardMenu.vue';
import ApplicationDoubleProgressBar from '../../components/applications/card/ApplicationDoubleProgressBar.vue';
import DocumentViewerHost
    from '@/modules/applications/documents/components/DocumentViewerHost.vue';

import { provideDocumentViewer }
    from '@/modules/applications/documents/composables/useDocumentViewer';
import DocumentUploadHost
    from '@/modules/applications/documents/components/DocumentUploadHost.vue';

import { provideDocumentUploadWindow }
    from '@/modules/applications/documents/composables/useDocumentUploadWindow';

const route = useRoute();
const router = useRouter();

const activeModule = ref(null);
const cardData = ref({});
const saving = ref(false);
const moduleError = ref('');

// Справочники
const options = reactive({
    statuses: [],
    finance_statuses: [], // ОБЯЗАТЕЛЬНО!
    insurance_companies: [],
});

const applicationId = computed(() => Number(route.params.id));
provideDocumentViewer(applicationId);
provideDocumentUploadWindow();
const hasValidApplicationId = computed(() => (
    Number.isInteger(applicationId.value) && applicationId.value > 0
));

const canSaveActiveModule = computed(() => (
    route.meta?.cardModule === 'general'
));

// Текущий статус дела для Changers
const currentStatus = computed(() => {
    const statusId = Number(cardData.value.id_status ?? cardData.value.status_id);
    if (!statusId || !options.statuses.length) {
        return cardData.value.status || null;
    }
    return options.statuses.find((s) => Number(s.id) === statusId) || cardData.value.status || null;
});

// Название страховой компании
const currentInsuranceName = computed(() => {
    const companyId = Number(cardData.value.id_insuranceCompany ?? cardData.value.insurance_company_id);
    const company = options.insurance_companies.find((item) => Number(item.id) === companyId);
    return company?.name || cardData.value.insurance_company?.name || 'Страховая компания не выбрана';
});

// Синхронизация данных от табов
function syncCardData(data) {
    if (!data) return;
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

// Загрузка первичных данных дела прямо в родителе (чтобы Summary сразу заполнился)
async function loadApplicationData() {
    if (!hasValidApplicationId.value) return;
    try {
        const res = await axios.get(`/api/applications/${applicationId.value}`);
        const data = res.data?.data || res.data || {};
        syncCardData(data);
    } catch (err) {
        console.error('Ошибка загрузки данных дела в ApplicationShow:', err);

        if (err.response?.status === 403) {
            alert(
                err.response?.data?.message
                || 'Нет доступа к этому делу.'
            );
            router.push('/requests'); // или router.push('/applications')
        } else if (err.response?.status === 404) {
            alert('Дело с таким номером не найдено.');
            router.push('/requests');
        }
    }
}

// Загрузка справочников (статусы, фин. статусы, СК)
async function loadCardOptions() {
    try {
        const res = await axios.get('/api/applications/general-options');
        const data = res.data || {};

        options.statuses = data.statuses || [];
        options.finance_statuses = data.finance_statuses || [];
        options.insurance_companies = data.insurance_companies || []; // Проверьте эту строку!
    } catch (error) {
        console.error('Не удалось загрузить справочники:', error);
    }
}

// Обработчики смены статусов
async function onStatusChanged(newStatus) {
    if (!newStatus?.id || !applicationId.value) return;

    const prevId = cardData.value.id_status || cardData.value.status_id;
    if ('id_status' in cardData.value) {
        cardData.value.id_status = newStatus.id;
    } else {
        cardData.value.status_id = newStatus.id;
    }

    try {
        await axios.patch(`/api/applications/${applicationId.value}/status`, {
            status_id: newStatus.id,
        });
        // При успехе перезапрашиваем актуальное дело (чтобы обновились таймеры)
        loadApplicationData();
    } catch (err) {
        console.error('Ошибка смены статуса:', err);
        if ('id_status' in cardData.value) {
            cardData.value.id_status = prevId;
        } else {
            cardData.value.status_id = prevId;
        }
    }
}

async function onFinanceStatusChanged(newFinanceStatus) {
    if (!newFinanceStatus?.id || !applicationId.value) return;

    const prevId = cardData.value.finance_status_id || cardData.value.id_finance_status;
    if ('id_finance_status' in cardData.value) {
        cardData.value.id_finance_status = newFinanceStatus.id;
    } else {
        cardData.value.finance_status_id = newFinanceStatus.id;
    }

    try {
        await axios.patch(`/api/applications/${applicationId.value}/finance-status`, {
            finance_status_id: newFinanceStatus.id,
        });
        loadApplicationData();
    } catch (err) {
        console.error('Ошибка смены фин. статуса:', err);
        if ('id_finance_status' in cardData.value) {
            cardData.value.id_finance_status = prevId;
        } else {
            cardData.value.finance_status_id = prevId;
        }
    }
}

async function saveActiveModule() {
    if (saving.value || !canSaveActiveModule.value || typeof activeModule.value?.save !== 'function') {
        return false;
    }
    saving.value = true;
    moduleError.value = '';
    try {
        return await activeModule.value.save();
    } catch (error) {
        console.error('Ошибка сохранения активного модуля:', error);
        handleModuleError(error);
        return false;
    } finally {
        saving.value = false;
    }
}

function goBack() {
    router.back();
}

function handleKeyboard(event) {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
        event.preventDefault();
        saveActiveModule();
    }
}

watch(applicationId, () => {
    cardData.value = {};
    moduleError.value = '';
    loadApplicationData();
});

onMounted(() => {
    loadCardOptions();
    loadApplicationData();
    window.addEventListener('keydown', handleKeyboard);
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleKeyboard);
});
</script>

<style scoped>
/*
 * Вся карточка — единая область прокрутки.
 * Финансовая шапка, сводка и таймеры прокручиваются
 * вместе с содержимым активной вкладки.
 */
.application-card-layout {
    position: relative;
    display: block;
    width: 100%;
    height: 100%;
    min-width: 0;
    min-height: 0;
    overflow-y: auto;
    overflow-x: hidden;
    box-sizing: border-box;
    scrollbar-gutter: stable;
    -webkit-overflow-scrolling: touch;
}

/* Верхние элементы не закрепляются. */
.application-card-top {
    position: relative;
}

/*
 * Убираем возможное старое sticky-позиционирование
 * финансовой шапки, если оно осталось в её компоненте.
 */
.application-card-top :deep(.application-finance-header) {
    position: static;
    top: auto;
}

/*
 * Меню прилипает к верхней границе области карточки.
 * Высота остальных элементов шапки не учитывается:
 * при прокрутке они полностью уходят из видимой области.
 */
.application-card-sticky-menu {
    position: sticky;
    top: 0;
    z-index: 30;
    width: 100%;
    background: #ffffff;
    box-shadow: 0 3px 8px rgb(15 23 42 / 8%);
}

/* Внутри main больше нет отдельного скролла. */
.application-card-content {
    min-width: 0;
    overflow: visible;
    box-sizing: border-box;
}

.module-error {
    font: inherit;
    font-size: 0.78rem;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
}
</style>
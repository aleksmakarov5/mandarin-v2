<template>
    <nav class="application-card-menu bg-white border-bottom flex-shrink-0">
        <div ref="scrollContainer" class="application-card-menu__scroll">
            <div class="application-card-menu__items">
                <RouterLink v-for="tab in tabs" :key="tab.id" :to="{
                    name: tab.routeName,
                    params: { id: applicationId },
                }" class="application-card-menu__item" active-class="active">
                    <i :class="tab.icon" aria-hidden="true"></i>
                    <span>{{ tab.label }}</span>
                </RouterLink>
            </div>
        </div>
    </nav>
</template>

<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { useRoute } from 'vue-router';

const props = defineProps({
    applicationId: {
        type: Number,
        required: true,
    },

    permissions: {
        type: Object,
        default: () => ({}),
    },
});

const route = useRoute();
const scrollContainer = ref(null);

const allTabs = [
    {
        id: 'general',
        label: 'Основное',
        icon: 'bi bi-info-circle',
        routeName: 'applications.general',
    },
    {
        id: 'documents',
        label: 'Документы',
        icon: 'bi bi-folder2-open',
        routeName: 'applications.documents',
    },
    {
        id: 'correspondence',
        label: 'Переписка с СК',
        icon: 'bi bi-chat-left-text',
        routeName: 'applications.correspondence',
        permission: 'correspondence',
    },
    {
        id: 'estimate',
        label: 'Смета',
        icon: 'bi bi-calculator',
        routeName: 'applications.estimate',
    },
    {
        id: 'defects',
        label: 'Дефектовка',
        icon: 'bi bi-wrench-adjustable',
        routeName: 'applications.defects',
    },
    {
        id: 'history',
        label: 'История',
        icon: 'bi bi-clock-history',
        routeName: 'applications.history',
        permission: 'history',
    },
];

const tabs = computed(() => {
    return allTabs.filter(tab => {
        if (!tab.permission) {
            return true;
        }

        // Пока разрешения не загружены, закрытые вкладки
        // не показываем даже на короткое время.
        return props.permissions?.[tab.permission] === true;
    });
});

watch(
    [
        () => route.fullPath,
        () => props.applicationId,
        () => tabs.value.map(tab => tab.id).join(','),
    ],
    async () => {
        await nextTick();

        scrollContainer.value
            ?.querySelector('.application-card-menu__item.active')
            ?.scrollIntoView({
                behavior: 'smooth',
                block: 'nearest',
                inline: 'center',
            });
    },
    { immediate: true }
);
</script>

<style scoped>
.application-card-menu {
    position: relative;
    z-index: 5;
}

.application-card-menu__scroll {
    overflow-x: auto;
    overflow-y: hidden;
    padding: 7px 12px;
    scrollbar-width: none;
    -webkit-overflow-scrolling: touch;
}

.application-card-menu__scroll::-webkit-scrollbar {
    display: none;
}

.application-card-menu__items {
    display: inline-flex;
    gap: 4px;
    min-width: max-content;
    padding: 4px;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    border-radius: 9px;
}

.application-card-menu__item {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    min-height: 36px;
    padding: 7px 14px;
    color: #64748b;
    border-radius: 6px;
    font-size: 0.82rem;
    font-weight: 600;
    line-height: 1.2;
    text-decoration: none;
    white-space: nowrap;
    transition: color 0.16s ease, background-color 0.16s ease;
}

.application-card-menu__item:hover {
    color: #1e293b;
    background: rgba(255, 255, 255, 0.75);
}

.application-card-menu__item.active {
    color: #ffffff;
    background: linear-gradient(135deg, #f97316, #ea580c);
    box-shadow: 0 2px 6px rgba(234, 88, 12, 0.28);
}

@media (max-width: 767.98px) {
    .application-card-menu__scroll {
        padding: 5px 8px;
        scroll-snap-type: x proximity;
    }

    .application-card-menu__items {
        gap: 3px;
        padding: 3px;
    }

    .application-card-menu__item {
        min-height: 40px;
        padding: 8px 11px;
        font-size: 0.76rem;
        scroll-snap-align: start;
    }
}
</style>
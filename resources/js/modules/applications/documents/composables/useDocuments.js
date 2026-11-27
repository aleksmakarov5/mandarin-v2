import {
    computed,
    onBeforeUnmount,
    ref,
    watch,
} from 'vue';

import axios from 'axios';
import { documentCategories } from '../documentCategories';

const sectionCodes = [
    'unassigned',
    ...documentCategories.map(category => category.code),
];

export function useDocuments(getApplicationId) {
    const itemsByCategory = ref({});
    const metaByCategory = ref({});
    const types = ref([]);
    const counts = ref({});
    const permissions = ref({ manage: false });

    const loading = ref(false);
    const error = ref('');

    const applicationId = computed(() => {
        const id = Number(getApplicationId());

        return Number.isSafeInteger(id) && id > 0
            ? id
            : null;
    });

    let controller = null;
    let requestSequence = 0;

    const resetData = () => {
        itemsByCategory.value = {};
        metaByCategory.value = {};
        types.value = [];
        counts.value = {};
        permissions.value = { manage: false };
        error.value = '';
    };

    const applyResponse = (code, data) => {
        itemsByCategory.value = {
            ...itemsByCategory.value,
            [code]: data.data || [],
        };

        metaByCategory.value = {
            ...metaByCategory.value,
            [code]: data.meta || {
                current_page: 1,
                last_page: 1,
                total: 0,
            },
        };

        if (Array.isArray(data.types)) {
            types.value = data.types;
        }

        if (data.counts) {
            counts.value = data.counts;
        }

        if (data.permissions) {
            permissions.value = data.permissions;
        }
    };

    const loadAll = async () => {
        controller?.abort();

        const currentSequence = ++requestSequence;
        const id = applicationId.value;

        if (!id) {
            resetData();
            loading.value = false;
            return;
        }

        controller = new AbortController();
        loading.value = true;
        error.value = '';

        try {
            const responses = await Promise.all(
                sectionCodes.map(code => {
                    return axios.get(
                        `/api/applications/${id}/documents`,
                        {
                            params: {
                                category: code,
                                page: 1,
                            },
                            signal: controller.signal,
                        }
                    );
                })
            );

            if (currentSequence !== requestSequence) return;

            responses.forEach((response, index) => {
                applyResponse(
                    sectionCodes[index],
                    response.data
                );
            });
        } catch (requestError) {
            if (
                currentSequence !== requestSequence
                || requestError.code === 'ERR_CANCELED'
            ) {
                return;
            }

            error.value =
                requestError.response?.data?.message
                || 'Не удалось загрузить документы.';
        } finally {
            if (currentSequence === requestSequence) {
                loading.value = false;
            }
        }
    };

    const loadSection = async (code, page = 1) => {
        const id = applicationId.value;

        if (!id) return;

        try {
            const response = await axios.get(
                `/api/applications/${id}/documents`,
                {
                    params: {
                        category: code,
                        page,
                    },
                }
            );

            applyResponse(code, response.data);
        } catch (requestError) {
            if (requestError.code === 'ERR_CANCELED') {
                return;
            }

            error.value =
                requestError.response?.data?.message
                || 'Не удалось загрузить документы.';
        }
    };

    watch(
        applicationId,
        () => {
            resetData();
            loadAll();
        },
        { immediate: true }
    );

    onBeforeUnmount(() => {
        requestSequence++;
        controller?.abort();
    });

    return {
        applicationId,
        itemsByCategory,
        metaByCategory,
        types,
        counts,
        permissions,
        loading,
        error,
        loadAll,
        loadSection,
    };
}
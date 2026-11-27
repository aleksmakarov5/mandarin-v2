import {
    computed,
    onBeforeUnmount,
    reactive,
    ref,
    watch,
} from 'vue';

import axios from 'axios';

function checkedUrl(value) {
    if (typeof value !== 'string' || !value) return null;

    const url = new URL(value, window.location.origin);

    if (url.origin !== window.location.origin) {
        throw new Error('Недопустимый адрес изображения.');
    }

    return url.href;
}

function message(error, fallback) {
    return error.response?.data?.message
        || error.message
        || fallback;
}

export function usePhotoGallery(
    getApplicationId,
    getDocumentId,
    getActive
) {
    const photos = ref([]);
    const info = ref(null);

    const loading = ref(false);
    const error = ref('');
    const orderSaving = ref(false);
    const orderError = ref('');

    const selectedIds = ref(new Set());
    const mediaErrors = reactive({});

    let generation = 0;
    let disposed = false;
    let loadController = null;
    let orderController = null;

    const selectedCount = computed(() => selectedIds.value.size);

    const canManage = computed(() => {
        return info.value?.permissions?.manage === true;
    });

    /*
     * Совместимый формат для текущих компонентов галереи.
     * Это только отображение данных ответа, не обработка фото.
     */
    const presentations = computed(() => {
        const result = {};

        for (const photo of photos.value) {
            const id = Number(photo.id);

            result[id] = {
                status: photo.thumbnail_url ? 'ready' : 'failed',
                thumbnail: photo.thumbnail_url,
                screen: photo.screen_url,

                thumbnailError: mediaErrors[id]?.thumbnail || '',
                screenError: mediaErrors[id]?.screen || '',

                error: photo.thumbnail_url
                    ? ''
                    : 'Миниатюра отсутствует. Обработка при просмотре отключена.',
            };
        }

        return result;
    });

    function baseUrl() {
        return `/api/applications/${getApplicationId()}`
            + `/documents/${getDocumentId()}/gallery`;
    }

    function clearMediaErrors() {
        for (const key of Object.keys(mediaErrors)) {
            delete mediaErrors[key];
        }
    }

    async function load() {
        if (orderSaving.value) return;

        loadController?.abort();

        const currentGeneration = ++generation;
        const controller = new AbortController();

        loadController = controller;
        loading.value = true;
        error.value = '';
        orderError.value = '';

        try {
            const response = await axios.get(baseUrl(), {
                signal: controller.signal,
                headers: {
                    Accept: 'application/json',
                },
            });

            if (disposed || currentGeneration !== generation) return;

            const data = response.data?.data;

            if (!data || !Array.isArray(data.photos)) {
                throw new Error(
                    'Некорректный ответ списка фотографий.'
                );
            }

            const items = data.photos.map(photo => ({
                ...photo,
                thumbnail_url: checkedUrl(photo.thumbnail_url),
                screen_url: checkedUrl(photo.screen_url),
            }));

            info.value = data;
            photos.value = items;
            clearMediaErrors();

            const existing = new Set(
                items.map(photo => Number(photo.id))
            );

            selectedIds.value = new Set(
                [...selectedIds.value].filter(id => existing.has(id))
            );
        } catch (requestError) {
            if (
                disposed
                || currentGeneration !== generation
                || controller.signal.aborted
            ) {
                return;
            }

            error.value = message(
                requestError,
                'Не удалось загрузить галерею.'
            );

            if ([401, 403, 404].includes(requestError.response?.status)) {
                photos.value = [];
                info.value = null;
                selectedIds.value = new Set();
                clearMediaErrors();
            }
        } finally {
            if (!disposed && currentGeneration === generation) {
                loading.value = false;
                loadController = null;
            }
        }
    }

    function markMediaError(photoId, kind) {
        if (!['thumbnail', 'screen'].includes(kind)) return;

        const id = Number(photoId);

        mediaErrors[id] = {
            ...mediaErrors[id],
            [kind]: 'Не удалось загрузить изображение. Обновите галерею.',
        };
    }

    function toggleSelection(photoId) {
        const id = Number(photoId);
        const next = new Set(selectedIds.value);

        if (next.has(id)) {
            next.delete(id);
        } else {
            next.add(id);
        }

        selectedIds.value = next;
    }

    function selectAll() {
        selectedIds.value = new Set(
            photos.value.map(photo => Number(photo.id))
        );
    }

    function clearSelection() {
        selectedIds.value = new Set();
    }

    async function reorder(ids) {
        if (
            !canManage.value
            || loading.value
            || orderSaving.value
        ) {
            return;
        }

        const oldPhotos = [...photos.value];
        const byId = new Map(
            oldPhotos.map(photo => [Number(photo.id), photo])
        );

        const nextIds = ids.map(Number);

        if (
            nextIds.length !== oldPhotos.length
            || new Set(nextIds).size !== oldPhotos.length
            || nextIds.some(id => !byId.has(id))
        ) {
            orderError.value = 'Некорректный список фотографий.';
            return;
        }

        if (
            nextIds.every(
                (id, index) => id === Number(oldPhotos[index].id)
            )
        ) {
            return;
        }

        const currentGeneration = generation;
        const controller = new AbortController();

        orderController = controller;
        orderSaving.value = true;
        orderError.value = '';

        photos.value = nextIds.map((id, index) => ({
            ...byId.get(id),
            position: index + 1,
        }));

        let conflict = false;

        try {
            const response = await axios.put(
                `${baseUrl()}/order`,
                {
                    revision: info.value.content_revision,
                    photo_ids: nextIds,
                },
                {
                    signal: controller.signal,
                    headers: {
                        Accept: 'application/json',
                    },
                }
            );

            if (disposed || currentGeneration !== generation) return;

            const changed = response.data.data.changed;

            info.value = {
                ...info.value,
                content_revision: response.data.data.revision,
                archive_status: changed
                    ? 'pending'
                    : info.value.archive_status,
                archive_is_current: changed
                    ? false
                    : info.value.archive_is_current,
            };
        } catch (requestError) {
            if (
                disposed
                || currentGeneration !== generation
                || controller.signal.aborted
            ) {
                return;
            }

            photos.value = oldPhotos;
            conflict = requestError.response?.status === 409;

            orderError.value = message(
                requestError,
                'Не удалось сохранить порядок.'
            );
        } finally {
            if (!disposed && currentGeneration === generation) {
                orderSaving.value = false;
                orderController = null;
            }
        }

        if (conflict) {
            const conflictMessage = orderError.value;

            await load();

            orderError.value = conflictMessage;
        }
    }

    watch(
        () => [getApplicationId(), getDocumentId()],
        () => {
            orderController?.abort();
            orderSaving.value = false;

            photos.value = [];
            info.value = null;
            selectedIds.value = new Set();
            clearMediaErrors();

            load();
        },
        { immediate: true }
    );

    /*
     * Если окно было свёрнуто долго, обновляем ссылки
     * одним запросом при разворачивании.
     *
     * Никакой обработки файлов не запускается.
     */
    watch(
        getActive,
        active => {
            if (!active || loading.value || !info.value) return;

            const expires = Date.parse(
                info.value.links_expires_at || ''
            );

            if (
                !Number.isFinite(expires)
                || Date.now() >= expires - 60000
            ) {
                load();
            }
        }
    );

    onBeforeUnmount(() => {
        disposed = true;
        generation += 1;

        loadController?.abort();
        orderController?.abort();
    });

    return {
        photos,
        info,
        presentations,
        loading,
        error,
        orderSaving,
        orderError,
        selectedIds,
        selectedCount,
        canManage,
        load,
        markMediaError,
        toggleSelection,
        selectAll,
        clearSelection,
        reorder,
    };
}
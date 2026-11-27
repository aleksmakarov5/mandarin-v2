import {
    inject,
    onBeforeUnmount,
    provide,
    readonly,
    ref,
    watch,
} from 'vue';

const DOCUMENT_VIEWER_KEY = Symbol('application-document-viewer');

/*
 * Вызывается один раз в ApplicationShow.vue.
 * applicationId — существующий ref/computed ID дела.
 *
 * Состояние принадлежит конкретной карточке,
 * а не всему приложению.
 */
export function provideDocumentViewer(applicationId) {
    const document = ref(null);
    const minimized = ref(false);

    function open(item) {
        const id = Number(applicationId.value);

        if (!Number.isSafeInteger(id) || id <= 0 || !item?.id) {
            return;
        }

        document.value = {
            ...item,
            application_id: id,
        };

        minimized.value = false;
    }

    function minimize() {
        if (!document.value) return;

        minimized.value = true;
    }

    function restore() {
        if (!document.value) return;

        minimized.value = false;
    }

    function close() {
        document.value = null;
        minimized.value = false;
    }

    /*
     * Между вкладками одного дела состояние сохраняется.
     * При смене дела — закрывается немедленно.
     */
    watch(
        () => applicationId.value,
        close,
        { flush: 'sync' }
    );

    onBeforeUnmount(close);

    const viewer = {
        document: readonly(document),
        minimized: readonly(minimized),
        open,
        minimize,
        restore,
        close,
    };

    provide(DOCUMENT_VIEWER_KEY, viewer);

    return viewer;
}

export function useDocumentViewer() {
    const viewer = inject(DOCUMENT_VIEWER_KEY, null);

    if (!viewer) {
        throw new Error(
            'Просмотрщик документов должен находиться '
            + 'внутри карточки с provideDocumentViewer().'
        );
    }

    return viewer;
}
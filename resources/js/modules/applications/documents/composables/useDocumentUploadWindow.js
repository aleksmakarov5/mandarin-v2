import {
    inject,
    provide,
    shallowRef,
} from 'vue';

const DOCUMENT_UPLOAD_WINDOW_KEY = Symbol(
    'application-document-upload-window'
);

export function provideDocumentUploadWindow() {
    /*
     * Ссылка на единственный загрузчик карточки дела.
     * Сами файлы и очередь остаются внутри загрузчика.
     */
    const uploadModalRef = shallowRef(null);

    const lastUploadedBatch = shallowRef(null);

    function notifySourcesUploaded(payload) {
        lastUploadedBatch.value = {
            applicationId: Number(payload.applicationId),
            batchId: Number(payload.batchId),
        };
    }

    const uploadWindow = {
        uploadModalRef,
        lastUploadedBatch,
        notifySourcesUploaded,
    };

    provide(
        DOCUMENT_UPLOAD_WINDOW_KEY,
        uploadWindow
    );

    return uploadWindow;
}

export function useDocumentUploadWindow() {
    const uploadWindow = inject(
        DOCUMENT_UPLOAD_WINDOW_KEY,
        null
    );

    if (!uploadWindow) {
        throw new Error(
            'Загрузчик должен находиться внутри карточки '
            + 'с provideDocumentUploadWindow().'
        );
    }

    return uploadWindow;
}
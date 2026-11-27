import {
    computed,
    onBeforeUnmount,
    ref,
} from 'vue';

import axios from 'axios';

import {
    uploadDocumentFile,
    completeDocumentUpload,
    getDocumentUploadState,
} from '../services/uploadDocumentFile';

/*
 * crypto.randomUUID() может быть недоступен на смартфоне,
 * открывшем тестовый сервер по обычному HTTP.
 *
 * getRandomValues используем как резервный вариант.
 * Math.random для идентификаторов пакета не используем.
 */
function createUuid() {
    if (globalThis.crypto?.randomUUID) {
        return globalThis.crypto.randomUUID();
    }

    if (!globalThis.crypto?.getRandomValues) {
        throw new Error(
            'Браузер не поддерживает создание идентификатора загрузки.'
        );
    }

    const bytes = new Uint8Array(16);

    globalThis.crypto.getRandomValues(bytes);

    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;

    const hex = [...bytes].map(value => {
        return value.toString(16).padStart(2, '0');
    });

    return [
        hex.slice(0, 4).join(''),
        hex.slice(4, 6).join(''),
        hex.slice(6, 8).join(''),
        hex.slice(8, 10).join(''),
        hex.slice(10, 16).join(''),
    ].join('-');
}

function cancelledError() {
    const error = new Error('Загрузка остановлена.');
    error.code = 'ERR_CANCELED';

    return error;
}

function wait(milliseconds, signal) {
    return new Promise((resolve, reject) => {
        if (signal.aborted) {
            reject(cancelledError());
            return;
        }

        const timer = window.setTimeout(() => {
            signal.removeEventListener('abort', onAbort);
            resolve();
        }, milliseconds);

        function onAbort() {
            window.clearTimeout(timer);
            signal.removeEventListener('abort', onAbort);
            reject(cancelledError());
        }

        signal.addEventListener('abort', onAbort, {
            once: true,
        });
    });
}

function errorText(error) {
    const errors = error.response?.data?.errors;

    const validationMessage = errors
        ? Object.values(errors)
            .flat()
            .find(value => typeof value === 'string')
        : null;

    return validationMessage
        || error.response?.data?.message
        || error.message
        || 'Не удалось загрузить файл.';
}

export function useDocumentUpload() {
    const rows = ref([]);
    const batch = ref(null);

    const running = ref(false);
    const registering = ref(false);
    const error = ref('');

    let manifest = null;
    let applicationId = null;
    let controller = null;
    let generation = 0;
    const prepared = computed(() => rows.value.length > 0);

    const totalBytes = computed(() => {
        return rows.value.reduce(
            (sum, row) => sum + row.size,
            0
        );
    });

    const sentBytes = computed(() => {
        return rows.value.reduce(
            (sum, row) => sum + row.sentBytes,
            0
        );
    });

    const percent = computed(() => {
        if (!totalBytes.value) return 0;

        return Math.min(
            100,
            Math.floor(
                sentBytes.value / totalBytes.value * 100
            )
        );
    });

    const acceptedCount = computed(() => {
        return rows.value.filter(
            row => row.status === 'uploaded'
        ).length;
    });

    const failedCount = computed(() => {
        return rows.value.filter(
            row => row.status === 'failed'
        ).length;
    });

    const allAccepted = computed(() => {
        return rows.value.length > 0
            && acceptedCount.value === rows.value.length;
    });

    /*
     * Контекст и состав файлов фиксируются до первого запроса.
     * Повтор регистрации отправляет тот же manifest и UUID.
     */
    function prepare(files, context) {
        if (running.value || manifest) {
            throw new Error(
                'Сначала завершите или сбросьте текущий пакет.'
            );
        }

        const id = Number(context.applicationId);

        if (!Number.isSafeInteger(id) || id <= 0) {
            throw new Error('Не определён номер дела.');
        }

        const sourceFiles = Array.from(files);

        if (!sourceFiles.length) {
            throw new Error('Выберите файлы.');
        }

        if (sourceFiles.some(file => file.size <= 0)) {
            throw new Error(
                'В списке есть пустой файл. Уберите его из выбора.'
            );
        }

        const newRows = sourceFiles.map(file => ({
            file,
            key: createUuid(),
            name: file.name,
            size: file.size,

            item: null,
            status: 'pending',

            sentBytes: 0,
            confirmedBytes: 0,
            error: '',
        }));

        const request = {
            placement: context.placement,
            client_batch_key: createUuid(),

            files: newRows.map(row => ({
                client_file_key: row.key,
                name: row.name,
                size: row.size,
                mime_type: row.file.type || null,
            })),
        };

        if (context.placement === 'typed') {
            const typeId = Number(context.documentTypeId);

            if (!Number.isSafeInteger(typeId) || typeId <= 0) {
                throw new Error('Выберите тип документа.');
            }

            request.document_type_id = typeId;
            request.title = String(context.title || '').trim() || null;
            request.document_date = context.documentDate || null;

            if (context.documentId) {
                request.document_id = Number(context.documentId);
            }
        } else if (context.placement !== 'unassigned') {
            throw new Error('Не определено назначение загрузки.');
        }

        applicationId = id;
        rows.value = newRows;
        manifest = request;
        error.value = '';
    }

    async function register(signal, currentGeneration) {
        registering.value = true;

        try {
            const response = await axios.post(
                `/api/applications/${applicationId}`
                + '/document-upload-batches',
                manifest,
                {
                    signal,
                    headers: {
                        Accept: 'application/json',
                    },
                }
            );

            if (currentGeneration !== generation) return;

            const data = response.data?.data;

            if (!data?.id || !Array.isArray(data.items)) {
                throw new Error(
                    'Сервер не вернул зарегистрированный пакет.'
                );
            }

            const items = new Map(
                data.items.map(item => [
                    item.client_file_key,
                    item,
                ])
            );

            for (const row of rows.value) {
                if (!items.has(row.key)) {
                    throw new Error(
                        'Сервер вернул неполный состав пакета.'
                    );
                }
            }

            batch.value = data;

            for (const row of rows.value) {
                row.item = items.get(row.key);
            }
        } finally {
            if (currentGeneration === generation) {
                registering.value = false;
            }
        }
    }

    async function processRow(row, signal, currentGeneration) {
        const parameters = {
            applicationId,
            batchId: batch.value.id,
            itemId: row.item.id,
            signal,
        };

        const isCurrent = () => {
            return currentGeneration === generation
                && !signal.aborted;
        };

        row.error = '';

        try {
            let state = await getDocumentUploadState(parameters);

            if (!isCurrent()) return;

            if (
                !['uploaded', 'processing', 'completed', 'assembling']
                    .includes(state.status)
            ) {
                if (!state.all_chunks_received) {
                    row.status = 'uploading';

                    await uploadDocumentFile({
                        applicationId,
                        batchId: batch.value.id,
                        item: row.item,
                        file: row.file,
                        signal,

                        onProgress(progress) {
                            if (!isCurrent()) return;

                            row.sentBytes = progress.sentBytes;
                            row.confirmedBytes =
                                progress.confirmedBytes;
                        },
                    });
                }

                if (!isCurrent()) return;

                row.status = 'assembling';

                /*
                 * Повтор завершения для failed с уже принятыми
                 * частями не требует повторной передачи файла.
                 */
                await completeDocumentUpload(parameters);

                state = await getDocumentUploadState(parameters);
            }

            while (isCurrent()) {
                if (
                    ['uploaded', 'processing', 'completed']
                        .includes(state.status)
                ) {
                    row.status = 'uploaded';
                    row.sentBytes = row.size;
                    row.confirmedBytes = row.size;

                    return;
                }

                if (state.status === 'failed') {
                    throw new Error(
                        state.error || 'Не удалось собрать исходник.'
                    );
                }

                if (state.status !== 'assembling') {
                    throw new Error(
                        'Неожиданное состояние файла. '
                        + 'Повторите завершение загрузки.'
                    );
                }

                row.status = 'assembling';

                await wait(3000, signal);

                state = await getDocumentUploadState(parameters);
            }
        } catch (uploadError) {
            if (currentGeneration !== generation) return;

            if (
                signal.aborted
                || uploadError.code === 'ERR_CANCELED'
            ) {
                row.status = 'paused';
                return;
            }

            row.status = 'failed';
            row.error = errorText(uploadError);
        }
    }

    async function start() {
        if (running.value || !manifest || allAccepted.value) {
            return;
        }

        const currentGeneration = generation;
        const requestController = new AbortController();

        controller = requestController;

        const signal = requestController.signal;

        running.value = true;
        error.value = '';

        try {
            if (!batch.value) {
                await register(signal, currentGeneration);
            }

            if (
                signal.aborted
                || currentGeneration !== generation
            ) {
                return;
            }

            const pending = rows.value.filter(
                row => row.status !== 'uploaded'
            );

            let cursor = 0;

            /*
             * Два файла в работе одновременно.
             * Части внутри одного файла передаются последовательно.
             */
            async function worker() {
                while (
                    !signal.aborted
                    && currentGeneration === generation
                ) {
                    const index = cursor++;

                    if (index >= pending.length) return;

                    await processRow(
                        pending[index],
                        signal,
                        currentGeneration
                    );
                }
            }

            const parallel = Math.max(
                1,
                Math.min(
                    2,
                    Number(batch.value.parallel_files) || 2
                )
            );

            await Promise.all(
                Array.from(
                    { length: parallel },
                    () => worker()
                )
            );
        } catch (uploadError) {
            if (
                currentGeneration === generation
                && !signal.aborted
                && uploadError.code !== 'ERR_CANCELED'
            ) {
                error.value = errorText(uploadError);
            }
        } finally {
            if (currentGeneration === generation) {
                running.value = false;
                registering.value = false;
                controller = null;
            }
        }
    }

    function pause() {
        controller?.abort();
    }

    function reset() {
        generation += 1;

        controller?.abort();
        controller = null;

        rows.value = [];
        batch.value = null;

        manifest = null;
        applicationId = null;

        running.value = false;
        registering.value = false;
        error.value = '';
    }

    onBeforeUnmount(reset);

    return {
        rows,
        batch,
        prepared,

        running,
        registering,
        error,

        totalBytes,
        sentBytes,
        percent,
        acceptedCount,
        failedCount,
        allAccepted,

        prepare,
        start,
        pause,
        reset,
    };
}
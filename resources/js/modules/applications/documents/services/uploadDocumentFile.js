import axios from 'axios';

function abortError() {
    const error = new Error('Загрузка отменена.');
    error.code = 'ERR_CANCELED';

    return error;
}

function checkAbort(signal) {
    if (signal?.aborted) {
        throw abortError();
    }
}

function wait(milliseconds, signal) {
    return new Promise((resolve, reject) => {
        if (signal?.aborted) {
            reject(abortError());
            return;
        }

        const timeout = window.setTimeout(() => {
            signal?.removeEventListener('abort', onAbort);
            resolve();
        }, milliseconds);

        function onAbort() {
            window.clearTimeout(timeout);
            signal?.removeEventListener('abort', onAbort);
            reject(abortError());
        }

        signal?.addEventListener('abort', onAbort, {
            once: true,
        });
    });
}

function canRetry(error) {
    if (error.code === 'ERR_CANCELED') {
        return false;
    }

    const status = error.response?.status;

    // Сетевой сбой, таймаут или временная ошибка сервера.
    return !status
        || [408, 429, 500, 502, 503, 504].includes(status);
}

async function sendChunk({
    url,
    blob,
    chunkIndex,
    signal,
    onProgress,
}) {
    const attempts = 3;

    for (let attempt = 0; attempt < attempts; attempt++) {
        checkAbort(signal);

        const body = new FormData();

        body.append(
            'chunk',
            blob,
            `chunk-${chunkIndex}.part`
        );

        try {
            await axios.post(url, body, {
                signal,

                /*
                 * Content-Type вручную не устанавливаем:
                 * браузер добавит multipart boundary.
                 */
                headers: {
                    Accept: 'application/json',
                },

                onUploadProgress(event) {
                    onProgress?.(
                        Math.min(
                            Number(event.loaded) || 0,
                            blob.size
                        )
                    );
                },
            });

            return;
        } catch (error) {
            checkAbort(signal);

            if (
                attempt === attempts - 1
                || !canRetry(error)
            ) {
                throw error;
            }

            // Следующая попытка начинается с нуля
            // для текущей части, не для целого файла.
            onProgress?.(0);

            await wait(
                1000 * (attempt + 1),
                signal
            );
        }
    }
}

/**
 * Передаёт исходный файл по частям.
 *
 * Возвращает состояние принятых частей.
 * Сборка файла и создание документа выполняются отдельно.
 */
export async function uploadDocumentFile({
    applicationId,
    batchId,
    item,
    file,
    signal,
    onProgress,
}) {
    checkAbort(signal);

    if (!(file instanceof File)) {
        throw new Error('Не выбран исходный файл.');
    }

    if (file.size !== Number(item.file_size)) {
        throw new Error(
            'Размер выбранного файла отличается '
            + 'от зарегистрированного в пакете.'
        );
    }

    if (file.name !== item.original_name) {
        throw new Error(
            'Имя выбранного файла отличается '
            + 'от зарегистрированного в пакете.'
        );
    }

    const baseUrl =
        `/api/applications/${applicationId}`
        + `/document-upload-batches/${batchId}`
        + `/items/${item.id}`;

    const response = await axios.get(
        `${baseUrl}/upload-state`,
        {
            signal,
            headers: {
                Accept: 'application/json',
            },
        }
    );

    const state = response.data?.data;
    const chunkSize = Number(state?.chunk_size_bytes);
    const chunksCount = Number(state?.chunks_count);

    if (
        !state
        || !Number.isSafeInteger(chunkSize)
        || chunkSize <= 0
        || !Number.isSafeInteger(chunksCount)
        || chunksCount <= 0
    ) {
        throw new Error(
            'Сервер вернул некорректные параметры загрузки.'
        );
    }

    const received = new Set(
        (state.received_chunks || []).map(chunk => {
            return Number(chunk.index);
        })
    );

    let confirmedBytes = Number(state.received_bytes) || 0;

    onProgress?.({
        confirmedBytes,
        sentBytes: confirmedBytes,
        totalBytes: file.size,
    });

    for (let index = 0; index < chunksCount; index++) {
        checkAbort(signal);

        if (received.has(index)) {
            continue;
        }

        const start = index * chunkSize;
        const end = Math.min(start + chunkSize, file.size);
        const blob = file.slice(start, end);

        await sendChunk({
            url: `${baseUrl}/chunks/${index}`,
            blob,
            chunkIndex: index,
            signal,

            onProgress(currentBytes) {
                onProgress?.({
                    confirmedBytes,
                    sentBytes: Math.min(
                        confirmedBytes + currentBytes,
                        file.size
                    ),
                    totalBytes: file.size,
                });
            },
        });

        confirmedBytes += blob.size;

        onProgress?.({
            confirmedBytes,
            sentBytes: confirmedBytes,
            totalBytes: file.size,
        });
    }

    checkAbort(signal);

    const finalResponse = await axios.get(
        `${baseUrl}/upload-state`,
        {
            signal,
            headers: {
                Accept: 'application/json',
            },
        }
    );

    const finalState = finalResponse.data?.data;

    if (!finalState?.all_chunks_received) {
        throw new Error(
            'Не все части подтверждены сервером. '
            + 'Повторите загрузку этого файла.'
        );
    }

    return finalState;
}

/**
 * Просит сервер собрать все принятые части.
 *
 * 202 означает постановку в очередь,
 * а не готовность документа.
 */
export async function completeDocumentUpload({
    applicationId,
    batchId,
    itemId,
    signal,
}) {
    const response = await axios.post(
        `/api/applications/${applicationId}`
        + `/document-upload-batches/${batchId}`
        + `/items/${itemId}/complete`,
        {},
        {
            signal,
            headers: {
                Accept: 'application/json',
            },
        }
    );

    return response.data.data;
}

/**
 * Получает текущее состояние файла.
 * Используется для опроса после завершения передачи.
 */
export async function getDocumentUploadState({
    applicationId,
    batchId,
    itemId,
    signal,
}) {
    const response = await axios.get(
        `/api/applications/${applicationId}`
        + `/document-upload-batches/${batchId}`
        + `/items/${itemId}/upload-state`,
        {
            signal,
            headers: {
                Accept: 'application/json',
            },
        }
    );

    return response.data.data;
}
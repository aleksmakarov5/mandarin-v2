import axios from 'axios';

function pause(milliseconds, signal) {
    return new Promise((resolve, reject) => {
        if (signal?.aborted) {
            reject(new DOMException('Отменено', 'AbortError'));
            return;
        }

        const onAbort = () => {
            window.clearTimeout(timer);
            reject(new DOMException('Отменено', 'AbortError'));
        };

        const timer = window.setTimeout(() => {
            signal?.removeEventListener('abort', onAbort);
            resolve();
        }, milliseconds);

        signal?.addEventListener('abort', onAbort, { once: true });
    });
}

export async function downloadDocument({
    applicationId,
    documentId,
    signal,
    onPreparing,
}) {
    /*
     * Первый запрос разрешает повтор после прошлой ошибки.
     * Последующие только ожидают результат.
     */
    let firstRequest = true;

    for (let attempt = 0; attempt < 600; attempt += 1) {
        const response = await axios.post(
            `/api/applications/${applicationId}`
            + `/documents/${documentId}/download`,
            { retry: firstRequest },
            {
                signal,
                headers: { Accept: 'application/json' },
            }
        );

        firstRequest = false;

        const data = response.data?.data;

        if (data?.status === 'ready') {
            const url = new URL(
                data.url,
                window.location.origin
            );

            if (url.origin !== window.location.origin) {
                throw new Error('Недопустимый адрес скачивания.');
            }

            const link = document.createElement('a');

            link.href = url.href;
            link.referrerPolicy = 'no-referrer';
            link.hidden = true;

            document.body.appendChild(link);
            link.click();
            link.remove();

            return;
        }

        if (data?.status !== 'processing') {
            throw new Error('Некорректный ответ скачивания.');
        }

        onPreparing?.();

        await pause(3000, signal);
    }

    throw new Error(
        'Сборка занимает больше времени. '
        + 'Попробуйте скачать архив позднее.'
    );
}
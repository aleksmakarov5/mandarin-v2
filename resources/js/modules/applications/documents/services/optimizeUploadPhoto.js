const rasterExtensions = [
    'jpg', 'jpeg', 'png', 'webp',
    'heic', 'heif', 'avif', 'tif', 'tiff',
];

function extensionOf(name) {
    return String(name).split('.').pop()?.toLowerCase() || '';
}

function jpegName(name) {
    const base = String(name).replace(/\.[^.]+$/, '');

    return `${base || 'photo'}.jpg`;
}

async function loadImage(file) {
    /*
     * Современные браузеры учитывают EXIF-ориентацию
     * при декодировании изображения.
     */
    if (typeof createImageBitmap === 'function') {
        try {
            const bitmap = await createImageBitmap(file, {
                imageOrientation: 'from-image',
            });

            return {
                source: bitmap,
                width: bitmap.width,
                height: bitmap.height,
                dispose: () => bitmap.close(),
            };
        } catch {
            // Попробуем штатный декодер <img>.
        }
    }

    const url = URL.createObjectURL(file);
    const image = new Image();

    try {
        await new Promise((resolve, reject) => {
            image.onload = resolve;
            image.onerror = () => reject(
                new Error('Браузер не смог прочитать изображение.')
            );

            image.src = url;
        });

        return {
            source: image,
            width: image.naturalWidth,
            height: image.naturalHeight,
            dispose() {
                image.src = '';
                URL.revokeObjectURL(url);
            },
        };
    } catch (error) {
        URL.revokeObjectURL(url);
        throw error;
    }
}

/**
 * Документы и архивы возвращаются без изменений.
 *
 * Если браузер не поддерживает HEIC/TIFF или другой формат,
 * отправляем исходник: окончательное преобразование
 * обязательно выполняется сервером.
 */
export async function optimizeUploadPhoto(file) {
    if (!rasterExtensions.includes(extensionOf(file.name))) {
        return file;
    }

    let decoded = null;
    let canvas = null;

    try {
        decoded = await loadImage(file);

        const { width, height } = decoded;

        if (
            width <= 0
            || height <= 0
            || width * height > 80000000
        ) {
            // Лимит и допустимость дополнительно проверит сервер.
            return file;
        }

        const ratio = Math.min(
            1,
            2560 / Math.max(width, height)
        );

        canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(width * ratio));
        canvas.height = Math.max(1, Math.round(height * ratio));

        const context = canvas.getContext('2d', {
            alpha: false,
        });

        if (!context) return file;

        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, canvas.width, canvas.height);

        context.drawImage(
            decoded.source,
            0,
            0,
            canvas.width,
            canvas.height
        );

        const blob = await new Promise(resolve => {
            canvas.toBlob(resolve, 'image/jpeg', 0.82);
        });

        if (!blob || blob.type !== 'image/jpeg') {
            return file;
        }

        return new File(
            [blob],
            jpegName(file.name),
            {
                type: 'image/jpeg',
                lastModified: file.lastModified,
            }
        );
    } catch {
        /*
         * Не запрещаем загрузку только из-за отсутствия
         * декодера в браузере. Проверка и преобразование —
         * на сервере.
         */
        return file;
    } finally {
        decoded?.dispose();

        if (canvas) {
            canvas.width = 0;
            canvas.height = 0;
        }
    }
}
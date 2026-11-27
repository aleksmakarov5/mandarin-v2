<?php

namespace App\Services\Documents;

use App\Models\Document;
use App\Models\DocumentFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class PhotoPresentationService
{
    /*
     * Меняем при изменении правил обработки.
     * Старые представления не будут приняты за новые.
     */
    public const VERSION = 'v1';

    public function paths(DocumentFile $file): array
    {
        $document = Document::findOrFail($file->document_id);

        $hash = strtolower((string) $file->sha256);

        if (!preg_match('/^[a-f0-9]{64}$/D', $hash)) {
            throw new RuntimeException(
                'У исходника отсутствует корректный SHA-256.'
            );
        }

        $base = sprintf(
            'applications/%d/documents/photo-presentations/%d/%s/%s',
            $document->application_id,
            $file->id,
            self::VERSION,
            $hash
        );

        return [
            'thumbnail' => $base . '/thumbnail.jpg',
            'screen' => $base . '/screen.jpg',
        ];
    }

    public function ready(DocumentFile $file): bool
    {
        $paths = $this->paths($file);
        $disk = Storage::disk($file->storage_disk);

        return $disk->exists($paths['thumbnail'])
            && $disk->exists($paths['screen']);
    }

    public function cacheKey(DocumentFile $file): string
    {
        return sprintf(
            'document-photo:%s:%d:%s',
            self::VERSION,
            $file->id,
            $file->sha256
        );
    }

    public function build(DocumentFile $file): void
    {
        if ($this->ready($file)) {
            return;
        }

        if (!extension_loaded('imagick')) {
            throw new RuntimeException(
                'На сервере не подключено расширение Imagick.'
            );
        }

        if (
            $file->file_role !== 'original'
            || !$file->is_current
            || $file->processing_status !== 'ready'
        ) {
            throw new RuntimeException(
                'Исходник фотографии недоступен для обработки.'
            );
        }

        /*
         * Не обрабатываем SVG, PDF или произвольный файл
         * только потому, что имя заканчивается на .jpg.
         */
        $allowedMimes = [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/heic',
            'image/heif',
            'image/avif',
            'image/tiff',
        ];

        if (!in_array($file->mime_type, $allowedMimes, true)) {
            throw new RuntimeException(
                'Этот формат не поддерживается обработчиком фотографий.'
            );
        }

        $disk = Storage::disk($file->storage_disk);
        $paths = $this->paths($file);

        /*
         * Рабочий файл на диске, не весь исходник в PHP-строке.
         * Подходит и для удалённого хранилища.
         */
        $temporary = tmpfile();

        if ($temporary === false) {
            throw new RuntimeException(
                'Не удалось создать рабочий файл.'
            );
        }

        $image = null;

        try {
            $source = $disk->readStream($file->storage_path);

            if (!is_resource($source)) {
                throw new RuntimeException(
                    'Не удалось открыть исходник фотографии.'
                );
            }

            try {
                $copied = stream_copy_to_stream(
                    $source,
                    $temporary
                );
            } finally {
                fclose($source);
            }

            if (
                $copied === false
                || $copied !== (int) $file->file_size
            ) {
                throw new RuntimeException(
                    'Размер исходника не совпадает с данными файла.'
                );
            }
            /*
             * Делаем записанные байты доступными для чтения
             * через отдельный файловый дескриптор Imagick.
             */
            if (!fflush($temporary)) {
                throw new \RuntimeException(
                    'Не удалось сбросить буфер рабочего файла фотографии.'
                );
            }
            $metadata = stream_get_meta_data($temporary);
            $localPath = $metadata['uri'];

            $actualHash = hash_file('sha256', $localPath);

            if (
                !is_string($actualHash)
                || !hash_equals(
                    strtolower((string) $file->sha256),
                    $actualHash
                )
            ) {
                throw new RuntimeException(
                    'Контрольная сумма исходника не совпадает.'
                );
            }

            /*
             * Обрабатываем первое изображение:
             * многостраничный TIFF не разворачиваем
             * автоматически в отдельные фотографии.
             */
            $probe = new \Imagick();

            try {
                $probe->pingImage($localPath . '[0]');

                $width = $probe->getImageWidth();
                $height = $probe->getImageHeight();
            } finally {
                $probe->clear();
                $probe->destroy();
            }

            /*
             * Защита от изображений с чрезмерными размерами.
             * Значение можно вынести в config/documents.php.
             */
            $maxPixels = (int) config(
                'documents.photos.max_pixels',
                80000000
            );

            if (
                $width <= 0
                || $height <= 0
                || $maxPixels <= 0
                || $width > intdiv($maxPixels, $height)
            ) {
                throw new RuntimeException(
                    'Размер изображения превышает лимит обработки.'
                );
            }

            $image = new \Imagick();

            /*
             * Подсказка JPEG-декодеру: для галереи не требуется
             * декодировать исходник в полном разрешении.
             *
             * Это оптимизация чтения, а не изменение исходного файла.
             * Для остальных форматов эта подсказка не применяется.
             */
            if ($file->mime_type === 'image/jpeg') {
                $image->setOption('jpeg:size', '2048x2048');
            }

            $image->readImage($localPath . '[0]');

            /*
             * Учитываем EXIF-ориентацию смартфона.
             */
            // Применяем ориентацию EXIF к пикселям изображения.
            $image->autoOrient();

            // После поворота отмечаем изображение как ориентированное нормально.
            $image->setImageOrientation(
                \Imagick::ORIENTATION_TOPLEFT
            );
            /*
             * Максимальное представление галереи — 2048 px.
             * Дальнейшие преобразования выполняем уже
             * на уменьшенном рабочем изображении.
             *
             * Маленькие фотографии не увеличиваем.
             */
            if (
                $image->getImageWidth() > 2048
                || $image->getImageHeight() > 2048
            ) {
                $image->thumbnailImage(
                    2048,
                    2048,
                    true
                );
            }
            if (
                $image->getImageColorspace()
                !== \Imagick::COLORSPACE_SRGB
            ) {
                $image->transformImageColorspace(
                    \Imagick::COLORSPACE_SRGB
                );
            }

            /*
             * Прозрачные области PNG/WebP переводим
             * в белую подложку для JPEG-представления.
             */
            $image->setImageBackgroundColor(
                new \ImagickPixel('white')
            );

            $flattened = $image->mergeImageLayers(
                \Imagick::LAYERMETHOD_FLATTEN
            );

            $image->clear();
            $image->destroy();
            $image = $flattened;

            $image->stripImage();

            foreach ([
                'thumbnail' => [480, 78],
                'screen' => [2048, 85],
            ] as $kind => [$limit, $quality]) {
                $output = clone $image;

                try {
                    /*
                     * Маленькие исходники не увеличиваем.
                     */
                    if (
                        $output->getImageWidth() > $limit
                        || $output->getImageHeight() > $limit
                    ) {
                        $output->thumbnailImage(
                            $limit,
                            $limit,
                            true
                        );
                    }

                    $output->setImageFormat('jpeg');
                    $output->setImageCompression(
                        \Imagick::COMPRESSION_JPEG
                    );
                    $output->setImageCompressionQuality($quality);
                    $output->setInterlaceScheme(
                        \Imagick::INTERLACE_PLANE
                    );

                    $stored = $disk->put(
                        $paths[$kind],
                        $output->getImageBlob(),
                        ['visibility' => 'private']
                    );

                    if (!$stored) {
                        throw new RuntimeException(
                            'Не удалось сохранить представление фотографии.'
                        );
                    }
                } finally {
                    $output->clear();
                    $output->destroy();
                }
            }
        } finally {
            if ($image instanceof \Imagick) {
                $image->clear();
                $image->destroy();
            }

            fclose($temporary);
        }
    }
}
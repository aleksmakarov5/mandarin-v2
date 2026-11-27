<?php

namespace App\Services\Documents;

use RuntimeException;
use Throwable;

class PhotoNormalizationService
{
    /**
     * Возвращает:
     *
     * [
     *     'main' => [
     *         'stream' => resource,
     *         'size' => int,
     *         'sha256' => string,
     *         'width' => int,
     *         'height' => int,
     *     ],
     *     'thumbnail' => [...],
     * ]
     *
     * Вызывающий код обязан закрыть оба потока.
     */
    public function normalize(
        string $sourcePath,
        string $mime
    ): array {
        if (!extension_loaded('imagick')) {
            throw new RuntimeException(
                'Для обработки фотографий требуется Imagick.'
            );
        }

        $maxSide = (int) config(
            'documents.photos.optimized_max_side',
            2560
        );

        $thumbnailSide = (int) config(
            'documents.photos.preview_max_side',
            480
        );

        $quality = (int) config(
            'documents.photos.jpeg_quality',
            82
        );

        $thumbnailQuality = (int) config(
            'documents.photos.preview_jpeg_quality',
            75
        );

        $maxPixels = (int) config(
            'documents.photos.max_pixels',
            80000000
        );

        if (
            $maxSide <= 0
            || $thumbnailSide <= 0
            || $thumbnailSide > $maxSide
            || $quality < 1
            || $quality > 100
            || $thumbnailQuality < 1
            || $thumbnailQuality > 100
            || $maxPixels <= 0
        ) {
            throw new RuntimeException(
                'Некорректные настройки обработки фотографий.'
            );
        }

        /*
         * Сначала читаем заголовки.
         * Полное декодирование исходника выполняется ниже.
         */
        $probe = new \Imagick();

        try {
            $probe->pingImage($sourcePath . '[0]');

            $width = $probe->getImageWidth();
            $height = $probe->getImageHeight();

            if (
                $width <= 0
                || $height <= 0
                || $width > intdiv($maxPixels, $height)
            ) {
                throw new RuntimeException(
                    'Изображение превышает допустимые размеры.'
                );
            }
        } finally {
            $probe->clear();
            $probe->destroy();
        }

        $image = new \Imagick();
        $thumbnailImage = null;

        $main = null;
        $thumbnail = null;

        try {
            if ($mime === 'image/jpeg') {
                $image->setOption(
                    'jpeg:size',
                    "{$maxSide}x{$maxSide}"
                );
            }

            /*
             * Декодируем только первый кадр.
             * Миниатюру получаем из этого же изображения,
             * не открывая исходник повторно.
             */
            $image->readImage($sourcePath . '[0]');

            $image->autoOrient();
            $image->setImageOrientation(
                \Imagick::ORIENTATION_TOPLEFT
            );

            if (
                $image->getImageColorspace()
                !== \Imagick::COLORSPACE_SRGB
            ) {
                $image->transformImageColorspace(
                    \Imagick::COLORSPACE_SRGB
                );
            }

            if (
                $image->getImageWidth() > $maxSide
                || $image->getImageHeight() > $maxSide
            ) {
                $image->thumbnailImage(
                    $maxSide,
                    $maxSide,
                    true
                );
            }

            /*
             * JPEG не поддерживает прозрачность.
             * Прозрачные области делаем белыми.
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
            $image->setImagePage(0, 0, 0, 0);

            /*
             * Клонируем изображение в памяти ДО JPEG-кодирования.
             * Миниатюра не создаётся из повторно прочитанного JPEG.
             */
            $thumbnailImage = clone $image;

            if (
                $thumbnailImage->getImageWidth() > $thumbnailSide
                || $thumbnailImage->getImageHeight() > $thumbnailSide
            ) {
                $thumbnailImage->thumbnailImage(
                    $thumbnailSide,
                    $thumbnailSide,
                    true
                );
            }

            $main = $this->encodeJpeg(
                $image,
                $quality
            );

            $thumbnail = $this->encodeJpeg(
                $thumbnailImage,
                $thumbnailQuality
            );

            return [
                'main' => $main,
                'thumbnail' => $thumbnail,
            ];
        } catch (Throwable $error) {
            foreach ([$main, $thumbnail] as $result) {
                if (
                    is_array($result)
                    && is_resource($result['stream'] ?? null)
                ) {
                    fclose($result['stream']);
                }
            }

            throw $error;
        } finally {
            $image->clear();
            $image->destroy();

            if ($thumbnailImage instanceof \Imagick) {
                $thumbnailImage->clear();
                $thumbnailImage->destroy();
            }
        }
    }

    private function encodeJpeg(
        \Imagick $image,
        int $quality
    ): array {
        $image->setImageFormat('jpeg');
        $image->setImageCompression(
            \Imagick::COMPRESSION_JPEG
        );
        $image->setImageCompressionQuality($quality);
        $image->setInterlaceScheme(
            \Imagick::INTERLACE_PLANE
        );
        $image->setImagePage(0, 0, 0, 0);

        $stream = tmpfile();

        if ($stream === false) {
            throw new RuntimeException(
                'Не удалось создать рабочий JPEG.'
            );
        }

        try {
            if (!$image->writeImageFile($stream)) {
                throw new RuntimeException(
                    'Не удалось записать JPEG.'
                );
            }

            if (!fflush($stream)) {
                throw new RuntimeException(
                    'Не удалось сбросить буфер JPEG.'
                );
            }

            $metadata = stream_get_meta_data($stream);
            $stat = fstat($stream);

            $size = (int) ($stat['size'] ?? 0);
            $sha256 = hash_file(
                'sha256',
                $metadata['uri']
            );

            if ($size <= 0 || $sha256 === false) {
                throw new RuntimeException(
                    'Не удалось проверить подготовленный JPEG.'
                );
            }

            if (!rewind($stream)) {
                throw new RuntimeException(
                    'Не удалось подготовить JPEG к сохранению.'
                );
            }

            return [
                'stream' => $stream,
                'size' => $size,
                'sha256' => $sha256,
                'width' => $image->getImageWidth(),
                'height' => $image->getImageHeight(),
            ];
        } catch (Throwable $error) {
            fclose($stream);

            throw $error;
        }
    }
}
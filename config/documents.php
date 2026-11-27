<?php

return [
    /*
     * Диск для новых постоянных файлов.
     *
     * Локально: documents_local
     * В облаке: имя настроенного S3-диска.
     */
    'disk' => env(
        'DOCUMENTS_DISK',
        'documents_local'
    ),

    /*
     * Временные части файлов и рабочие файлы обработки.
     *
     * При переходе на S3 временная обработка
     * может по-прежнему выполняться локально.
     */
    'temporary_disk' => env(
        'DOCUMENTS_TEMP_DISK',
        'documents_local'
    ),
    'office' => [
        'executable' => env(
            'LIBREOFFICE_BINARY',
            PHP_OS_FAMILY === 'Windows'
            ? 'C:/Program Files/LibreOffice/program/soffice.exe'
            : '/usr/bin/libreoffice'
        ),

        'timeout' => 150,

        // Ограничение именно для автоматического просмотра.
        'max_source_bytes' => 100 * 1024 * 1024,
    ],
    'archives' => [
        'executable' => env(
            'DOCUMENTS_ARCHIVE_BINARY',
            PHP_OS_FAMILY === 'Windows'
            ? 'C:/Program Files/7-Zip/7z.exe'
            : '/usr/bin/7z'
        ),

        'allowed_extensions' => [
            'zip',
            'rar',
            '7z',
            'tar',
        ],

        'max_entries' => 10000,
        'max_entry_bytes' => 209715200,
        'max_uncompressed_bytes' => 32212254720,
        'max_path_length' => 500,

        'preview_mimes' => [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif',
            'text/plain',
        ],
    ],

    'text_preview' => [
        'max_bytes' => 1024 * 1024,
    ],


    'uploads' => [
        /*
         * Ограниченная параллельность применяется
         * и на ПК, и на смартфоне.
         */
        'parallel_files' => 2,

        /*
         * Размер одной передаваемой части — 8 МиБ.
         * Это не ограничение размера целого файла.
         */
        'chunk_size_bytes' => 8 * 1024 * 1024,

        'max_files_per_batch' => 500,

        /*
         * Начальные эксплуатационные ограничения.
         * Их можно изменить через .env.
         */
        'max_file_bytes' => (int) env(
            'DOCUMENTS_MAX_FILE_BYTES',
            5 * 1024 * 1024 * 1024
        ),

        'max_batch_bytes' => (int) env(
            'DOCUMENTS_MAX_BATCH_BYTES',
            20 * 1024 * 1024 * 1024
        ),

        'temporary_retention_hours' => 48,

        /*
         * Предварительный фильтр.
         * Реальный MIME и содержимое проверяются после получения файла.
         */
        'allowed_extensions' => [
            'pdf',
            'doc',
            'docx',
            'xls',
            'xlsx',
            'csv',
            'txt',
            'rtf',
            'odt',
            'ods',
            'jpg',
            'jpeg',
            'png',
            'webp',
            'heic',
            'heif',
            'avif',
            'tif',
            'tiff',
            'zip',
            'rar',
            '7z',
        ],
    ],

    'photos' => [
        /*
         * Исходник не перезаписываем:
         * оптимизированная фотография и превью —
         * производные файлы.
         */
        'preserve_originals' => true,

        'optimized_max_side' => 2560,
        'jpeg_quality' => 82,

        'preview_max_side' => 380,
        'preview_jpeg_quality' => 55,

        'max_pixels' => 80000000,
        /*
         * В комплект фото можно отправить фотографии
         * либо архив с фотографиями.
         *
         * Наличие расширения в этом списке не означает,
         * что все форматы уже поддерживаются обработчиком.
         */
        'upload_extensions' => [
            'jpg',
            'jpeg',
            'png',
            'webp',
            'heic',
            'heif',
            'avif',
            'tif',
            'tiff',
            'zip',
            'rar',
            '7z',
        ],
    ],


];
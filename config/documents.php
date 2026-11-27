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

    'archives' => [
        /*
         * Расширение — только предварительная проверка.
         * Содержимое проверяется сервером отдельно.
         */
        'allowed_extensions' => [
            'zip',
            'rar',
            '7z',
        ],

        /*
         * Ограничения защищают от архивных бомб
         * и неконтролируемой распаковки.
         */
        'max_entries' => 10000,

        'max_uncompressed_bytes' => (int) env(
            'DOCUMENTS_ARCHIVE_MAX_UNCOMPRESSED_BYTES',
            30 * 1024 * 1024 * 1024
        ),

        'max_compression_ratio' => 200,
    ],
];
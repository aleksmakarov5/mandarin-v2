<?php

namespace App\Services\Documents;

use App\Models\Application;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\DocumentType;
use App\Models\DocumentUploadBatch;
use App\Models\DocumentUploadItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class DocumentPublicationService
{
    public function publish(int $itemId): void
    {
        $item = DocumentUploadItem::findOrFail($itemId);

        // Уже опубликован: повторная задача ничего не создаёт.
        if ($item->document_file_id !== null) {
            return;
        }

        if ($item->status !== 'uploaded') {
            return;
        }

        $batch = DocumentUploadBatch::findOrFail($item->batch_id);

        $application = Application::findOrFail(
            $batch->application_id
        );

        $user = User::findOrFail(
            $batch->created_by_user_id
        );

        Gate::forUser($user)->authorize('view', $application);
        Gate::forUser($user)->authorize(
            'manageDocuments',
            $application
        );

        $context = $batch->context ?? [];
        $targetDisk = $context['storage_disk'] ?? null;

        if (
            !is_string($targetDisk)
            || $targetDisk === ''
            || !$item->temp_disk
            || !$item->temp_path
        ) {
            throw new RuntimeException(
                'Не настроено хранилище исходного файла.'
            );
        }

        /*
         * Исходник может находиться на S3.
         * Для finfo и вычисления хеша используем рабочий
         * файл на диске, а не строку в оперативной памяти.
         */
        $working = tmpfile();

        if ($working === false) {
            throw new RuntimeException(
                'Не удалось создать рабочий файл публикации.'
            );
        }

        $targetPath = null;
        $thumbnailPath = null;

        $thumbnail = null;

        $imageWidth = null;
        $imageHeight = null;

        $registered = false;

        try {
            $source = Storage::disk($item->temp_disk)
                ->readStream($item->temp_path);

            if (!is_resource($source)) {
                throw new RuntimeException(
                    'Не удалось прочитать собранный исходник.'
                );
            }

            try {
                $bytes = stream_copy_to_stream($source, $working);
            } finally {
                fclose($source);
            }

            if (
                $bytes === false
                || $bytes !== (int) $item->file_size
            ) {
                throw new RuntimeException(
                    'Размер исходника не совпадает с зарегистрированным.'
                );
            }
            if (!fflush($working)) {
                throw new RuntimeException(
                    'Не удалось сбросить буфер рабочего файла публикации.'
                );
            }
            $metadata = stream_get_meta_data($working);
            $localPath = $metadata['uri'];

            $mime = (new \finfo(FILEINFO_MIME_TYPE))
                ->file($localPath);

            $sha256 = hash_file('sha256', $localPath);

            if (!$mime || !$sha256) {
                throw new RuntimeException(
                    'Не удалось определить свойства исходника.'
                );
            }

            $extension = strtolower(
                pathinfo(
                    $item->original_name,
                    PATHINFO_EXTENSION
                )
            );

            $type = $batch->document_type_id
                ? DocumentType::query()
                    ->where('is_active', true)
                    ->findOrFail($batch->document_type_id)
                : null;

            $this->validateFile(
                $extension,
                $mime,
                $type
            );
            $storedSize = (int) $item->file_size;

            $rasterMimes = [
                'image/jpeg',
                'image/png',
                'image/webp',
                'image/heic',
                'image/heif',
                'image/avif',
                'image/tiff',
            ];

            if (in_array($mime, $rasterMimes, true)) {
                $normalized = app(PhotoNormalizationService::class)
                    ->normalize($localPath, $mime);

                /*
                 * Старый рабочий файл больше не нужен.
                 * Теперь $working содержит основной JPEG.
                 */
                fclose($working);

                $working = $normalized['main']['stream'];
                $thumbnail = $normalized['thumbnail'];

                $storedSize = $normalized['main']['size'];
                $sha256 = $normalized['main']['sha256'];

                $imageWidth = $normalized['main']['width'];
                $imageHeight = $normalized['main']['height'];

                $extension = 'jpg';
                $mime = 'image/jpeg';
            }
            /*
             * Имя пользователя не используется в физическом пути.
             * Оригинальное имя хранится только в БД.
             */
            $targetPath = sprintf(
                'applications/%d/documents/sources/%s.%s',
                $application->id,
                (string) Str::uuid(),
                $extension
            );

            if (!rewind($working)) {
                throw new RuntimeException(
                    'Не удалось подготовить исходник к сохранению.'
                );
            }

            $stored = Storage::disk($targetDisk)->put(
                $targetPath,
                $working,
                ['visibility' => 'private']
            );

            if (
                !$stored
                || Storage::disk($targetDisk)->size($targetPath)
                !== $storedSize
            ) {
                throw new RuntimeException(
                    'Не удалось сохранить постоянный файл.'
                );
            }

            if ($thumbnail !== null) {
                $thumbnailPath = sprintf(
                    'applications/%d/documents/thumbnails/%s.jpg',
                    $application->id,
                    (string) Str::uuid()
                );

                if (!rewind($thumbnail['stream'])) {
                    throw new RuntimeException(
                        'Не удалось подготовить миниатюру к сохранению.'
                    );
                }

                $thumbnailStored = Storage::disk($targetDisk)->put(
                    $thumbnailPath,
                    $thumbnail['stream'],
                    ['visibility' => 'private']
                );

                if (
                    !$thumbnailStored
                    || Storage::disk($targetDisk)->size($thumbnailPath)
                    !== $thumbnail['size']
                ) {
                    throw new RuntimeException(
                        'Не удалось сохранить миниатюру фотографии.'
                    );
                }
            }

            $registered = DB::transaction(function () use ($application, $user, $batch, $item, $targetDisk, $targetPath, $mime, $sha256, $extension, $storedSize, $imageWidth, $imageHeight, $thumbnail, $thumbnailPath): bool {
                /*
                 * Один порядок блокировок во всех обработчиках:
                 * дело → пакет → элемент.
                 */
                $lockedApplication = Application::query()
                    ->lockForUpdate()
                    ->findOrFail($application->id);

                Gate::forUser($user)->authorize(
                    'view',
                    $lockedApplication
                );

                Gate::forUser($user)->authorize(
                    'manageDocuments',
                    $lockedApplication
                );

                $lockedBatch = DocumentUploadBatch::query()
                    ->where(
                        'application_id',
                        $lockedApplication->id
                    )
                    ->where('created_by_user_id', $user->id)
                    ->lockForUpdate()
                    ->findOrFail($batch->id);

                $lockedItem = DocumentUploadItem::query()
                    ->where('batch_id', $lockedBatch->id)
                    ->lockForUpdate()
                    ->findOrFail($item->id);

                if ($lockedItem->document_file_id !== null) {
                    return false;
                }

                if (
                    $lockedItem->status !== 'uploaded'
                    || !in_array(
                        $lockedBatch->status,
                        ['pending', 'uploading', 'processing'],
                        true
                    )
                ) {
                    return false;
                }

                $type = $lockedBatch->document_type_id
                    ? DocumentType::query()
                        ->where('is_active', true)
                        ->findOrFail(
                            $lockedBatch->document_type_id
                        )
                    : null;

                $document = $this->resolveDocument(
                    $lockedApplication,
                    $lockedBatch,
                    $lockedItem,
                    $type,
                    $user
                );

                $file = DocumentFile::create([
                    'document_id' => $document->id,
                    'file_role' => 'original',

                    /*
                     * Имя должно соответствовать опубликованному формату.
                     * Имя принятого исходника остаётся в upload_items.
                     */
                    'original_name' => $mime === 'image/jpeg'
                        ? (
                            pathinfo(
                                $lockedItem->original_name,
                                PATHINFO_FILENAME
                            ) . '.jpg'
                        )
                        : $lockedItem->original_name,

                    'stored_name' => basename($targetPath),
                    'storage_disk' => $targetDisk,
                    'storage_path' => $targetPath,

                    'extension' => $extension,
                    'mime_type' => $mime,

                    // Размер именно сохранённого файла после преобразования.
                    'file_size' => $storedSize,

                    // SHA-256 именно сохранённого файла.
                    'sha256' => $sha256,
                    'width' => $imageWidth,
                    'height' => $imageHeight,
                    /*
                     * Для фотографии готов нормализованный JPEG.
                     * Миниатюры галереи создаются отдельно.
                     */
                    'processing_status' => 'ready',
                    'is_current' => true,
                    'is_inline' => false,

                    'uploaded_by_user_id' => $user->id,
                ]);

                $lockedItem->document_file_id = $file->id;
                if ($thumbnail !== null && $thumbnailPath !== null) {
                    DocumentFile::create([
                        'document_id' => $document->id,
                        'parent_file_id' => $file->id,

                        'file_role' => 'thumbnail',

                        'original_name' =>
                            'thumbnail-' . $file->id . '.jpg',

                        'stored_name' => basename($thumbnailPath),
                        'storage_disk' => $targetDisk,
                        'storage_path' => $thumbnailPath,

                        'extension' => 'jpg',
                        'mime_type' => 'image/jpeg',

                        'file_size' => $thumbnail['size'],
                        'sha256' => $thumbnail['sha256'],

                        'width' => $thumbnail['width'],
                        'height' => $thumbnail['height'],

                        'processing_status' => 'ready',
                        'is_current' => true,
                        'is_inline' => false,

                        'uploaded_by_user_id' => $user->id,
                    ]);
                }
                $lockedItem->status = 'completed';
                $lockedItem->error = null;
                $lockedItem->save();

                $document->updated_by_user_id = $user->id;
                $document->touch();

                /*
                 * Счётчики считаем по БД, а не увеличиваем:
                 * повторная задача не удвоит значения.
                 */
                $publishedCount = DocumentUploadItem::query()
                    ->where('batch_id', $lockedBatch->id)
                    ->whereNotNull('document_file_id')
                    ->count();

                $lockedBatch->processed_count = $publishedCount;

                if (
                    $publishedCount === (int) $lockedBatch->total_count
                ) {
                    $lockedBatch->status = 'ready';
                }

                $lockedBatch->save();

                return true;
            });
        } finally {
            if (is_resource($working)) {
                fclose($working);
            }

            if (
                is_array($thumbnail)
                && is_resource($thumbnail['stream'] ?? null)
            ) {
                fclose($thumbnail['stream']);
            }

            /*
             * Если транзакция не зарегистрировала файлы,
             * удаляем обе непривязанные физические копии.
             *
             * Принятый исходник и части загрузки здесь не удаляем.
             */
            if (!$registered) {
                foreach ([$targetPath, $thumbnailPath] as $path) {
                    if ($path === null) {
                        continue;
                    }

                    try {
                        $deleted = Storage::disk($targetDisk)
                            ->delete($path);

                        if (!$deleted) {
                            Log::warning(
                                'Не удалось удалить непривязанный файл.',
                                [
                                    'item_id' => $itemId,
                                    'path' => $path,
                                ]
                            );
                        }
                    } catch (Throwable $error) {
                        Log::warning(
                            'Ошибка удаления непривязанного файла.',
                            [
                                'item_id' => $itemId,
                                'path' => $path,
                                'error' => $error->getMessage(),
                            ]
                        );
                    }
                }
            }
        }
    }

    private function resolveDocument(
        Application $application,
        DocumentUploadBatch $batch,
        DocumentUploadItem $item,
        ?DocumentType $type,
        User $user
    ): Document {
        /*
         * Общая загрузка:
         * каждый файл — отдельный нераспределённый документ.
         */
        if ($batch->batch_type === 'unassigned') {
            return Document::create([
                'application_id' => $application->id,
                'document_type_id' => null,
                'title' => $item->original_name,
                'source_type' => 'manual',
                'lifecycle_status' => 'active',

                // Не расширяем доступ автоматически.
                'visibility_scope' => 'internal',

                'created_by_user_id' => $user->id,
                'updated_by_user_id' => $user->id,
            ]);
        }

        if (!$type) {
            throw new RuntimeException(
                'Для типизированной загрузки не найден тип.'
            );
        }

        /*
         * Типизированный пакет:
         * все его файлы принадлежат одному документу.
         *
         * Для фото это конкретный комплект,
         * а не все фотографии такого типа в деле.
         */
        if ($batch->document_id !== null) {
            return Document::query()
                ->where('application_id', $application->id)
                ->where('document_type_id', $type->id)
                ->where('is_archived', false)
                ->where('lifecycle_status', '!=', 'deleted')
                ->lockForUpdate()
                ->findOrFail($batch->document_id);
        }

        $context = $batch->context ?? [];

        $document = Document::create([
            'application_id' => $application->id,
            'document_type_id' => $type->id,

            'title' => ($context['title'] ?? null) ?: $type->name,
            'document_date' => $context['document_date'] ?? null,

            'source_type' => 'manual',
            'lifecycle_status' => 'active',
            'visibility_scope' => 'internal',

            'created_by_user_id' => $user->id,
            'updated_by_user_id' => $user->id,
        ]);

        /*
         * Пакет уже заблокирован:
         * соседняя задача не создаст второй документ.
         */
        $batch->document_id = $document->id;
        $batch->save();

        return $document;
    }

    private function validateFile(
        string $extension,
        string $mime,
        ?DocumentType $type
    ): void {
        $allowedExtensions = config(
            'documents.uploads.allowed_extensions',
            []
        );

        if (!in_array($extension, $allowedExtensions, true)) {
            throw new RuntimeException(
                'Расширение файла не разрешено.'
            );
        }

        /*
         * Первый этап обработки.
         *
         * Остальные форматы из предварительного фильтра
         * требуют отдельных проверок — не выдаём их
         * за проверенные документы.
         */
        $mimeByExtension = [
            'pdf' => ['application/pdf'],
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'webp' => ['image/webp'],
            'heic' => ['image/heic', 'image/heif'],
            'heif' => ['image/heif', 'image/heic'],
            'avif' => ['image/avif'],
            'tif' => ['image/tiff'],
            'tiff' => ['image/tiff'],

            // Храним как закрытый исходник, не распаковываем.
            'zip' => [
                'application/zip',
                'application/x-zip',
                'application/x-zip-compressed',
            ],
        ];

        if (!isset($mimeByExtension[$extension])) {
            throw new RuntimeException(
                'Проверка этого формата ещё не подключена.'
            );
        }

        if (!in_array($mime, $mimeByExtension[$extension], true)) {
            throw new RuntimeException(
                'Содержимое файла не соответствует расширению.'
            );
        }

        if (
            $type?->is_photo
            && !str_starts_with($mime, 'image/')
        ) {
            throw new RuntimeException(
                'В комплект фотографий принимаются изображения.'
            );
        }
    }
}
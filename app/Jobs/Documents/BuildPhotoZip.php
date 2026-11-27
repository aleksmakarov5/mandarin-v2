<?php

namespace App\Jobs\Documents;

use App\Models\Document;
use App\Models\DocumentFile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

class BuildPhotoZip implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 1800;
    public int $tries = 3;
    public int $uniqueFor = 2100;

    public function __construct(
        public int $documentId
    ) {
    }

    public function uniqueId(): string
    {
        return 'photo-zip:' . $this->documentId;
    }

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(): void
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException(
                'В PHP worker не установлено расширение ZIP.'
            );
        }

        /*
         * Получаем согласованный снимок:
         * версия комплекта + состав + порядок.
         *
         * Операции изменения фото должны блокировать
         * документ и увеличивать content_revision.
         */
        $snapshot = DB::transaction(function (): ?array {
            $document = Document::query()
                ->where('is_archived', false)
                ->where('lifecycle_status', '!=', 'deleted')
                ->whereHas(
                    'type',
                    fn($query) =>
                        $query->where('is_photo', true)
                )
                ->lockForUpdate()
                ->find($this->documentId);

            if (!$document) {
                return null;
            }

            $currentArchiveExists = DocumentFile::query()
                ->where('document_id', $document->id)
                ->where('file_role', 'archive')
                ->where('is_current', true)
                ->where('processing_status', 'ready')
                ->exists();

            if (
                $document->archive_status === 'ready'
                && $document->archive_revision !== null
                && (int) $document->archive_revision
                === (int) $document->content_revision
                && $currentArchiveExists
            ) {
                return null;
            }

            $photos = DocumentFile::query()
                ->where('document_id', $document->id)
                ->where('file_role', 'original')
                ->where('is_current', true)
                ->where('processing_status', 'ready')
                ->where('mime_type', 'like', 'image/%')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get([
                    'id',
                    'storage_disk',
                    'storage_path',
                    'extension',
                    'file_size',
                ]);

            if ($photos->isEmpty()) {
                $document->archive_status = 'failed';
                $document->archive_error =
                    'В комплекте нет готовых фотографий.';
                $document->save();

                return null;
            }

            $document->archive_status = 'building';
            $document->archive_error = null;
            $document->save();

            return [
                'application_id' => (int) $document->application_id,
                'revision' => (int) $document->content_revision,
                'photos' => $photos,
            ];
        });

        if ($snapshot === null) {
            return;
        }

        $directory = null;
        $zip = null;
        $zipOpened = false;

        $diskName = (string) config('documents.disk');
        $storagePath = null;
        $registered = false;

        try {
            $directory = sys_get_temp_dir()
                . DIRECTORY_SEPARATOR
                . 'mandarin-photo-zip-'
                . Str::uuid();

            if (!mkdir($directory, 0700, true) && !is_dir($directory)) {
                throw new RuntimeException(
                    'Не удалось создать рабочий каталог ZIP.'
                );
            }

            $zipPath = $directory . DIRECTORY_SEPARATOR . 'photos.zip';

            $zip = new ZipArchive();

            $opened = $zip->open(
                $zipPath,
                ZipArchive::CREATE | ZipArchive::OVERWRITE
            );

            if ($opened !== true) {
                throw new RuntimeException(
                    'Не удалось создать ZIP.'
                );
            }

            $zipOpened = true;

            foreach ($snapshot['photos'] as $index => $photo) {
                $extension = strtolower(
                    (string) $photo->extension
                );

                if (
                    !in_array($extension, [
                        'jpg',
                        'jpeg',
                        'png',
                        'webp',
                        'heic',
                        'heif',
                        'avif',
                        'tif',
                        'tiff',
                    ], true)
                ) {
                    throw new RuntimeException(
                        'Недопустимый формат фотографии в комплекте.'
                    );
                }

                $entryName = sprintf(
                    '%04d.%s',
                    $index + 1,
                    $extension
                );

                $localPath = $directory
                    . DIRECTORY_SEPARATOR
                    . $entryName;

                $source = Storage::disk($photo->storage_disk)
                    ->readStream($photo->storage_path);

                if (!is_resource($source)) {
                    throw new RuntimeException(
                        'Не удалось прочитать фотографию.'
                    );
                }

                $destination = fopen($localPath, 'wb');

                if ($destination === false) {
                    fclose($source);

                    throw new RuntimeException(
                        'Не удалось создать рабочую копию фотографии.'
                    );
                }

                try {
                    $bytes = stream_copy_to_stream(
                        $source,
                        $destination
                    );
                } finally {
                    fclose($source);
                    fclose($destination);
                }

                if (
                    $bytes === false
                    || $bytes !== (int) $photo->file_size
                ) {
                    throw new RuntimeException(
                        'Размер фотографии не совпадает с записью БД.'
                    );
                }

                if (!$zip->addFile($localPath, $entryName)) {
                    throw new RuntimeException(
                        'Не удалось добавить фотографию в ZIP.'
                    );
                }

                /*
                 * JPEG уже сжат. Повторный DEFLATE почти
                 * не уменьшит размер, но потратит процессор.
                 */
                if (
                    !$zip->setCompressionName(
                        $entryName,
                        ZipArchive::CM_STORE
                    )
                ) {
                    throw new RuntimeException(
                        'Не удалось настроить запись ZIP.'
                    );
                }
            }

            /*
             * Рабочие фотографии нельзя удалять до close():
             * ZipArchive может читать их именно на этом этапе.
             */
            $closed = $zip->close();
            $zipOpened = false;

            if (!$closed) {
                throw new RuntimeException(
                    'Не удалось завершить ZIP.'
                );
            }

            clearstatcache(true, $zipPath);

            $size = filesize($zipPath);
            $sha256 = hash_file('sha256', $zipPath);

            if ($size === false || $size <= 0 || !$sha256) {
                throw new RuntimeException(
                    'Не удалось проверить готовый ZIP.'
                );
            }

            $storagePath = sprintf(
                'applications/%d/documents/archives/%d/%s.zip',
                $snapshot['application_id'],
                $this->documentId,
                Str::uuid()
            );

            $stream = fopen($zipPath, 'rb');

            if ($stream === false) {
                throw new RuntimeException(
                    'Не удалось открыть готовый ZIP.'
                );
            }

            try {
                $stored = Storage::disk($diskName)->put(
                    $storagePath,
                    $stream,
                    ['visibility' => 'private']
                );
            } finally {
                fclose($stream);
            }

            if (
                !$stored
                || (int) Storage::disk($diskName)->size($storagePath)
                !== $size
            ) {
                throw new RuntimeException(
                    'Не удалось сохранить ZIP.'
                );
            }

            $registered = DB::transaction(function () use ($snapshot, $diskName, $storagePath, $size, $sha256): bool {
                $document = Document::query()
                    ->lockForUpdate()
                    ->find($this->documentId);

                if (
                    !$document
                    || $document->is_archived
                    || $document->lifecycle_status === 'deleted'
                ) {
                    return false;
                }

                /*
                 * Пока собирался архив, фото могли удалить,
                 * добавить или переставить.
                 * Устаревший результат не публикуем.
                 */
                if (
                    (int) $document->content_revision
                    !== $snapshot['revision']
                ) {
                    $document->archive_status = 'pending';
                    $document->archive_error = null;
                    $document->save();

                    return false;
                }

                DocumentFile::query()
                    ->where('document_id', $document->id)
                    ->where('file_role', 'archive')
                    ->where('is_current', true)
                    ->update(['is_current' => false]);

                DocumentFile::create([
                    'document_id' => $document->id,
                    'file_role' => 'archive',
                    'original_name' => 'photos.zip',
                    'stored_name' => basename($storagePath),
                    'storage_disk' => $diskName,
                    'storage_path' => $storagePath,
                    'extension' => 'zip',
                    'mime_type' => 'application/zip',
                    'file_size' => $size,
                    'sha256' => $sha256,
                    'processing_status' => 'ready',
                    'is_current' => true,
                    'is_inline' => false,
                ]);

                $document->archive_revision = $snapshot['revision'];
                $document->archive_status = 'ready';
                $document->archive_error = null;
                $document->save();

                return true;
            });
        } catch (Throwable $error) {
            Document::query()
                ->whereKey($this->documentId)
                ->where('content_revision', $snapshot['revision'])
                ->update([
                    'archive_status' => 'failed',
                    'archive_error' =>
                        'Не удалось сформировать ZIP. Повторите скачивание.',
                ]);

            throw $error;
        } finally {
            if ($zipOpened && $zip instanceof ZipArchive) {
                $zip->close();
            }

            if ($storagePath !== null && !$registered) {
                try {
                    Storage::disk($diskName)->delete($storagePath);
                } catch (Throwable $error) {
                    Log::warning('Не удалось удалить непривязанный ZIP.', [
                        'document_id' => $this->documentId,
                        'error' => $error->getMessage(),
                    ]);
                }
            }

            if ($directory !== null && is_dir($directory)) {
                foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
                    if (is_file($path)) {
                        @unlink($path);
                    }
                }

                @rmdir($directory);
            }
        }
    }
}
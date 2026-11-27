<?php

namespace App\Services\Documents;

use App\Models\Application;
use App\Models\DocumentUploadBatch;
use App\Models\DocumentUploadItem;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class DocumentUploadChunkService
{
    public function store(
        Application $application,
        DocumentUploadBatch $batch,
        DocumentUploadItem $item,
        User $user,
        int $chunkIndex,
        UploadedFile $file,
        ?string $clientSha256 = null
    ): array {
        $context = $batch->context ?? [];

        $chunkSize = (int) (
            $context['chunk_size_bytes'] ?? 0
        );

        $diskName = $context['temporary_disk'] ?? null;

        if ($chunkSize <= 0 || !is_string($diskName) || $diskName === '') {
            throw new RuntimeException(
                'У пакета не настроено временное хранилище '
                . 'или размер части.'
            );
        }

        $fileSize = (int) $item->file_size;

        $chunksCount = $this->chunksCount(
            $fileSize,
            $chunkSize
        );

        if ($chunkIndex < 0 || $chunkIndex >= $chunksCount) {
            throw ValidationException::withMessages([
                'chunk' => 'Номер части находится за пределами файла.',
            ]);
        }

        $expectedSize = min(
            $chunkSize,
            $fileSize - $chunkIndex * $chunkSize
        );

        $actualSize = $file->getSize();

        if ($actualSize === false || $actualSize !== $expectedSize) {
            throw ValidationException::withMessages([
                'chunk' =>
                    "Неверный размер части. Ожидается {$expectedSize} байт.",
            ]);
        }

        $temporaryPath = $file->getRealPath();

        if ($temporaryPath === false) {
            throw new RuntimeException(
                'Не удалось прочитать временный файл запроса.'
            );
        }

        $sha256 = hash_file('sha256', $temporaryPath);

        if ($sha256 === false) {
            throw new RuntimeException(
                'Не удалось вычислить контрольную сумму части.'
            );
        }

        if (
            $clientSha256 !== null
            && !hash_equals(
                strtolower($clientSha256),
                $sha256
            )
        ) {
            throw ValidationException::withMessages([
                'sha256' =>
                    'Контрольная сумма части не совпадает. '
                    . 'Повторите отправку.',
            ]);
        }

        /*
         * Каждая попытка получает собственный внутренний путь.
         *
         * Параллельные повторы не перезаписывают уже принятую
         * часть, в том числе при хранении на S3.
         */
        $storagePath = sprintf(
            'temporary/uploads/%d/%d/%d/%s.part',
            $application->id,
            $batch->id,
            $item->id,
            (string) Str::uuid()
        );

        $stream = fopen($temporaryPath, 'rb');

        if ($stream === false) {
            throw new RuntimeException(
                'Не удалось открыть часть для сохранения.'
            );
        }

        try {
            /*
             * Передаём поток, а не содержимое файла строкой.
             * Целый архив в оперативную память не загружается.
             */
            $stored = Storage::disk($diskName)->put(
                $storagePath,
                $stream,
                ['visibility' => 'private']
            );

            if (!$stored) {
                throw new RuntimeException(
                    'Хранилище не подтвердило сохранение части.'
                );
            }
        } catch (\Throwable $error) {
            $this->deleteTemporaryFile(
                $diskName,
                $storagePath
            );

            throw $error;
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        try {
            $result = DB::transaction(function () use ($application, $batch, $item, $user, $chunkIndex, $actualSize, $sha256, $diskName, $storagePath): array {
                /*
                 * Порядок блокировок:
                 * дело → пакет → элемент.
                 *
                 * Доступ перепроверяется перед регистрацией.
                 */
                $lockedApplication = Application::query()
                    ->lockForUpdate()
                    ->findOrFail($application->id);

                Gate::authorize('view', $lockedApplication);
                Gate::authorize(
                    'manageDocuments',
                    $lockedApplication
                );

                $lockedBatch = DocumentUploadBatch::query()
                    ->where('application_id', $lockedApplication->id)
                    ->where('created_by_user_id', $user->id)
                    ->lockForUpdate()
                    ->findOrFail($batch->id);

                $lockedItem = DocumentUploadItem::query()
                    ->where('batch_id', $lockedBatch->id)
                    ->lockForUpdate()
                    ->findOrFail($item->id);

                $existing = DB::table('document_upload_chunks')
                    ->where('upload_item_id', $lockedItem->id)
                    ->where('chunk_index', $chunkIndex)
                    ->first();

                if ($existing) {
                    if (
                        (int) $existing->size_bytes !== $actualSize
                        || !hash_equals($existing->sha256, $sha256)
                    ) {
                        abort(
                            409,
                            'Часть с таким номером уже принята, '
                            . 'но её содержимое отличается.'
                        );
                    }

                    /*
                     * Повтор той же части — успешный ответ.
                     * Новую временную копию удалим после транзакции.
                     */
                    return [
                        'duplicate' => true,
                        'chunk_index' => $chunkIndex,
                        'size_bytes' => $actualSize,
                        'sha256' => $sha256,
                    ];
                }

                if (
                    !in_array(
                        $lockedBatch->status,
                        ['pending', 'uploading'],
                        true
                    )
                    || !in_array(
                        $lockedItem->status,
                        ['pending', 'uploading'],
                        true
                    )
                ) {
                    abort(
                        409,
                        'Этот файл больше не принимает новые части.'
                    );
                }

                DB::table('document_upload_chunks')->insert([
                    'upload_item_id' => $lockedItem->id,
                    'chunk_index' => $chunkIndex,
                    'size_bytes' => $actualSize,
                    'sha256' => $sha256,
                    'storage_disk' => $diskName,
                    'storage_path' => $storagePath,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $lockedItem->status = 'uploading';
                $lockedItem->error = null;
                $lockedItem->save();

                if ($lockedBatch->status === 'pending') {
                    $lockedBatch->status = 'uploading';
                    $lockedBatch->save();
                }

                return [
                    'duplicate' => false,
                    'chunk_index' => $chunkIndex,
                    'size_bytes' => $actualSize,
                    'sha256' => $sha256,
                ];
            });
        } catch (\Throwable $error) {
            /*
             * Эта попытка не зарегистрирована в БД.
             * Удаляем только её собственный временный файл.
             */
            $this->deleteTemporaryFile(
                $diskName,
                $storagePath
            );

            throw $error;
        }

        if ($result['duplicate']) {
            $this->deleteTemporaryFile(
                $diskName,
                $storagePath
            );
        }

        return $result;
    }

    public function state(
        DocumentUploadBatch $batch,
        DocumentUploadItem $item
    ): array {
        $chunkSize = (int) (
            $batch->context['chunk_size_bytes'] ?? 0
        );

        if ($chunkSize <= 0) {
            throw new RuntimeException(
                'У пакета не настроен размер части.'
            );
        }

        $fileSize = (int) $item->file_size;

        $expectedCount = $this->chunksCount(
            $fileSize,
            $chunkSize
        );

        $chunks = DB::table('document_upload_chunks')
            ->where('upload_item_id', $item->id)
            ->orderBy('chunk_index')
            ->get([
                'chunk_index',
                'size_bytes',
                'sha256',
            ]);

        $receivedBytes = (int) $chunks->sum('size_bytes');

        return [
            'id' => (int) $item->id,
            'batch_id' => (int) $batch->id,
            'client_file_key' => $item->client_file_key,
            'original_name' => $item->original_name,
            'status' => $item->status,
            'error' => $item->error,
            'document_file_id' => $item->document_file_id !== null
                ? (int) $item->document_file_id
                : null,

            'published' => $item->document_file_id !== null,
            'file_size' => $fileSize,

            'chunk_size_bytes' => $chunkSize,
            'chunks_count' => $expectedCount,
            'received_chunks_count' => $chunks->count(),
            'received_bytes' => $receivedBytes,

            /*
             * Только признак получения всех частей.
             * Не означает сборку и готовность документа.
             */
            'all_chunks_received' =>
                $chunks->count() === $expectedCount
                && $receivedBytes === $fileSize,

            'received_chunks' => $chunks->map(
                fn($chunk): array => [
                    'index' => (int) $chunk->chunk_index,
                    'size_bytes' => (int) $chunk->size_bytes,
                    'sha256' => $chunk->sha256,
                ]
            )->values()->all(),
        ];
    }

    private function chunksCount(
        int $fileSize,
        int $chunkSize
    ): int {
        return intdiv(
            $fileSize + $chunkSize - 1,
            $chunkSize
        );
    }

    private function deleteTemporaryFile(
        string $diskName,
        string $path
    ): void {
        try {
            $deleted = Storage::disk($diskName)->delete($path);

            if (!$deleted) {
                Log::warning(
                    'Не удалось удалить временную часть документа.',
                    ['disk' => $diskName, 'path' => $path]
                );
            }
        } catch (\Throwable $error) {
            /*
             * Ошибка очистки не должна превращать
             * успешно принятый повтор части в ошибку загрузки.
             */
            Log::warning(
                'Ошибка очистки временной части документа.',
                [
                    'disk' => $diskName,
                    'path' => $path,
                    'error' => $error->getMessage(),
                ]
            );
        }
    }
}
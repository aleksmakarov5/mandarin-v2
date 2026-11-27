<?php

namespace App\Jobs\Documents;

use App\Models\Application;
use App\Models\DocumentUploadBatch;
use App\Models\DocumentUploadItem;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use App\Jobs\Documents\PublishDocumentUpload;

class AssembleDocumentUpload implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /*
     * Для больших архивов сборка может быть длительной.
     * retry_after очереди должен быть больше timeout.
     */
    public int $timeout = 1800;

    /*
     * Сюда входят и отложенные попытки получения блокировки.
     */
    public int $tries = 10;

    public function __construct(
        public int $itemId
    ) {
    }

    public function middleware(): array
    {
        /*
         * Повторный запрос завершения может поставить
         * вторую задачу. Одновременную сборку запрещаем.
         *
         * Для нескольких серверов нужен общий cache store,
         * поддерживающий блокировки, например Redis.
         */
        return [
            (new WithoutOverlapping(
                'document-upload-assembly:' . $this->itemId
            ))
                ->releaseAfter(60)
                ->expireAfter(2100),
        ];
    }

    public function backoff(): array
    {
        return [10, 30, 60, 120];
    }

    public function handle(): void
    {
        $item = DocumentUploadItem::findOrFail($this->itemId);

        /*
         * Повторная задача после успешной сборки
         * ничего не пересобирает.
         */
        /*
         * Если сборка уже завершилась, но постановка публикации
         * в очередь не состоялась, повтор задачи восстановит цепочку.
         */
        if ($item->status === 'uploaded') {
            PublishDocumentUpload::dispatch($item->id)
                ->onQueue('documents');

            return;
        }

        if (
            in_array(
                $item->status,
                ['processing', 'completed'],
                true
            )
        ) {
            return;
        }

        if ($item->status !== 'assembling') {
            return;
        }

        $batch = DocumentUploadBatch::findOrFail(
            $item->batch_id
        );

        if (
            !in_array(
                $batch->status,
                ['pending', 'uploading'],
                true
            )
        ) {
            return;
        }

        /*
         * Между приёмом файлов и выполнением задачи
         * пользователь мог потерять доступ.
         */
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

        $chunkSize = (int) (
            $context['chunk_size_bytes'] ?? 0
        );

        $temporaryDisk = $context['temporary_disk'] ?? null;

        if (
            $chunkSize <= 0
            || !is_string($temporaryDisk)
            || $temporaryDisk === ''
        ) {
            throw new RuntimeException(
                'Не настроены параметры сборки пакета.'
            );
        }

        $fileSize = (int) $item->file_size;

        $expectedCount = intdiv(
            $fileSize + $chunkSize - 1,
            $chunkSize
        );

        $chunks = DB::table('document_upload_chunks')
            ->where('upload_item_id', $item->id)
            ->orderBy('chunk_index')
            ->get();

        if ($chunks->count() !== $expectedCount) {
            throw new RuntimeException(
                'Для сборки получены не все части файла.'
            );
        }

        /*
         * tmpfile() создаёт рабочий файл на локальном диске.
         * Это НЕ буфер размером с архив в оперативной памяти.
         *
         * PHP автоматически удалит файл после fclose().
         */
        $assembled = tmpfile();

        if ($assembled === false) {
            throw new RuntimeException(
                'Не удалось создать рабочий файл для сборки.'
            );
        }

        $storagePath = null;
        $registered = false;

        try {
            $assembledBytes = 0;

            foreach ($chunks as $index => $chunk) {
                if ((int) $chunk->chunk_index !== $index) {
                    throw new RuntimeException(
                        'Нарушена последовательность частей.'
                    );
                }

                $expectedSize = min(
                    $chunkSize,
                    $fileSize - $index * $chunkSize
                );

                if ((int) $chunk->size_bytes !== $expectedSize) {
                    throw new RuntimeException(
                        'Размер зарегистрированной части неверен.'
                    );
                }

                $source = Storage::disk(
                    $chunk->storage_disk
                )->readStream($chunk->storage_path);

                if (!is_resource($source)) {
                    throw new RuntimeException(
                        "Не удалось открыть часть {$index}."
                    );
                }

                try {
                    $hashContext = hash_init('sha256');
                    $partBytes = 0;

                    while (!feof($source)) {
                        /*
                         * В памяти находится только небольшой блок,
                         * а не целая часть или целый архив.
                         */
                        $buffer = fread($source, 1024 * 1024);

                        if ($buffer === false) {
                            throw new RuntimeException(
                                "Ошибка чтения части {$index}."
                            );
                        }

                        if ($buffer === '') {
                            if (feof($source)) {
                                break;
                            }

                            throw new RuntimeException(
                                "Чтение части {$index} прервано."
                            );
                        }

                        $length = strlen($buffer);
                        $partBytes += $length;

                        if ($partBytes > $expectedSize) {
                            throw new RuntimeException(
                                "Часть {$index} превышает ожидаемый размер."
                            );
                        }

                        hash_update($hashContext, $buffer);

                        $this->writeAll($assembled, $buffer);
                    }

                    $actualHash = hash_final($hashContext);

                    if (
                        $partBytes !== $expectedSize
                        || !hash_equals(
                            (string) $chunk->sha256,
                            $actualHash
                        )
                    ) {
                        throw new RuntimeException(
                            "Нарушена целостность части {$index}."
                        );
                    }

                    $assembledBytes += $partBytes;
                } finally {
                    fclose($source);
                }
            }

            if ($assembledBytes !== $fileSize) {
                throw new RuntimeException(
                    'Размер собранного файла не совпадает с исходным.'
                );
            }

            if (!rewind($assembled)) {
                throw new RuntimeException(
                    'Не удалось подготовить исходник к сохранению.'
                );
            }

            /*
             * Пока файл находится в закрытой зоне обработки.
             * Расширение пользователя не используется как путь.
             */
            $storagePath = sprintf(
                'temporary/assembled/%d/%d/%s.source',
                $batch->id,
                $item->id,
                (string) Str::uuid()
            );

            $stored = Storage::disk($temporaryDisk)->put(
                $storagePath,
                $assembled,
                ['visibility' => 'private']
            );

            if (!$stored) {
                throw new RuntimeException(
                    'Не удалось сохранить собранный исходник.'
                );
            }

            if (
                Storage::disk($temporaryDisk)->size($storagePath)
                !== $fileSize
            ) {
                throw new RuntimeException(
                    'Размер сохранённого исходника не совпадает.'
                );
            }

            /*
             * Файл уже записан в хранилище.
             * Теперь фиксируем результат в БД.
             */
            $registered = DB::transaction(function () use ($batch, $item, $temporaryDisk, $storagePath): bool {
                $lockedBatch = DocumentUploadBatch::query()
                    ->lockForUpdate()
                    ->findOrFail($batch->id);

                $lockedItem = DocumentUploadItem::query()
                    ->where('batch_id', $lockedBatch->id)
                    ->lockForUpdate()
                    ->findOrFail($item->id);

                if (
                    $lockedItem->status !== 'assembling'
                    || !in_array(
                        $lockedBatch->status,
                        ['pending', 'uploading'],
                        true
                    )
                ) {
                    return false;
                }

                $lockedItem->temp_disk = $temporaryDisk;
                $lockedItem->temp_path = $storagePath;
                $lockedItem->status = 'uploaded';
                $lockedItem->error = null;
                $lockedItem->save();

                /*
                 * Пересчёт надёжнее increment():
                 * повтор задачи не увеличит счётчик дважды.
                 */
                $lockedBatch->uploaded_count =
                    DocumentUploadItem::query()
                        ->where('batch_id', $lockedBatch->id)
                        ->whereNotNull('temp_path')
                        ->count();

                $lockedBatch->failed_count =
                    DocumentUploadItem::query()
                        ->where('batch_id', $lockedBatch->id)
                        ->where('status', 'failed')
                        ->count();

                $lockedBatch->save();

                return true;
            });
        } finally {
            fclose($assembled);

            /*
             * Если результат не зарегистрирован,
             * эта временная копия больше не нужна.
             *
             * Части пока сохраняем для повторной сборки.
             */
            if ($storagePath !== null && !$registered) {
                try {
                    Storage::disk($temporaryDisk)->delete(
                        $storagePath
                    );
                } catch (Throwable $error) {
                    Log::warning(
                        'Ошибка очистки несохранённого исходника.',
                        [
                            'item_id' => $this->itemId,
                            'error' => $error->getMessage(),
                        ]
                    );
                }
            }
        }

        if ($registered) {
            PublishDocumentUpload::dispatch($this->itemId)
                ->onQueue('documents');
        }
    }

    /**
     * fwrite() может записать не весь переданный блок.
     */
    private function writeAll($stream, string $buffer): void
    {
        $offset = 0;
        $length = strlen($buffer);

        while ($offset < $length) {
            $written = fwrite(
                $stream,
                substr($buffer, $offset)
            );

            if ($written === false || $written === 0) {
                throw new RuntimeException(
                    'Ошибка записи рабочего файла.'
                );
            }

            $offset += $written;
        }
    }

    /**
     * Вызывается после исчерпания попыток задачи.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('Не удалось собрать загруженный файл.', [
            'item_id' => $this->itemId,
            'error' => $exception?->getMessage(),
        ]);

        $item = DocumentUploadItem::find($this->itemId);

        if (!$item) {
            return;
        }

        DB::transaction(function () use ($item): void {
            $batch = DocumentUploadBatch::query()
                ->lockForUpdate()
                ->find($item->batch_id);

            if (!$batch) {
                return;
            }

            $lockedItem = DocumentUploadItem::query()
                ->lockForUpdate()
                ->find($item->id);

            if (
                !$lockedItem
                || $lockedItem->status !== 'assembling'
            ) {
                return;
            }

            $lockedItem->status = 'failed';

            /*
             * Не выдаём внутренние пути и сообщения
             * хранилища пользователю.
             */
            $lockedItem->error =
                'Не удалось собрать исходный файл. '
                . 'Можно повторить завершение загрузки.';

            $lockedItem->save();

            $batch->failed_count = DocumentUploadItem::query()
                ->where('batch_id', $batch->id)
                ->where('status', 'failed')
                ->count();

            $batch->save();
        });
    }
}
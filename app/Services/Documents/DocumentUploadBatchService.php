<?php

namespace App\Services\Documents;

use App\Models\Application;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\DocumentUploadBatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DocumentUploadBatchService
{
    /**
     * @return array{batch: DocumentUploadBatch, created: bool}
     */
    public function create(
        Application $application,
        User $user,
        array $data
    ): array {
        $files = $this->normalizeFiles($data['files']);

        $totalBytes = array_sum(
            array_column($files, 'file_size')
        );

        $maxBatchBytes = (int) config(
            'documents.uploads.max_batch_bytes'
        );

        if ($totalBytes > $maxBatchBytes) {
            throw ValidationException::withMessages([
                'files' =>
                    'Общий размер выбранных файлов '
                    . 'превышает ограничение пакета.',
            ]);
        }

        /*
         * Регистрируем файлы в устойчивом порядке.
         * Повтор того же пакета с другим порядком
         * файлов не должен создавать конфликт.
         */
        usort(
            $files,
            fn(array $a, array $b): int =>
                strcmp(
                    $a['client_file_key'],
                    $b['client_file_key']
                )
        );

        $manifestHash = hash(
            'sha256',
            json_encode([
                'placement' => $data['placement'],
                'document_type_id' =>
                    isset($data['document_type_id'])
                    ? (int) $data['document_type_id']
                    : null,
                'document_id' =>
                    isset($data['document_id'])
                    ? (int) $data['document_id']
                    : null,
                'title' => isset($data['title'])
                    ? trim($data['title'])
                    : null,
                'document_date' =>
                    $data['document_date'] ?? null,
                'files' => $files,
            ], JSON_THROW_ON_ERROR)
        );

        return DB::transaction(function () use ($application, $user, $data, $files, $totalBytes, $manifestHash): array {
            /*
             * Одновременные регистрации пакетов одного дела
             * сериализуем короткой блокировкой.
             *
             * Содержимое файлов внутри транзакции
             * не принимается и не обрабатывается.
             */
            $lockedApplication = Application::query()
                ->lockForUpdate()
                ->findOrFail($application->id);

            Gate::authorize('view', $lockedApplication);
            Gate::authorize(
                'manageDocuments',
                $lockedApplication
            );

            /*
             * Повторный запрос возвращает прежний пакет.
             * Ключ ограничен делом и создателем.
             */
            $existing = DocumentUploadBatch::query()
                ->where('application_id', $lockedApplication->id)
                ->where('created_by_user_id', $user->id)
                ->where(
                    'client_batch_key',
                    $data['client_batch_key']
                )
                ->first();

            if ($existing) {
                $existingHash = $existing->context[
                    'manifest_hash'
                ] ?? null;

                if (
                    !is_string($existingHash)
                    || !hash_equals($existingHash, $manifestHash)
                ) {
                    abort(
                        409,
                        'Этот идентификатор пакета уже использован '
                        . 'для другого набора файлов.'
                    );
                }

                return [
                    'batch' => $existing->load('items'),
                    'created' => false,
                ];
            }

            $type = null;
            $targetDocument = null;
            $mode = 'unassigned';
            if (
                $data['placement'] === 'unassigned'
                && count($files) !== 1
            ) {
                throw ValidationException::withMessages([
                    'files' =>
                        'Нераспределённые документы загружаются по одному файлу.',
                ]);
            }
            if ($data['placement'] === 'typed') {
                $type = DocumentType::query()
                    ->where('is_active', true)
                    ->findOrFail($data['document_type_id']);

                if (!$type->allows_upload) {
                    throw ValidationException::withMessages([
                        'document_type_id' =>
                            'Для выбранного типа загрузка запрещена.',
                    ]);
                }

                $mode = $type->is_photo
                    ? 'photo_set'
                    : 'document';

                if (!$type->is_photo && count($files) !== 1) {
                    throw ValidationException::withMessages([
                        'files' =>
                            'Документ загружается одним файлом. '
                            . 'Для следующего документа создайте новую загрузку.',
                    ]);
                }

                if ($type->is_photo) {
                    $this->validatePhotoExtensions($files);
                }
                if ($type->is_photo) {
                    $archiveExtensions = ['zip', 'rar', '7z'];

                    $archiveFiles = array_filter(
                        $files,
                        function (array $file) use ($archiveExtensions): bool {
                            $extension = strtolower(
                                pathinfo(
                                    $file['original_name'],
                                    PATHINFO_EXTENSION
                                )
                            );

                            return in_array(
                                $extension,
                                $archiveExtensions,
                                true
                            );
                        }
                    );

                    if (count($archiveFiles) > 0 && count($files) !== 1) {
                        throw ValidationException::withMessages([
                            'files' =>
                                'Готовый архив загружается отдельно. '
                                . 'Выберите один архив либо несколько фотографий.',
                        ]);
                    }

                    if (count($archiveFiles) > 0 && !empty($data['document_id'])) {
                        throw ValidationException::withMessages([
                            'document_id' =>
                                'Готовый архив нужно загрузить отдельным документом. '
                                . 'Импорт его фотографий в существующий комплект '
                                . 'будет отдельным действием.',
                        ]);
                    }
                }
                if (!empty($data['document_id'])) {
                    /*
                     * Пока пополнение разрешаем только
                     * для существующего комплекта фото.
                     *
                     * Версии обычных документов подключим
                     * отдельным действием.
                     */
                    if (!$type->is_photo) {
                        throw ValidationException::withMessages([
                            'document_id' =>
                                'Добавление в существующий документ '
                                . 'пока доступно только для фото.',
                        ]);
                    }

                    $targetDocument = Document::query()
                        ->where(
                            'application_id',
                            $lockedApplication->id
                        )
                        ->where('document_type_id', $type->id)
                        ->where('is_archived', false)
                        ->where('lifecycle_status', '!=', 'deleted')
                        ->lockForUpdate()
                        ->findOrFail($data['document_id']);
                }
            }

            $documentDate = $data['document_date']
                ?? now()->toDateString();

            $defaultTitle = $type
                ? $type->name . ' · '
                . \Carbon\CarbonImmutable::parse($documentDate)
                    ->format('d.m.Y')
                : null;

            $documentTitle = trim((string) ($data['title'] ?? ''));

            if ($documentTitle === '') {
                $documentTitle = $defaultTitle;
            }

            $batch = DocumentUploadBatch::create([
                'application_id' => $lockedApplication->id,

                'document_id' => $targetDocument?->id,
                'document_type_id' => $type?->id,

                'batch_type' => $mode,
                'status' => 'pending',

                'total_count' => count($files),
                'uploaded_count' => 0,
                'processed_count' => 0,
                'failed_count' => 0,

                'created_by_user_id' => $user->id,
                'client_batch_key' => $data['client_batch_key'],

                'context' => [
                    'manifest_hash' => $manifestHash,
                    'total_bytes' => $totalBytes,

                    /*
                     * Настройки фиксируются при создании пакета.
                     * Смена .env не должна менять уже начатую
                     * загрузку.
                     */
                    'storage_disk' => config('documents.disk'),
                    'temporary_disk' =>
                        config('documents.temporary_disk'),
                    'chunk_size_bytes' => (int) config(
                        'documents.uploads.chunk_size_bytes'
                    ),

                    /*
                     * Добавление в существующий комплект
                     * не переименовывает его автоматически.
                     */
                    'title' => $targetDocument
                        ? $targetDocument->title
                        : $documentTitle,

                    'document_date' => $targetDocument
                        ? $targetDocument->document_date?->format('Y-m-d')
                        : $documentDate,
                ],
            ]);

            $batch->items()->createMany(
                array_map(
                    fn(array $file): array => [
                        'client_file_key' =>
                            $file['client_file_key'],
                        'original_name' =>
                            $file['original_name'],
                        'file_size' =>
                            $file['file_size'],
                        'mime_type' =>
                            $file['mime_type'],
                        'status' => 'pending',
                    ],
                    $files
                )
            );

            return [
                'batch' => $batch->load('items'),
                'created' => true,
            ];
        });
    }

    private function normalizeFiles(array $files): array
    {
        $allowed = config(
            'documents.uploads.allowed_extensions',
            []
        );

        $result = [];

        foreach ($files as $index => $file) {
            $name = trim($file['name']);

            /*
             * Не принимаем путь вместо имени файла.
             * Физические пути всё равно будут формироваться
             * только на сервере.
             */
            if (
                $name === ''
                || $name === '.'
                || $name === '..'
                || str_contains($name, '/')
                || str_contains($name, '\\')
                || preg_match('/[\x00-\x1F\x7F]/', $name)
            ) {
                throw ValidationException::withMessages([
                    "files.{$index}.name" =>
                        'Некорректное имя файла.',
                ]);
            }

            $extension = mb_strtolower(
                pathinfo($name, PATHINFO_EXTENSION)
            );

            if (!in_array($extension, $allowed, true)) {
                throw ValidationException::withMessages([
                    "files.{$index}.name" =>
                        "Формат файла «{$name}» не разрешён.",
                ]);
            }

            $result[] = [
                'client_file_key' => $file['client_file_key'],
                'original_name' => $name,
                'file_size' => (int) $file['size'],
                'mime_type' => $file['mime_type'] ?? null,
            ];
        }

        return $result;
    }

    private function validatePhotoExtensions(array $files): void
    {
        $allowed = config(
            'documents.photos.upload_extensions',
            []
        );

        foreach ($files as $file) {
            $extension = mb_strtolower(
                pathinfo(
                    $file['original_name'],
                    PATHINFO_EXTENSION
                )
            );

            if (!in_array($extension, $allowed, true)) {
                throw ValidationException::withMessages([
                    'files' =>
                        'В комплект фотографий можно добавить '
                        . 'изображения или архив с фотографиями.',
                ]);
            }
        }
    }
}
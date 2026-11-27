<?php

namespace App\Services\Documents;

use App\Models\ArchiveEntry;
use App\Models\DocumentFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use RuntimeException;
use Throwable;

class ArchiveIndexService
{
    public function __construct(
        protected ArchivePathSafetyService $paths
    ) {
    }

    public function index(DocumentFile $archive): void
    {
        $extension = strtolower(
            (string) $archive->extension
        );

        if (in_array($extension, ['gz', 'tgz', 'bz2', 'xz'], true)) {
            throw new RuntimeException(
                'Просмотр сжатого TAR пока не подключён. '
                . 'Скачайте исходный архив или загрузите TAR без внешнего сжатия.'
            );
        }

        $allowed = config(
            'documents.archives.allowed_extensions',
            []
        );

        if (!in_array($extension, $allowed, true)) {
            throw new RuntimeException(
                "Формат .$extension не поддерживается."
            );
        }

        $archive->update([
            'archive_index_status' => 'processing',
            'archive_index_error' => null,
        ]);

        $temporaryPath = $this->copyToTemporaryFile($archive);

        try {
            $blocks = $this->listArchive($temporaryPath);

            $entries = $this->parseBlocks(
                $blocks,
                $archive->id
            );

            DB::transaction(function () use ($archive, $entries) {
                ArchiveEntry::query()
                    ->where('archive_file_id', $archive->id)
                    ->delete();

                if ($entries !== []) {
                    ArchiveEntry::insert($entries);
                }

                $archive->forceFill([
                    'archive_index_status' => 'ready',
                    'archive_indexed_at' => now(),
                    'archive_index_error' => null,
                ])->save();
            });
        } catch (Throwable $error) {
            $archive->forceFill([
                'archive_index_status' => 'failed',
                'archive_index_error' => $this->safeError(
                    $error->getMessage()
                ),
            ])->save();

            throw $error;
        } finally {
            @unlink($temporaryPath);
        }
    }

    protected function copyToTemporaryFile(
        DocumentFile $archive
    ): string {
        $temporaryPath = tempnam(
            sys_get_temp_dir(),
            'mandarin-archive-'
        );

        if (!$temporaryPath) {
            throw new RuntimeException(
                'Не удалось создать временный файл архива.'
            );
        }

        try {
            $disk = Storage::disk($archive->storage_disk);
            $source = $disk->readStream($archive->storage_path);

            if (!is_resource($source)) {
                throw new RuntimeException(
                    'Не удалось открыть архив в хранилище.'
                );
            }

            $destination = fopen($temporaryPath, 'wb');

            if ($destination === false) {
                fclose($source);

                throw new RuntimeException(
                    'Не удалось создать временную копию архива.'
                );
            }

            try {
                $copied = stream_copy_to_stream(
                    $source,
                    $destination
                );
            } finally {
                fclose($source);
                fclose($destination);
            }

            if (
                $copied === false
                || $copied !== (int) $archive->file_size
            ) {
                throw new RuntimeException(
                    'Размер временной копии архива не совпадает.'
                );
            }

            return $temporaryPath;
        } catch (Throwable $error) {
            @unlink($temporaryPath);

            throw $error;
        }
    }

    protected function listArchive(string $path): array
    {
        $binary = (string) config(
            'documents.archives.executable'
        );

        if ($binary === '') {
            throw new RuntimeException(
                'Не задан путь к 7-Zip.'
            );
        }

        $process = new Process([
            $binary,
            'l',
            '-slt',
            '-sccUTF-8',
            '-y',
            '--',
            $path,
        ]);

        $process->setTimeout(900);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException(
                '7-Zip не смог прочитать архив.'
            );
        }

        $output = $process->getOutput();

        if ($output === '') {
            throw new RuntimeException(
                '7-Zip вернул пустой каталог архива.'
            );
        }

        return $this->splitBlocks($output);
    }

    protected function splitBlocks(string $output): array
    {
        $lines = preg_split('/\r\n|\n|\r/', $output);

        $entriesStarted = false;
        $blocks = [];
        $current = [];

        foreach ($lines as $line) {
            /*
             * До этого разделителя находятся сведения
             * о самом архиве, а не его содержимое.
             */
            if (trim($line) === '----------') {
                $entriesStarted = true;
                $current = [];
                continue;
            }

            if (!$entriesStarted) {
                continue;
            }

            if (trim($line) === '') {
                if ($current !== []) {
                    $blocks[] = $current;
                    $current = [];
                }

                continue;
            }

            /*
             * Не trim() всей строки:
             * пробелы могут быть частью имени элемента.
             */
            $pair = explode(' = ', $line, 2);

            if (count($pair) !== 2) {
                continue;
            }

            $key = trim($pair[0]);

            /*
             * Два поля Path без пустого разделителя
             * также считаем началом новой записи.
             */
            if ($key === 'Path' && isset($current['Path'])) {
                $blocks[] = $current;
                $current = [];
            }

            $current[$key] = $pair[1];
        }

        if ($current !== []) {
            $blocks[] = $current;
        }

        if (!$entriesStarted) {
            throw new RuntimeException(
                'Не удалось распознать каталог архива.'
            );
        }

        return $blocks;
    }

    protected function parseBlocks(
        array $blocks,
        int $archiveFileId
    ): array {
        $maxEntries = (int) config(
            'documents.archives.max_entries',
            10000
        );

        $maxEntryBytes = (int) config(
            'documents.archives.max_entry_bytes',
            209715200
        );

        $maxTotalBytes = (int) config(
            'documents.archives.max_uncompressed_bytes',
            32212254720
        );

        $entries = [];
        $seenPaths = [];
        $totalBytes = 0;

        foreach ($blocks as $block) {
            if (!isset($block['Path'])) {
                continue;
            }

            /*
             * Блок с физическим описанием самого архива
             * обычно не содержит Size.
             */
            if (!array_key_exists('Size', $block)) {
                continue;
            }

            if (count($entries) >= $maxEntries) {
                throw new RuntimeException(
                    'В архиве слишком много элементов.'
                );
            }

            $sourcePath = (string) $block['Path'];
            $rawPath = str_replace('\\', '/', $sourcePath);

            $isDirectory = ($block['Folder'] ?? '') === '+'
                || str_ends_with($rawPath, '/');

            $isSymlink = isset($block['Symbolic Link'])
                || isset($block['Hard Link']);

            $size = max(
                0,
                (int) ($block['Size'] ?? 0)
            );

            $packedSize = max(
                0,
                (int) ($block['Packed Size'] ?? 0)
            );

            $totalBytes += $size;

            if ($size > $maxEntryBytes) {
                throw new RuntimeException(
                    'Один элемент архива превышает лимит размера.'
                );
            }

            if ($totalBytes > $maxTotalBytes) {
                throw new RuntimeException(
                    'Распакованный размер архива превышает лимит.'
                );
            }

            $safe = true;
            $displayPath = trim($rawPath);
            $status = 'indexed';

            try {
                $displayPath = $this->paths->normalize(
                    $rawPath
                );
            } catch (Throwable) {
                $safe = false;
                $status = 'unsafe';

                $displayPath = mb_substr(
                    $displayPath,
                    0,
                    1000
                );
            }

            if ($isSymlink) {
                $safe = false;
                $status = 'unsafe';
            }

            /*
             * Дубликаты имён допустимы в архиве.
             * Для отображения добавляем суффикс,
             * но исходный путь сохраняем отдельно в error
             * до появления отдельного source_path.
             */
            /*
             * Пока адресуем элементы по пути.
             * Архив с одинаковыми нормализованными путями
             * отклоняем, чтобы не извлечь не тот файл.
             */
            $pathHash = hash('sha256', $displayPath);

            if (isset($seenPaths[$pathHash])) {
                throw new RuntimeException(
                    'В архиве есть повторяющиеся пути. '
                    . 'Просмотр такого архива пока не поддерживается.'
                );
            }

            $seenPaths[$pathHash] = true;
            $extension = strtolower(
                pathinfo(
                    $displayPath,
                    PATHINFO_EXTENSION
                )
            );

            $mime = $isDirectory
                ? null
                : $this->detectMime($extension);

            $previewable = $safe
                && !$isDirectory
                && !$isSymlink
                && in_array(
                    $mime,
                    config(
                        'documents.archives.preview_mimes',
                        []
                    ),
                    true
                );

            $entries[] = [
                'archive_file_id' => $archiveFileId,
                'entry_path' => $displayPath,
                'entry_path_hash' => $pathHash,
                'entry_name' => basename($displayPath),
                'entry_type' => $isDirectory
                    ? 'directory'
                    : ($isSymlink ? 'symlink' : 'file'),
                'extension' => $extension ?: null,
                'mime_type' => $mime,
                'compressed_size' => $packedSize,
                'uncompressed_size' => $size,
                'depth' => $this->paths->depth($displayPath),
                'is_safe' => $safe,
                'is_previewable' => $previewable,
                'status' => $status,
                'error' => $safe
                    ? null
                    : 'Элемент нельзя извлекать автоматически.',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        return $entries;
    }

    protected function detectMime(
        string $extension
    ): ?string {
        return match ($extension) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'txt', 'log', 'csv' => 'text/plain',
            default => null,
        };
    }

    protected function safeError(string $message): string
    {
        $message = preg_replace(
            '/[^\P{C}\n\t]+/u',
            '',
            $message
        );

        return mb_substr(
            trim($message ?: 'Ошибка индексации архива.'),
            0,
            1000
        );
    }
}
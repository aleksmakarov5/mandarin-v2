<?php

namespace App\Services\Documents;

use App\Models\Document;
use App\Models\DocumentFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class OfficePreviewService
{
    private const EXTENSIONS = [
        'docx',
        'doc',
        'odt',
        'rtf',
    ];

    public function supports(DocumentFile $file): bool
    {
        return in_array(
            strtolower((string) $file->extension),
            self::EXTENSIONS,
            true
        );
    }

    public function errorKey(int $fileId): string
    {
        return 'documents:office-preview:' . $fileId . ':error';
    }

    public function ready(DocumentFile $source): ?DocumentFile
    {
        $preview = DocumentFile::query()
            ->where('document_id', $source->document_id)
            ->where('parent_file_id', $source->id)
            ->where('file_role', 'preview_pdf')
            ->where('is_current', true)
            ->where('processing_status', 'ready')
            ->latest('id')
            ->first();

        if (!$preview) {
            return null;
        }

        return Storage::disk($preview->storage_disk)
            ->exists($preview->storage_path)
            ? $preview
            : null;
    }

    public function build(int $fileId): void
    {
        $source = DocumentFile::query()
            ->where('is_current', true)
            ->where('processing_status', 'ready')
            ->find($fileId);

        if (!$source) {
            return;
        }

        $document = Document::query()
            ->where('is_archived', false)
            ->where('lifecycle_status', '!=', 'deleted')
            ->find($source->document_id);

        if (!$document || !$this->supports($source)) {
            return;
        }

        if ($this->ready($source)) {
            return;
        }

        $maximum = (int) config(
            'documents.office.max_source_bytes',
            100 * 1024 * 1024
        );

        if ($source->file_size > $maximum) {
            throw new RuntimeException(
                'Документ слишком большой для автоматического просмотра.'
            );
        }

        $executable = (string) config(
            'documents.office.executable'
        );

        if (!is_file($executable)) {
            throw new RuntimeException(
                'Не найден исполняемый файл LibreOffice.'
            );
        }

        $directory = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'mandarin-office-'
            . Str::uuid();

        $targetPath = null;
        $registered = false;

        // Представление храним на том же закрытом диске.
        $targetDisk = $source->storage_disk;

        try {
            if (!mkdir($directory, 0700, true) && !is_dir($directory)) {
                throw new RuntimeException(
                    'Не удалось создать рабочий каталог просмотра.'
                );
            }

            $outputDirectory = $directory . DIRECTORY_SEPARATOR . 'output';
            $profileDirectory = $directory . DIRECTORY_SEPARATOR . 'profile';

            foreach ([$outputDirectory, $profileDirectory] as $path) {
                if (!mkdir($path, 0700, true) && !is_dir($path)) {
                    throw new RuntimeException(
                        'Не удалось подготовить рабочий каталог LibreOffice.'
                    );
                }
            }

            $extension = strtolower((string) $source->extension);

            $inputPath = $directory
                . DIRECTORY_SEPARATOR
                . 'input.'
                . $extension;

            $input = Storage::disk($source->storage_disk)
                ->readStream($source->storage_path);

            if (!is_resource($input)) {
                throw new RuntimeException(
                    'Не удалось открыть исходный документ.'
                );
            }

            $output = fopen($inputPath, 'wb');

            if ($output === false) {
                fclose($input);

                throw new RuntimeException(
                    'Не удалось создать рабочую копию документа.'
                );
            }

            try {
                // Ограничиваем фактическое чтение, а не только поле БД.
                $copied = stream_copy_to_stream(
                    $input,
                    $output,
                    $maximum + 1
                );
            } finally {
                fclose($input);
                fclose($output);
            }

            if (
                $copied === false
                || $copied > $maximum
                || $copied !== (int) $source->file_size
            ) {
                throw new RuntimeException(
                    'Размер исходного документа не прошёл проверку.'
                );
            }

            /*
             * Отдельный профиль на каждую конвертацию:
             * параллельные задачи не используют общий профиль.
             */
            $process = new Process([
                $executable,
                '-env:UserInstallation=' . $this->fileUri($profileDirectory),
                '--headless',
                '--nologo',
                '--nodefault',
                '--nofirststartwizard',
                '--norestore',
                '--convert-to',
                'pdf:writer_pdf_Export',
                '--outdir',
                $outputDirectory,
                $inputPath,
            ]);

            $process->setTimeout(
                (float) config('documents.office.timeout', 150)
            );

            $process->run();

            $pdfPath = $outputDirectory
                . DIRECTORY_SEPARATOR
                . 'input.pdf';

            if (!$process->isSuccessful() || !is_file($pdfPath)) {
                throw new RuntimeException(
                    'LibreOffice не смог подготовить PDF. '
                    . 'Документ может быть повреждён или защищён паролем.'
                );
            }

            clearstatcache(true, $pdfPath);

            $size = filesize($pdfPath);
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($pdfPath);
            $sha256 = hash_file('sha256', $pdfPath);

            if (
                $size === false
                || $size <= 0
                || $mime !== 'application/pdf'
                || !$sha256
            ) {
                throw new RuntimeException(
                    'Результат преобразования не является корректным PDF.'
                );
            }

            $targetPath = sprintf(
                'applications/%d/documents/previews/%d/%s.pdf',
                $document->application_id,
                $source->id,
                Str::uuid()
            );

            $pdf = fopen($pdfPath, 'rb');

            if ($pdf === false) {
                throw new RuntimeException(
                    'Не удалось открыть подготовленный PDF.'
                );
            }

            try {
                $stored = Storage::disk($targetDisk)->put(
                    $targetPath,
                    $pdf,
                    ['visibility' => 'private']
                );
            } finally {
                fclose($pdf);
            }

            if (
                !$stored
                || (int) Storage::disk($targetDisk)->size($targetPath) !== $size
            ) {
                throw new RuntimeException(
                    'Не удалось сохранить представление документа.'
                );
            }

            $registered = DB::transaction(function () use ($source, $targetDisk, $targetPath, $size, $sha256): bool {
                $document = Document::query()
                    ->lockForUpdate()
                    ->find($source->document_id);

                if (
                    !$document
                    || $document->is_archived
                    || $document->lifecycle_status === 'deleted'
                ) {
                    return false;
                }

                $currentSource = DocumentFile::query()
                    ->where('document_id', $document->id)
                    ->where('is_current', true)
                    ->where('processing_status', 'ready')
                    ->lockForUpdate()
                    ->find($source->id);

                if (!$currentSource) {
                    return false;
                }

                $existing = DocumentFile::query()
                    ->where('document_id', $document->id)
                    ->where('parent_file_id', $source->id)
                    ->where('file_role', 'preview_pdf')
                    ->where('is_current', true)
                    ->where('processing_status', 'ready')
                    ->first();

                if (
                    $existing
                    && Storage::disk($existing->storage_disk)
                        ->exists($existing->storage_path)
                ) {
                    return false;
                }

                DocumentFile::query()
                    ->where('parent_file_id', $source->id)
                    ->where('file_role', 'preview_pdf')
                    ->where('is_current', true)
                    ->update(['is_current' => false]);

                DocumentFile::create([
                    'document_id' => $document->id,
                    'parent_file_id' => $source->id,

                    'file_role' => 'preview_pdf',
                    'original_name' => 'preview.pdf',
                    'stored_name' => basename($targetPath),

                    'storage_disk' => $targetDisk,
                    'storage_path' => $targetPath,

                    'extension' => 'pdf',
                    'mime_type' => 'application/pdf',
                    'file_size' => $size,
                    'sha256' => $sha256,

                    'processing_status' => 'ready',
                    'is_current' => true,
                    'is_inline' => false,
                ]);

                return true;
            });
        } finally {
            if ($targetPath !== null && !$registered) {
                try {
                    Storage::disk($targetDisk)->delete($targetPath);
                } catch (Throwable $error) {
                    report($error);
                }
            }

            $this->removeDirectory($directory);
        }
    }

    private function fileUri(string $path): string
    {
        $path = str_replace('\\', '/', $path);

        $encoded = implode(
            '/',
            array_map('rawurlencode', explode('/', $path))
        );

        // Сохраняем двоеточие диска в Windows.
        $encoded = str_replace('%3A', ':', $encoded);

        return str_starts_with($encoded, '/')
            ? 'file://' . $encoded
            : 'file:///' . $encoded;
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $path,
                \FilesystemIterator::SKIP_DOTS
            ),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isLink() || $item->isFile()) {
                @unlink($item->getPathname());
            } else {
                @rmdir($item->getPathname());
            }
        }

        @rmdir($path);
    }
}
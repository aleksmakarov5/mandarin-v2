<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\Documents\BuildPhotoZip;
use App\Models\Application;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentDownloadController extends Controller
{
    public function prepare(
        Request $request,
        int $applicationId,
        int $documentId
    ): JsonResponse {
        $validated = $request->validate([
            'retry' => ['sometimes', 'boolean'],
        ]);

        $document = $this->resolveDocument(
            $applicationId,
            $documentId,
            $request->user()
        );

        $originals = DocumentFile::query()
            ->where('document_id', $document->id)
            ->where('is_current', true)
            ->where('processing_status', 'ready')
            ->whereIn('file_role', [
                'original',
                'generated_docx',
                'generated_pdf',
            ]);

        $hasPhotos = (clone $originals)
            ->where('file_role', 'original')
            ->where('mime_type', 'like', 'image/%')
            ->exists();

        $isPhotoSet = (bool) $document->type?->is_photo
            && $hasPhotos;

        if ($isPhotoSet) {
            $file = DocumentFile::query()
                ->where('document_id', $document->id)
                ->where('file_role', 'archive')
                ->where('is_current', true)
                ->where('processing_status', 'ready')
                ->latest('id')
                ->first();

            $current = $document->archive_status === 'ready'
                && $document->archive_revision !== null
                && (int) $document->archive_revision
                === (int) $document->content_revision
                && $file !== null;

            if (!$current) {
                if (
                    $document->archive_status === 'failed'
                    && !($validated['retry'] ?? false)
                ) {
                    return response()->json([
                        'message' =>
                            'Не удалось собрать архив. Нажмите скачивание повторно.',
                    ], 422);
                }

                /*
                 * Уникальная задача не позволяет поставить
                 * несколько одинаковых сборок одновременно.
                 *
                 * pending повторно отправляем для восстановления
                 * после возможного сбоя постановки в очередь.
                 */
                if ($document->archive_status !== 'building') {
                    BuildPhotoZip::dispatch($document->id)
                        ->onQueue('documents');
                }

                return response()->json([
                    'data' => ['status' => 'processing'],
                ], 202)->header('Cache-Control', 'private, no-store');
            }
        } else {
            $files = $originals
                ->orderBy('sort_order')
                ->orderBy('id')
                ->limit(2)
                ->get();

            abort_if($files->isEmpty(), 404, 'Готовых файлов нет.');

            /*
             * Для старых многофайловых документов не выбираем
             * произвольный файл вместо нужного пользователю.
             */
            abort_if(
                $files->count() > 1,
                409,
                'В документе несколько файлов. '
                . 'Скачайте нужный файл из окна просмотра.'
            );

            $file = $files->first();
        }

        $url = URL::temporarySignedRoute(
            'api.documents.download.content',
            now()->addMinutes(5),
            [
                'applicationId' => $applicationId,
                'documentId' => $documentId,
                'fileId' => $file->id,
                'download_user' => $request->user()->id,
            ],
            absolute: false
        );

        return response()->json([
            'data' => [
                'status' => 'ready',
                'url' => $url,
            ],
        ])->header('Cache-Control', 'private, no-store');
    }

    public function content(
        Request $request,
        int $applicationId,
        int $documentId,
        int $fileId
    ): StreamedResponse {
        abort_unless(
            $request->hasValidRelativeSignature(),
            403,
            'Ссылка скачивания недействительна или истекла.'
        );

        $userId = filter_var(
            $request->query('download_user'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        abort_unless($userId !== false, 403);

        $document = $this->resolveDocument(
            $applicationId,
            $documentId,
            User::findOrFail($userId)
        );

        $file = DocumentFile::query()
            ->where('document_id', $document->id)
            ->where('is_current', true)
            ->where('processing_status', 'ready')
            ->whereIn('file_role', [
                'original',
                'generated_docx',
                'generated_pdf',
                'archive',
            ])
            ->findOrFail($fileId);

        if ($file->file_role === 'archive') {
            abort_unless(
                $document->archive_status === 'ready'
                && $document->archive_revision !== null
                && (int) $document->archive_revision
                === (int) $document->content_revision,
                409,
                'Архив устарел. Запросите скачивание повторно.'
            );
        }

        $disk = Storage::disk($file->storage_disk);

        abort_unless(
            $disk->exists($file->storage_path),
            404,
            'Файл отсутствует в хранилище.'
        );

        $extension = strtolower((string) $file->extension);

        $name = trim((string) $document->title);

        if ($name === '') {
            $name = 'document-' . $document->id;
        }

        $name = str_replace(
            ['/', '\\', "\r", "\n", "\0"],
            '_',
            $name
        );

        $name = preg_replace('/[\x00-\x1F\x7F]/u', '_', $name)
            ?: 'document-' . $document->id;

        if (
            $extension !== ''
            && !str_ends_with(strtolower($name), '.' . $extension)
        ) {
            $name .= '.' . $extension;
        }

        $disposition = HeaderUtils::makeDisposition(
            'attachment',
            $name,
            'document-' . $document->id
            . ($extension !== '' ? '.' . $extension : '')
        );

        $stream = $disk->readStream($file->storage_path);

        abort_unless(is_resource($stream), 404);

        return response()->stream(
            function () use ($stream): void {
                try {
                    fpassthru($stream);
                } finally {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }
            },
            200,
            [
                'Content-Type' => 'application/octet-stream',
                'Content-Disposition' => $disposition,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
                'Referrer-Policy' => 'no-referrer',
            ]
        );
    }

    private function resolveDocument(
        int $applicationId,
        int $documentId,
        User $user
    ): Document {
        $application = Application::findOrFail($applicationId);

        Gate::forUser($user)->authorize('view', $application);
        Gate::forUser($user)->authorize(
            'viewDocuments',
            $application
        );

        return Document::query()
            ->where('application_id', $applicationId)
            ->where('is_archived', false)
            ->where('lifecycle_status', '!=', 'deleted')
            ->with('type')
            ->findOrFail($documentId);
    }
}
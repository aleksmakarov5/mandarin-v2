<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PhotoGalleryMediaController extends Controller
{
    public function show(
        Request $request,
        int $applicationId,
        int $documentId,
        int $fileId,
        string $kind
    ): StreamedResponse {
        abort_unless(
            $request->hasValidRelativeSignature(),
            403,
            'Ссылка недействительна или истекла.'
        );

        abort_unless(
            in_array($kind, ['thumbnail', 'main'], true),
            404
        );

        $userId = filter_var(
            $request->query('preview_user'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        abort_unless($userId !== false, 403);

        $user = User::findOrFail($userId);
        $application = Application::findOrFail($applicationId);

        Gate::forUser($user)->authorize('view', $application);
        Gate::forUser($user)->authorize(
            'viewDocuments',
            $application
        );

        $document = Document::query()
            ->where('application_id', $applicationId)
            ->where('is_archived', false)
            ->where('lifecycle_status', '!=', 'deleted')
            ->whereHas('type', function ($query): void {
                $query->where('is_photo', true);
            })
            ->findOrFail($documentId);

        /*
         * Сначала проверяем основную фотографию.
         * fileId всегда означает ID основной фотографии,
         * даже при запросе миниатюры.
         */
        $photo = DocumentFile::query()
            ->where('document_id', $document->id)
            ->where('file_role', 'original')
            ->where('is_current', true)
            ->where('processing_status', 'ready')
            ->where('mime_type', 'like', 'image/%')
            ->findOrFail($fileId);

        $file = $kind === 'main'
            ? $photo
            : DocumentFile::query()
                ->where('document_id', $document->id)
                ->where('parent_file_id', $photo->id)
                ->where('file_role', 'thumbnail')
                ->where('is_current', true)
                ->where('processing_status', 'ready')
                ->where('mime_type', 'image/jpeg')
                ->orderByDesc('id')
                ->firstOrFail();

        /*
         * Только безопасные растровые форматы.
         * Новые основные фотографии и миниатюры — JPEG.
         */
        abort_unless(
            in_array($file->mime_type, [
                'image/jpeg',
                'image/png',
                'image/webp',
                'image/avif',
            ], true),
            415,
            'Формат не поддерживается просмотрщиком.'
        );

        abort_unless(
            filled($file->storage_disk)
            && filled($file->storage_path),
            404
        );

        $disk = Storage::disk($file->storage_disk);

        abort_unless(
            $disk->exists($file->storage_path),
            404,
            'Изображение отсутствует в хранилище.'
        );

        $stream = $disk->readStream($file->storage_path);

        abort_unless(
            is_resource($stream),
            404,
            'Не удалось открыть изображение.'
        );

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
                'Content-Type' => $file->mime_type,
                'Content-Disposition' => 'inline',
                'X-Content-Type-Options' => 'nosniff',
                'Referrer-Policy' => 'no-referrer',
                'Cache-Control' => 'private, no-store',
            ]
        );
    }
}
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\Documents\BuildPhotoPresentation;
use App\Models\Application;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\User;
use App\Services\Documents\PhotoPresentationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class PhotoPresentationController extends Controller
{
    public function prepare(
        Request $request,
        int $applicationId,
        int $documentId,
        int $fileId,
        PhotoPresentationService $service
    ): JsonResponse {
        $file = $this->resolve(
            $applicationId,
            $documentId,
            $fileId,
            $request->user()
        );

        $key = $service->cacheKey($file);

        $validated = $request->validate([
            'retry' => ['sometimes', 'boolean'],
        ]);

        if ($validated['retry'] ?? false) {
            Cache::forget($key . ':error');
        }

        if ($service->ready($file)) {
            $urls = [];

            foreach (['thumbnail', 'screen'] as $kind) {
                $urls[$kind] = URL::temporarySignedRoute(
                    'api.documents.photos.presentation',
                    now()->addMinutes(15),
                    [
                        'applicationId' => $applicationId,
                        'documentId' => $documentId,
                        'fileId' => $fileId,
                        'kind' => $kind,
                        'preview_user' => $request->user()->id,
                    ],
                    absolute: false
                );
            }

            return response()->json([
                'data' => [
                    'status' => 'ready',
                    'file_id' => $fileId,
                    'thumbnail_url' => $urls['thumbnail'],
                    'screen_url' => $urls['screen'],
                ],
            ])->header('Cache-Control', 'private, no-store');
        }

        if ($message = Cache::get($key . ':error')) {
            return response()->json([
                'data' => [
                    'status' => 'failed',
                    'file_id' => $fileId,
                ],
                'message' => $message,
            ], 422)->header('Cache-Control', 'private, no-store');
        }

        /*
         * Повторный опрос не создаёт новую задачу,
         * пока действует отметка постановки в очередь.
         */
        if (
            Cache::add(
                $key . ':queued',
                true,
                now()->addHour()
            )
        ) {
            try {
                BuildPhotoPresentation::dispatch(
                    $fileId,
                    $key
                )->onQueue('document-photos');
            } catch (Throwable $error) {
                Cache::forget($key . ':queued');
                throw $error;
            }
        }

        return response()->json([
            'data' => [
                'status' => 'processing',
                'file_id' => $fileId,
                'retry_after' => 3,
            ],
        ], 202)->header('Cache-Control', 'private, no-store');
    }

    public function show(
        Request $request,
        int $applicationId,
        int $documentId,
        int $fileId,
        string $kind,
        PhotoPresentationService $service
    ): StreamedResponse {
        abort_unless(
            $request->hasValidRelativeSignature(),
            403
        );

        abort_unless(
            in_array($kind, ['thumbnail', 'screen'], true),
            404
        );

        $userId = filter_var(
            $request->query('preview_user'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        abort_unless($userId !== false, 403);

        $user = User::findOrFail($userId);

        /*
         * Повторная проверка доступа при каждом открытии.
         * Подпись не заменяет проверку прав пользователя.
         */
        $file = $this->resolve(
            $applicationId,
            $documentId,
            $fileId,
            $user
        );

        $paths = $service->paths($file);
        $disk = Storage::disk($file->storage_disk);

        abort_unless($disk->exists($paths[$kind]), 404);

        $stream = $disk->readStream($paths[$kind]);

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
                'Content-Type' => 'image/jpeg',
                'Content-Disposition' => 'inline',
                'X-Content-Type-Options' => 'nosniff',
                'Referrer-Policy' => 'no-referrer',
                'Cache-Control' => 'private, no-store',
            ]
        );
    }

    private function resolve(
        int $applicationId,
        int $documentId,
        int $fileId,
        User $user
    ): DocumentFile {
        $application = Application::findOrFail($applicationId);

        Gate::forUser($user)->authorize('view', $application);
        Gate::forUser($user)->authorize(
            'viewDocuments',
            $application
        );

        /*
         * Сохраняем действующую модель доступа,
         * используемую текущим просмотрщиком документов.
         */
        $document = Document::query()
            ->where('application_id', $applicationId)
            ->where('is_archived', false)
            ->where('lifecycle_status', '!=', 'deleted')
            ->whereHas('type', function ($query): void {
                $query->where('is_photo', true);
            })
            ->findOrFail($documentId);

        return DocumentFile::query()
            ->where('document_id', $document->id)
            ->where('file_role', 'original')
            ->where('is_current', true)
            ->where('processing_status', 'ready')
            ->where('mime_type', 'like', 'image/%')
            ->findOrFail($fileId);
    }
}
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Document;
use App\Models\DocumentFile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\URL;

class PhotoGalleryController extends Controller
{
    public function index(
        int $applicationId,
        int $documentId
    ): JsonResponse {
        $application = Application::findOrFail($applicationId);

        Gate::authorize('view', $application);
        Gate::authorize('viewDocuments', $application);

        $data = DB::transaction(function () use ($applicationId, $documentId, $application): array {
            $document = $this->galleryQuery(
                $applicationId,
                $documentId
            )
                ->lockForUpdate()
                ->firstOrFail();

            $photos = $this->photosQuery($document->id)
                ->where('processing_status', 'ready')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get([
                    'id',
                    'original_name',
                    'extension',
                    'mime_type',
                    'file_size',
                    'width',
                    'height',
                ]);

            /*
             * Один запрос миниатюр для всего комплекта.
             * Не выполняем запрос отдельно для каждой фотографии.
             */
            $thumbnails = DocumentFile::query()
                ->where('document_id', $document->id)
                ->whereIn('parent_file_id', $photos->pluck('id'))
                ->where('file_role', 'thumbnail')
                ->where('is_current', true)
                ->where('processing_status', 'ready')
                ->where('mime_type', 'image/jpeg')
                ->orderByDesc('id')
                ->get([
                    'id',
                    'parent_file_id',
                ])
                ->unique('parent_file_id')
                ->keyBy('parent_file_id');

            $expiresAt = now()->addHour();
            $userId = auth()->id();

            $photoItems = $photos->values()->map(
                function (DocumentFile $photo, int $index) use ($applicationId, $documentId, $thumbnails, $expiresAt, $userId): array {
                    $thumbnail = $thumbnails->get($photo->id);

                    $parameters = [
                        'applicationId' => $applicationId,
                        'documentId' => $documentId,
                        'fileId' => $photo->id,
                        'preview_user' => $userId,
                    ];

                    return [
                        'id' => (int) $photo->id,
                        'original_name' => $photo->original_name,
                        'extension' => $photo->extension,
                        'mime_type' => $photo->mime_type,
                        'file_size' => (int) $photo->file_size,
                        'width' => $photo->width,
                        'height' => $photo->height,
                        'position' => $index + 1,

                        'thumbnail_url' => $thumbnail
                            ? URL::temporarySignedRoute(
                                'api.documents.gallery.media',
                                $expiresAt,
                                array_merge($parameters, [
                                    'kind' => 'thumbnail',
                                ]),
                                absolute: false
                            )
                            : null,

                        /*
                         * Крупный просмотр использует основной JPEG.
                         * Дополнительную screen-копию не создаём.
                         */
                        'screen_url' => URL::temporarySignedRoute(
                            'api.documents.gallery.media',
                            $expiresAt,
                            array_merge($parameters, [
                                'kind' => 'main',
                            ]),
                            absolute: false
                        ),
                    ];
                }
            )->all();

            return [
                'id' => (int) $document->id,
                'title' => $document->title,
                'document_type_id' =>
                    (int) $document->document_type_id,

                'content_revision' =>
                    (int) $document->content_revision,

                'archive_status' => $document->archive_status,

                'archive_is_current' =>
                    $document->archive_status === 'ready'
                    && $document->archive_revision !== null
                    && (int) $document->archive_revision
                    === (int) $document->content_revision,

                'permissions' => [
                    'manage' => Gate::allows(
                        'manageDocuments',
                        $application
                    ),
                ],

                'links_expires_at' => $expiresAt->toIso8601String(),
                'photos' => $photoItems,
            ];
        });

        return response()->json([
            'data' => $data,
        ])->header('Cache-Control', 'private, no-store');
    }


    public function reorder(
        Request $request,
        int $applicationId,
        int $documentId
    ): JsonResponse {
        $application = Application::findOrFail($applicationId);

        Gate::authorize('view', $application);
        Gate::authorize('manageDocuments', $application);

        $validated = $request->validate([
            'revision' => [
                'required',
                'integer',
                'min:0',
            ],

            /*
             * Полный упорядоченный список ID комплекта.
             * Не только изменённая страница сетки.
             */
            'photo_ids' => [
                'required',
                'array',
                'min:1',
                'max:10000',
            ],

            'photo_ids.*' => [
                'required',
                'integer',
                'min:1',
                'distinct',
            ],
        ]);

        $result = DB::transaction(function () use ($applicationId, $documentId, $validated): array {
            $application = Application::query()
                ->lockForUpdate()
                ->findOrFail($applicationId);

            Gate::authorize('view', $application);
            Gate::authorize('manageDocuments', $application);

            $document = $this->galleryQuery(
                $applicationId,
                $documentId
            )
                ->lockForUpdate()
                ->firstOrFail();

            /*
             * Защита от двух одновременно открытых галерей:
             * устаревший клиент не перезаписывает новые изменения.
             */
            abort_unless(
                (int) $document->content_revision
                === (int) $validated['revision'],
                409,
                'Комплект изменён другим действием. '
                . 'Обновите галерею перед перестановкой.'
            );

            $currentIds = $this->photosQuery($document->id)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->pluck('id')
                ->map(fn($id): int => (int) $id)
                ->all();

            $requestedIds = array_map(
                'intval',
                $validated['photo_ids']
            );

            $sortedCurrent = $currentIds;
            $sortedRequested = $requestedIds;

            sort($sortedCurrent, SORT_NUMERIC);
            sort($sortedRequested, SORT_NUMERIC);

            if ($sortedCurrent !== $sortedRequested) {
                throw ValidationException::withMessages([
                    'photo_ids' =>
                        'Список должен содержать все фотографии '
                        . 'этого комплекта без пропусков и повторов.',
                ]);
            }

            if ($currentIds === $requestedIds) {
                return [
                    'revision' =>
                        (int) $document->content_revision,
                    'changed' => false,
                ];
            }

            /*
             * Обновляем пачками.
             * Не выполняем по отдельному UPDATE
             * для каждой из 500 фотографий.
             */
            foreach (array_chunk($requestedIds, 250, true) as $part) {
                $cases = [];
                $bindings = [];
                $ids = [];

                foreach ($part as $position => $photoId) {
                    $cases[] = 'WHEN ? THEN ?';
                    $bindings[] = $photoId;
                    $bindings[] = $position + 1;
                    $ids[] = $photoId;
                }

                $placeholders = implode(
                    ',',
                    array_fill(0, count($ids), '?')
                );

                $sql = 'UPDATE document_files '
                    . 'SET sort_order = CASE id '
                    . implode(' ', $cases)
                    . ' ELSE sort_order END '
                    . 'WHERE document_id = ? '
                    . 'AND deleted_at IS NULL '
                    . "AND id IN ({$placeholders})";

                $bindings[] = $document->id;

                foreach ($ids as $id) {
                    $bindings[] = $id;
                }

                DB::update($sql, $bindings);
            }

            $document->content_revision =
                (int) $document->content_revision + 1;

            /*
             * Старый ZIP не удаляем немедленно,
             * но больше не считаем актуальным.
             */
            $document->archive_status = 'pending';
            $document->archive_error = null;
            $document->updated_by_user_id = auth()->id();
            $document->save();

            return [
                'revision' => (int) $document->content_revision,
                'changed' => true,
            ];
        });

        return response()->json([
            'data' => $result,
        ])->header('Cache-Control', 'private, no-store');
    }

    private function galleryQuery(
        int $applicationId,
        int $documentId
    ): Builder {
        return Document::query()
            ->whereKey($documentId)
            ->where('application_id', $applicationId)
            ->where('is_archived', false)
            ->where('lifecycle_status', '!=', 'deleted')
            ->whereHas('type', function (Builder $query): void {
                $query->where('is_photo', true);
            });
    }

    private function photosQuery(int $documentId): Builder
    {
        return DocumentFile::query()
            ->where('document_id', $documentId)
            ->where('file_role', 'original')
            ->where('is_current', true)
            ->where('mime_type', 'like', 'image/%');
    }
}
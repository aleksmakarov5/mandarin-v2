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

class DocumentActionController extends Controller
{
    /**
     * Доступные комплекты для перемещения/копирования.
     */
    public function destinations(
        Request $request,
        int $applicationId,
        int $documentId
    ): JsonResponse {
        $application = Application::findOrFail($applicationId);

        Gate::authorize('view', $application);
        Gate::authorize('manageDocuments', $application);

        $this->photoDocuments($applicationId)
            ->findOrFail($documentId);

        $documents = $this->photoDocuments($applicationId)
            ->where('documents.id', '<>', $documentId)
            ->with('type:id,name')
            ->withCount([
                'allFiles as photos_count' => function (Builder $query): void {
                    $query
                        ->where('file_role', 'original')
                        ->where('is_current', true)
                        ->where('mime_type', 'like', 'image/%');
                },
            ])
            ->orderByDesc('document_date')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => $documents->map(fn(Document $document): array => [
                'id' => (int) $document->id,
                'title' => $document->title ?: $document->type?->name,
                'type_name' => $document->type?->name,
                'document_date' =>
                    $document->document_date?->format('Y-m-d'),
                'photos_count' => (int) $document->photos_count,
                'content_revision' => (int) $document->content_revision,
            ])->values(),
        ])->header('Cache-Control', 'private, no-store');
    }

    /**
     * Удаление / копирование / перемещение фотографий.
     */
    public function photos(
        Request $request,
        int $applicationId,
        int $documentId
    ): JsonResponse {
        $application = Application::findOrFail($applicationId);

        Gate::authorize('view', $application);
        Gate::authorize('manageDocuments', $application);

        $data = $request->validate([
            'request_key' => ['required', 'uuid'],

            'action' => ['required', 'in:delete,copy,move'],

            'revision' => ['required', 'integer', 'min:0'],

            'photo_ids' => ['required', 'array', 'min:1', 'max:500'],
            'photo_ids.*' => ['required', 'integer', 'min:1', 'distinct'],

            'target_document_id' => [
                'required_if:action,copy,move',
                'prohibited_if:action,delete',
                'integer',
                'min:1',
            ],

            'target_revision' => [
                'required_if:action,copy,move',
                'prohibited_if:action,delete',
                'integer',
                'min:0',
            ],
        ]);

        $ids = array_map('intval', $data['photo_ids']);
        sort($ids, SORT_NUMERIC);

        $hash = $this->requestHash([
            'application_id' => $applicationId,
            'document_id' => $documentId,
            'action' => $data['action'],
            'revision' => (int) $data['revision'],
            'photo_ids' => $ids,
            'target_document_id' =>
                isset($data['target_document_id'])
                ? (int) $data['target_document_id']
                : null,
            'target_revision' =>
                isset($data['target_revision'])
                ? (int) $data['target_revision']
                : null,
        ]);

        $result = DB::transaction(function () use ($applicationId, $documentId, $data, $ids, $hash, $request): array {
            /*
             * Совпадает с подходом reorder():
             * сначала блокируется дело, затем документы.
             */
            $application = Application::query()
                ->lockForUpdate()
                ->findOrFail($applicationId);

            Gate::authorize('view', $application);
            Gate::authorize('manageDocuments', $application);

            $previous = $this->previousOperation(
                $data['request_key'],
                $hash,
                $applicationId,
                $request->user()->id
            );

            if ($previous !== null) {
                return $previous;
            }

            $targetId = isset($data['target_document_id'])
                ? (int) $data['target_document_id']
                : null;

            if ($targetId === $documentId) {
                throw ValidationException::withMessages([
                    'target_document_id' =>
                        'Выберите другой фотокомплект.',
                ]);
            }

            $documentIds = array_values(array_filter([
                $documentId,
                $targetId,
            ]));

            $documents = $this->photoDocuments($applicationId)
                ->whereIn('documents.id', $documentIds)
                ->orderBy('documents.id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $source = $documents->get($documentId);

            abort_unless($source, 404);

            abort_unless(
                (int) $source->content_revision === (int) $data['revision'],
                409,
                'Исходный комплект изменён. Обновите галерею.'
            );

            $target = null;

            if ($targetId !== null) {
                $target = $documents->get($targetId);

                abort_unless($target, 404);

                abort_unless(
                    (int) $target->content_revision
                    === (int) $data['target_revision'],
                    409,
                    'Комплект назначения изменён. Выберите его повторно.'
                );
            }

            /*
             * Выбор ID ограничен конкретным исходным комплектом.
             * Порядок копирования/переноса соответствует сетке.
             */
            $photos = $this->photosQuery($source->id)
                ->where('processing_status', 'ready')
                ->whereIn('id', $ids)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($photos->count() !== count($ids)) {
                throw ValidationException::withMessages([
                    'photo_ids' =>
                        'Некоторые фотографии удалены, не готовы '
                        . 'или не принадлежат этому комплекту.',
                ]);
            }

            /*
             * Сейчас производные файлы непосредственно связаны
             * с основной фотографией через parent_file_id.
             */
            $children = DocumentFile::query()
                ->where('document_id', $source->id)
                ->whereIn('parent_file_id', $ids)
                ->lockForUpdate()
                ->get();

            $newIds = [];

            if ($data['action'] === 'delete') {
                foreach ($children as $child) {
                    $child->delete();
                }

                foreach ($photos as $photo) {
                    $photo->delete();
                }

                $this->markChanged($source);
            } else {
                $nextPosition = (int) $this->photosQuery($target->id)
                    ->max('sort_order');

                $childrenByParent = $children->groupBy('parent_file_id');

                foreach ($photos as $photo) {
                    $nextPosition++;

                    if ($data['action'] === 'move') {
                        $photo->document_id = $target->id;
                        $photo->sort_order = $nextPosition;
                        $photo->save();

                        foreach ($childrenByParent->get($photo->id, collect()) as $child) {
                            $child->document_id = $target->id;
                            $child->save();
                        }

                        $newIds[] = (int) $photo->id;
                    } else {
                        /*
                         * Новая логическая копия, тот же неизменяемый
                         * объект хранения. Никакого копирования
                         * мегабайт внутри HTTP-запроса.
                         */
                        $copy = $photo->replicate([
                            'created_at',
                            'updated_at',
                            'deleted_at',
                        ]);

                        $copy->document_id = $target->id;
                        $copy->sort_order = $nextPosition;
                        $copy->uploaded_by_user_id = auth()->id();
                        $copy->legacy_attachment_id = null;
                        $copy->save();

                        foreach ($childrenByParent->get($photo->id, collect()) as $child) {
                            $childCopy = $child->replicate([
                                'created_at',
                                'updated_at',
                                'deleted_at',
                            ]);

                            $childCopy->document_id = $target->id;
                            $childCopy->parent_file_id = $copy->id;
                            $childCopy->uploaded_by_user_id = auth()->id();
                            $childCopy->legacy_attachment_id = null;
                            $childCopy->save();
                        }

                        $newIds[] = (int) $copy->id;
                    }
                }

                $this->markChanged($target);

                if ($data['action'] === 'move') {
                    $this->markChanged($source);
                }
            }

            $result = [
                'action' => $data['action'],
                'affected_count' => count($ids),
                'source_document_id' => (int) $source->id,
                'target_document_id' => $target
                    ? (int) $target->id
                    : null,
                'result_photo_ids' => $newIds,
            ];

            $this->saveOperation(
                $data['request_key'],
                $hash,
                $applicationId,
                $request->user()->id,
                'photos.' . $data['action'],
                $result
            );

            return $result;
        });

        return response()->json([
            'data' => $result,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function rename(
        Request $request,
        int $applicationId,
        int $documentId
    ): JsonResponse {
        $data = $request->validate([
            'request_key' => ['required', 'uuid'],
            'title' => ['required', 'string', 'max:255'],
        ]);

        $title = trim($data['title']);

        if ($title === '') {
            throw ValidationException::withMessages([
                'title' => 'Введите название документа.',
            ]);
        }

        $hash = $this->requestHash([
            'application_id' => $applicationId,
            'document_id' => $documentId,
            'title' => $title,
        ]);

        $result = DB::transaction(function () use ($request, $applicationId, $documentId, $data, $title, $hash): array {
            $application = Application::query()
                ->lockForUpdate()
                ->findOrFail($applicationId);

            Gate::authorize('view', $application);
            Gate::authorize('manageDocuments', $application);

            $previous = $this->previousOperation(
                $data['request_key'],
                $hash,
                $applicationId,
                $request->user()->id
            );

            if ($previous !== null) {
                return $previous;
            }

            $document = Document::query()
                ->where('application_id', $applicationId)
                ->lockForUpdate()
                ->findOrFail($documentId);

            $oldTitle = $document->title;

            $document->title = $title;
            $document->updated_by_user_id = $request->user()->id;
            $document->save();

            $result = [
                'id' => (int) $document->id,
                'old_title' => $oldTitle,
                'title' => $title,
            ];

            $this->saveOperation(
                $data['request_key'],
                $hash,
                $applicationId,
                $request->user()->id,
                'document.rename',
                $result
            );

            return $result;
        });

        return response()->json(['data' => $result]);
    }

    public function destroy(
        Request $request,
        int $applicationId,
        int $documentId
    ): JsonResponse {
        $data = $request->validate([
            'request_key' => ['required', 'uuid'],
        ]);

        $hash = $this->requestHash([
            'application_id' => $applicationId,
            'document_id' => $documentId,
            'action' => 'delete',
        ]);

        $result = DB::transaction(function () use ($request, $applicationId, $documentId, $data, $hash): array {
            $application = Application::query()
                ->lockForUpdate()
                ->findOrFail($applicationId);

            Gate::authorize('view', $application);
            Gate::authorize('manageDocuments', $application);

            $previous = $this->previousOperation(
                $data['request_key'],
                $hash,
                $applicationId,
                $request->user()->id
            );

            if ($previous !== null) {
                return $previous;
            }

            $document = Document::query()
                ->where('application_id', $applicationId)
                ->lockForUpdate()
                ->findOrFail($documentId);

            /*
             * Не удаляем комплект, пока в него идёт загрузка.
             * Иначе отложенная публикация может конфликтовать
             * с удалением.
             */
            $hasActiveUpload = DB::table('document_upload_batches')
                ->where('application_id', $applicationId)
                ->where('document_id', $documentId)
                ->whereIn('status', [
                    'pending',
                    'uploading',
                    'processing',
                ])
                ->exists();

            abort_if(
                $hasActiveUpload,
                409,
                'В документ ещё загружаются файлы. '
                . 'Завершите загрузку перед удалением.'
            );

            $result = [
                'id' => (int) $document->id,
                'title' => $document->title,
            ];

            /*
             * Только soft delete.
             * Объекты Storage здесь не удаляются.
             */
            DocumentFile::query()
                ->where('document_id', $documentId)
                ->delete();

            $document->updated_by_user_id = $request->user()->id;
            $document->save();
            $document->delete();

            $this->saveOperation(
                $data['request_key'],
                $hash,
                $applicationId,
                $request->user()->id,
                'document.delete',
                $result
            );

            return $result;
        });

        return response()->json(['data' => $result]);
    }

    private function photoDocuments(int $applicationId): Builder
    {
        return Document::query()
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

    private function markChanged(Document $document): void
    {
        $document->content_revision =
            (int) $document->content_revision + 1;

        $document->archive_status = 'pending';
        $document->archive_error = null;
        $document->updated_by_user_id = auth()->id();

        $document->save();
    }

    private function requestHash(array $data): string
    {
        return hash(
            'sha256',
            json_encode($data, JSON_THROW_ON_ERROR)
        );
    }

    private function previousOperation(
        string $key,
        string $hash,
        int $applicationId,
        int $userId
    ): ?array {
        $operation = DB::table('document_operations')
            ->where('request_key', $key)
            ->first();

        if (!$operation) {
            return null;
        }

        abort_unless(
            (int) $operation->application_id === $applicationId
            && (int) $operation->user_id === $userId
            && hash_equals($operation->request_hash, $hash),
            409,
            'Идентификатор операции уже использован.'
        );

        return json_decode(
            $operation->result,
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }

    private function saveOperation(
        string $key,
        string $hash,
        int $applicationId,
        int $userId,
        string $action,
        array $result
    ): void {
        DB::table('document_operations')->insert([
            'request_key' => $key,
            'request_hash' => $hash,
            'application_id' => $applicationId,
            'user_id' => $userId,
            'action' => $action,
            'result' => json_encode($result, JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
    }
}
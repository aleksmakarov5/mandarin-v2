<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Document;
use App\Models\DocumentType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DocumentController extends Controller
{
    private const CATEGORIES = [
        'incoming',
        'client',
        'vehicle_movement',
        'photo_archive',
        'closing',
        'supporting',
    ];

    public function index(
        Request $request,
        int $applicationId
    ): JsonResponse {
        $application = Application::findOrFail($applicationId);

        Gate::authorize('view', $application);
        Gate::authorize('viewDocuments', $application);

        $filters = $request->validate([
            'category' => [
                'sometimes',
                'string',
                'in:unassigned,' . implode(',', self::CATEGORIES),
            ],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $category = $filters['category'] ?? 'unassigned';

        $base = Document::query()
            ->where('application_id', $applicationId)
            ->where('is_archived', false)
            ->where('lifecycle_status', '!=', 'deleted');

        /*
         * Счётчики документов, а не физических файлов.
         * Пустые типы не создают строки в интерфейсе.
         */
        $counts = array_fill_keys(
            ['unassigned', ...self::CATEGORIES],
            0
        );

        $counts['unassigned'] = (clone $base)
            ->whereNull('document_type_id')
            ->count();

        $categoryCounts = (clone $base)
            ->join(
                'document_types',
                'document_types.id',
                '=',
                'documents.document_type_id'
            )
            ->selectRaw(
                'document_types.category AS category, COUNT(*) AS total'
            )
            ->groupBy('document_types.category')
            ->pluck('total', 'category');

        foreach (self::CATEGORIES as $code) {
            $counts[$code] = (int) ($categoryCounts[$code] ?? 0);
        }

        /*
         * Типы нужны для модалки выбора.
         * Они не содержат документы и пути файлов.
         */
        $types = DocumentType::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get([
                'id',
                'code',
                'name',
                'category',
                'is_photo',
                'is_archive',
                'allows_upload',
                'allows_generation',
            ]);

        $query = clone $base;

        if ($category === 'unassigned') {
            $query->whereNull('document_type_id');
        } else {
            $query->whereHas('type', function ($typeQuery) use ($category) {
                $typeQuery->where('category', $category);
            });
        }

        /*
         * В список не загружаем фотографии.
         * Даже для комплекта на 500 фото передаются только счётчики.
         */
        $paginator = $query
            ->with('type:id,name,code,category,is_photo,is_archive')
            ->withCount([
                'allFiles as source_files_count' => function ($files) {
                    $files
                        ->where('is_current', true)
                        ->whereIn('file_role', [
                            'original',
                            'generated_docx',
                            'generated_pdf',
                        ]);
                },

                'allFiles as photos_count' => function ($files) {
                    $files
                        ->where('is_current', true)
                        ->where('file_role', 'original')
                        ->where('mime_type', 'like', 'image/%');
                },

                'allFiles as processing_count' => function ($files) {
                    $files->whereIn('processing_status', [
                        'pending',
                        'processing',
                    ]);
                },

                'allFiles as failed_count' => function ($files) {
                    $files->where('processing_status', 'failed');
                },
            ])
            ->orderByDesc('document_date')
            ->orderByDesc('id')
            ->paginate(30);

        /*
         * Явный DTO: storage_path, source_message_id
         * и прочие внутренние поля наружу не выдаём.
         */
        $items = $paginator->getCollection()->map(
            function (Document $document): array {
                return [
                    'id' => (int) $document->id,
                    'title' => $document->title
                        ?: $document->type?->name
                        ?: 'Без названия',
                    'document_date' =>
                        $document->document_date?->format('Y-m-d'),
                    'created_at' => $document->created_at?->toIso8601String(),
                    'updated_at' => $document->updated_at?->toIso8601String(),
                    'source_type' => $document->source_type,
                    'lifecycle_status' => $document->lifecycle_status,

                    'type' => $document->type
                        ? [
                            'id' => (int) $document->type->id,
                            'name' => $document->type->name,
                            'code' => $document->type->code,
                            'category' => $document->type->category,
                            'is_photo' => (bool) $document->type->is_photo,
                        ]
                        : null,

                    'files_count' => (int) $document->source_files_count,
                    'photos_count' => (int) $document->photos_count,
                    'processing_count' => (int) $document->processing_count,
                    'failed_count' => (int) $document->failed_count,
                ];
            }
        );

        return response()->json([
            'data' => $items,
            'types' => $types,
            'counts' => $counts,
            'category' => $category,
            'permissions' => [
                'manage' => Gate::allows(
                    'manageDocuments',
                    $application
                ),
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ])->header('Cache-Control', 'private, no-store');
    }

    /**
     * Назначить тип нераспределённому документу.
     * Физические файлы никуда не перемещаем.
     */
    public function assignType(
        Request $request,
        int $applicationId,
        int $documentId
    ): JsonResponse {
        $application = Application::findOrFail($applicationId);

        Gate::authorize('view', $application);
        Gate::authorize('manageDocuments', $application);

        $validated = $request->validate([
            'document_type_id' => ['required', 'integer', 'min:1'],
        ]);

        DB::transaction(function () use ($applicationId, $documentId, $validated): void {
            $application = Application::query()
                ->lockForUpdate()
                ->findOrFail($applicationId);

            Gate::authorize('view', $application);
            Gate::authorize('manageDocuments', $application);

            $document = Document::query()
                ->where('application_id', $applicationId)
                ->where('is_archived', false)
                ->where('lifecycle_status', '!=', 'deleted')
                ->lockForUpdate()
                ->findOrFail($documentId);

            if ($document->document_type_id !== null) {
                abort(
                    409,
                    'Тип уже назначен. Обновите список документов.'
                );
            }

            $type = DocumentType::query()
                ->where('is_active', true)
                ->findOrFail($validated['document_type_id']);

            /*
             * Обычный документ можно классифицировать сразу.
             * Превращение набора файлов в фотоальбом требует
             * обработки и сборки ZIP — подключим через загрузчик.
             */
            if ($type->is_photo || $type->is_archive) {
                throw ValidationException::withMessages([
                    'document_type_id' =>
                        'Фотоальбом создаётся через загрузку фотографий '
                        . 'в разделе «Архивы фото».',
                ]);
            }

            $document->document_type_id = $type->id;

            /*
             * Назначение типа не расширяет видимость файла.
             * Не меняем visibility_scope автоматически.
             */
            $document->updated_by_user_id = auth()->id();
            $document->save();
        });

        return response()->json([
            'message' => 'Тип документа назначен.',
        ]);
    }
}
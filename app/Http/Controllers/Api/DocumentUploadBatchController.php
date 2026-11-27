<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\StoreDocumentUploadBatchRequest;
use App\Models\Application;
use App\Models\DocumentUploadBatch;
use App\Services\Documents\DocumentUploadBatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DocumentUploadBatchController extends Controller
{
    public function store(
        StoreDocumentUploadBatchRequest $request,
        int $applicationId,
        DocumentUploadBatchService $service
    ): JsonResponse {
        $application = Application::findOrFail($applicationId);

        Gate::authorize('view', $application);
        Gate::authorize('manageDocuments', $application);

        $result = $service->create(
            $application,
            $request->user(),
            $request->validated()
        );

        return response()
            ->json(
                $this->payload($result['batch']),
                $result['created'] ? 201 : 200
            )
            ->header('Cache-Control', 'private, no-store');
    }

    public function show(
        Request $request,
        int $applicationId,
        int $batchId
    ): JsonResponse {
        $application = Application::findOrFail($applicationId);

        Gate::authorize('view', $application);
        Gate::authorize('manageDocuments', $application);

        /*
         * Пользователь получает состояние собственных
         * транспортных пакетов, а не чужой очереди загрузки.
         */
        $batch = DocumentUploadBatch::query()
            ->where('application_id', $applicationId)
            ->where('created_by_user_id', $request->user()->id)
            ->with('items')
            ->findOrFail($batchId);

        return response()
            ->json($this->payload($batch))
            ->header('Cache-Control', 'private, no-store');
    }

    private function payload(DocumentUploadBatch $batch): array
    {
        $context = $batch->context ?? [];

        $chunkSize = (int) (
            $context['chunk_size_bytes']
            ?? config('documents.uploads.chunk_size_bytes')
        );

        return [
            'data' => [
                'id' => (int) $batch->id,
                'application_id' => (int) $batch->application_id,

                'document_id' => $batch->document_id !== null
                    ? (int) $batch->document_id
                    : null,

                'document_type_id' =>
                    $batch->document_type_id !== null
                    ? (int) $batch->document_type_id
                    : null,

                'batch_type' => $batch->batch_type,
                'status' => $batch->status,

                'total_count' => (int) $batch->total_count,
                'uploaded_count' => (int) $batch->uploaded_count,
                'processed_count' => (int) $batch->processed_count,
                'failed_count' => (int) $batch->failed_count,

                'total_bytes' =>
                    (int) ($context['total_bytes'] ?? 0),

                'chunk_size_bytes' => $chunkSize,

                'parallel_files' => (int) config(
                    'documents.uploads.parallel_files',
                    2
                ),

                'items' => $batch->items->map(
                    fn($item): array => [
                        'id' => (int) $item->id,
                        'client_file_key' => $item->client_file_key,
                        'original_name' => $item->original_name,
                        'file_size' => (int) $item->file_size,
                        'status' => $item->status,

                        'chunks_count' => intdiv(
                            (int) $item->file_size
                            + $chunkSize - 1,
                            $chunkSize
                        ),
                    ]
                )->values(),
            ],
        ];
    }
}
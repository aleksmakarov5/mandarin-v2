<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\DocumentUploadBatch;
use App\Models\DocumentUploadItem;
use App\Services\Documents\DocumentUploadChunkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

class DocumentUploadChunkController extends Controller
{
    public function store(
        Request $request,
        int $applicationId,
        int $batchId,
        int $itemId,
        int $chunkIndex,
        DocumentUploadChunkService $service
    ): JsonResponse {
        [$application, $batch, $item] = $this->resolveContext(
            $request,
            $applicationId,
            $batchId,
            $itemId
        );

        $chunkSize = (int) (
            $batch->context['chunk_size_bytes'] ?? 0
        );

        if ($chunkSize <= 0) {
            throw new RuntimeException(
                'У пакета не настроен размер части.'
            );
        }

        /*
         * Правило max для файла задаётся в КиБ.
         * Точный размер в байтах дополнительно проверяет сервис.
         */
        $maxKilobytes = intdiv(
            $chunkSize + 1023,
            1024
        );

        $validated = $request->validate([
            'chunk' => [
                'required',
                'file',
                'max:' . $maxKilobytes,
            ],

            /*
             * Необязательный SHA-256 от браузера.
             * Сервер в любом случае считает собственный SHA-256.
             */
            'sha256' => [
                'nullable',
                'string',
                'regex:/^[a-fA-F0-9]{64}$/',
            ],
        ]);

        $result = $service->store(
            $application,
            $batch,
            $item,
            $request->user(),
            $chunkIndex,
            $request->file('chunk'),
            $validated['sha256'] ?? null
        );

        return response()->json([
            'data' => $result,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function show(
        Request $request,
        int $applicationId,
        int $batchId,
        int $itemId,
        DocumentUploadChunkService $service
    ): JsonResponse {
        [, $batch, $item] = $this->resolveContext(
            $request,
            $applicationId,
            $batchId,
            $itemId
        );

        return response()->json([
            'data' => $service->state($batch, $item),
        ])->header('Cache-Control', 'private, no-store');
    }

    private function resolveContext(
        Request $request,
        int $applicationId,
        int $batchId,
        int $itemId
    ): array {
        $application = Application::findOrFail($applicationId);

        Gate::authorize('view', $application);
        Gate::authorize('manageDocuments', $application);

        /*
         * Проверяем всю цепочку:
         * дело → пакет текущего пользователя → элемент пакета.
         */
        $batch = DocumentUploadBatch::query()
            ->where('application_id', $applicationId)
            ->where('created_by_user_id', $request->user()->id)
            ->findOrFail($batchId);

        $item = DocumentUploadItem::query()
            ->where('batch_id', $batch->id)
            ->findOrFail($itemId);

        return [$application, $batch, $item];
    }
}
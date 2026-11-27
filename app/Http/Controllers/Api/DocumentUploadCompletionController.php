<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\Documents\AssembleDocumentUpload;
use App\Models\Application;
use App\Models\DocumentUploadBatch;
use App\Models\DocumentUploadItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use App\Jobs\Documents\PublishDocumentUpload;

class DocumentUploadCompletionController extends Controller
{
    public function store(
        Request $request,
        int $applicationId,
        int $batchId,
        int $itemId
    ): JsonResponse {
        $result = DB::transaction(function () use ($request, $applicationId, $batchId, $itemId): array {
            $application = Application::query()
                ->lockForUpdate()
                ->findOrFail($applicationId);

            Gate::authorize('view', $application);
            Gate::authorize('manageDocuments', $application);

            $batch = DocumentUploadBatch::query()
                ->where('application_id', $application->id)
                ->where(
                    'created_by_user_id',
                    $request->user()->id
                )
                ->lockForUpdate()
                ->findOrFail($batchId);

            $item = DocumentUploadItem::query()
                ->where('batch_id', $batch->id)
                ->lockForUpdate()
                ->findOrFail($itemId);

            /*
             * Файл уже собран или передан
             * на дальнейшую обработку.
             */
            if (
                in_array(
                    $item->status,
                    ['uploaded', 'processing', 'completed'],
                    true
                )
            ) {
                return [
                    'id' => (int) $item->id,
                    'status' => $item->status,
                    'dispatch' => false,

                    'publish' => $item->status === 'uploaded'
                        && $item->document_file_id === null,
                ];
            }

            abort_unless(
                in_array(
                    $batch->status,
                    ['pending', 'uploading'],
                    true
                ),
                409,
                'Этот пакет больше не принимает файлы.'
            );

            /*
             * Повтор запроса при assembling разрешён.
             * Если первая постановка в очередь не состоялась,
             * пользователь сможет повторить завершение.
             */
            abort_unless(
                in_array(
                    $item->status,
                    ['pending', 'uploading', 'assembling', 'failed'],
                    true
                ),
                409,
                'Невозможно завершить загрузку в текущем состоянии.'
            );

            $chunkSize = (int) (
                $batch->context['chunk_size_bytes'] ?? 0
            );

            abort_if(
                $chunkSize <= 0,
                409,
                'У пакета не настроен размер части.'
            );

            $fileSize = (int) $item->file_size;

            $expectedCount = intdiv(
                $fileSize + $chunkSize - 1,
                $chunkSize
            );

            $chunks = DB::table('document_upload_chunks')
                ->where('upload_item_id', $item->id)
                ->orderBy('chunk_index')
                ->get([
                    'chunk_index',
                    'size_bytes',
                ]);

            abort_unless(
                $chunks->count() === $expectedCount,
                409,
                'Не все части файла получены.'
            );

            foreach ($chunks as $index => $chunk) {
                $expectedSize = min(
                    $chunkSize,
                    $fileSize - $index * $chunkSize
                );

                abort_unless(
                    (int) $chunk->chunk_index === $index
                    && (int) $chunk->size_bytes === $expectedSize,
                    409,
                    'Нарушена последовательность или размер частей.'
                );
            }

            $item->status = 'assembling';
            $item->error = null;
            $item->save();

            if ($batch->status === 'pending') {
                $batch->status = 'uploading';
                $batch->save();
            }

            return [
                'id' => (int) $item->id,
                'status' => 'assembling',
                'dispatch' => true,
            ];
        });

        /*
         * Отправляем задачу после фиксации транзакции.
         *
         * Если очередь недоступна, запрос завершится ошибкой,
         * но повторный вызов снова поставит задачу.
         */
        if ($result['dispatch']) {
            AssembleDocumentUpload::dispatch($result['id'])
                ->onQueue('documents');
        }
        if ($result['publish'] ?? false) {
            PublishDocumentUpload::dispatch($result['id'])
                ->onQueue('documents');
        }

        return response()->json([
            'data' => [
                'id' => $result['id'],
                'status' => $result['status'],
            ],
        ], $result['dispatch'] ? 202 : 200)
            ->header('Cache-Control', 'private, no-store');
    }
}
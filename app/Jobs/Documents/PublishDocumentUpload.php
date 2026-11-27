<?php

namespace App\Jobs\Documents;

use App\Services\Documents\DocumentPublicationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PublishDocumentUpload implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 1800;
    public int $tries = 5;

    public function __construct(
        public int $itemId
    ) {
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping(
                'document-upload-publication:' . $this->itemId
            ))
                ->releaseAfter(60)
                ->expireAfter(2100),
        ];
    }

    public function backoff(): array
    {
        return [10, 30, 60, 120];
    }

    public function handle(
        DocumentPublicationService $service
    ): void {
        $service->publish($this->itemId);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Ошибка публикации документа.', [
            'item_id' => $this->itemId,
            'error' => $exception?->getMessage(),
        ]);

        /*
         * Исходник остаётся uploaded: он не потерян
         * и не требует повторной передачи частей.
         *
         * Ошибка публикации хранится отдельно от
         * статуса транспортной сборки.
         */
        DB::table('document_upload_items')
            ->where('id', $this->itemId)
            ->where('status', 'uploaded')
            ->whereNull('document_file_id')
            ->update([
                'error' =>
                    'Исходник получен, но документ не опубликован. '
                    . 'Обратитесь к администратору.',
                'updated_at' => now(),
            ]);
    }
}
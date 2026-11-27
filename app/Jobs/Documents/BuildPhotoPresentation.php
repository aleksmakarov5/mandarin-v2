<?php

namespace App\Jobs\Documents;

use App\Models\DocumentFile;
use App\Services\Documents\PhotoPresentationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class BuildPhotoPresentation implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $timeout = 180;

    public function __construct(
        public int $fileId,
        public string $cacheKey
    ) {
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->cacheKey))
                ->releaseAfter(30)
                ->expireAfter(240),
        ];
    }

    public function backoff(): array
    {
        return [15, 60];
    }

    public function handle(
        PhotoPresentationService $service
    ): void {
        $file = DocumentFile::find($this->fileId);

        if (
            !$file
            || !$file->is_current
            || $file->file_role !== 'original'
            || $file->processing_status !== 'ready'
        ) {
            Cache::forget($this->cacheKey . ':queued');
            return;
        }

        $service->build($file);

        Cache::forget($this->cacheKey . ':error');
        Cache::forget($this->cacheKey . ':queued');
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Ошибка подготовки фотографии.', [
            'file_id' => $this->fileId,
            'error' => $exception?->getMessage(),
        ]);

        /*
         * Техническую ошибку не отдаём клиенту:
         * в ней могут быть внутренние пути.
         */
        Cache::put(
            $this->cacheKey . ':error',
            'Не удалось подготовить фотографию. '
            . 'Возможно, сервер не поддерживает её формат.',
            now()->addMinutes(10)
        );

        Cache::forget($this->cacheKey . ':queued');
    }
}
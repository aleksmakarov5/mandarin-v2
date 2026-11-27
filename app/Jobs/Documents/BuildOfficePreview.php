<?php

namespace App\Jobs\Documents;

use App\Services\Documents\OfficePreviewService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Throwable;

class BuildOfficePreview implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 240;
    public int $tries = 1;
    public int $uniqueFor = 600;

    public function __construct(
        public int $fileId
    ) {
    }

    public function uniqueId(): string
    {
        return 'office-preview:' . $this->fileId;
    }

    public function handle(OfficePreviewService $service): void
    {
        try {
            $service->build($this->fileId);

            Cache::forget($service->errorKey($this->fileId));
        } catch (Throwable $error) {
            Cache::put(
                $service->errorKey($this->fileId),
                'Не удалось подготовить представление документа. '
                . 'Возможно, файл повреждён или защищён паролем.',
                now()->addMinutes(10)
            );

            throw $error;
        }
    }
}
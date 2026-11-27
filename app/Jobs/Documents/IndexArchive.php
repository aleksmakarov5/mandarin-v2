<?php

namespace App\Jobs\Documents;

use App\Models\DocumentFile;
use App\Services\Documents\ArchiveIndexService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class IndexArchive implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;
    public int $timeout = 1200;

    public function __construct(
        public int $archiveFileId
    ) {
    }

    public function handle(
        ArchiveIndexService $service
    ): void {
        $archive = DocumentFile::query()
            ->where('is_current', true)
            ->whereIn('file_role', [
                'original',
                'archive',
            ])
            ->findOrFail($this->archiveFileId);

        $service->index($archive);
    }

    public function failed(?Throwable $error): void
    {
        DocumentFile::query()
            ->whereKey($this->archiveFileId)
            ->update([
                'archive_index_status' => 'failed',
                'archive_index_error' => mb_substr(
                    $error?->getMessage()
                    ?: 'Ошибка индексации архива.',
                    0,
                    1000
                ),
            ]);
    }
}
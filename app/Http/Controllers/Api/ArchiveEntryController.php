<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ArchiveEntry;
use App\Models\Document;
use App\Models\DocumentFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ArchiveEntryController extends Controller
{
    public function index(
        Request $request,
        int $applicationId,
        int $documentId,
        int $archiveFileId
    ): JsonResponse {
        $application = Application::findOrFail($applicationId);

        Gate::authorize('view', $application);
        Gate::authorize('viewDocuments', $application);

        $document = Document::query()
            ->where('application_id', $applicationId)
            ->where('is_archived', false)
            ->where('lifecycle_status', '!=', 'deleted')
            ->findOrFail($documentId);

        $archive = DocumentFile::query()
            ->where('document_id', $document->id)
            ->where('is_current', true)
            ->whereIn('file_role', ['original', 'archive'])
            ->findOrFail($archiveFileId);

        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:500'],
        ]);

        $entries = ArchiveEntry::query()
            ->where('archive_file_id', $archive->id)
            ->orderBy('entry_path')
            ->orderBy('id')
            ->paginate((int) ($validated['per_page'] ?? 100));

        return response()->json([
            'data' => $entries->getCollection()
                ->map(fn(ArchiveEntry $entry) => [
                    'id' => (int) $entry->id,
                    'name' => $entry->entry_name,
                    'path' => $entry->entry_path,
                    'type' => $entry->entry_type,
                    'extension' => $entry->extension,
                    'mime_type' => $entry->mime_type,
                    'size' => (int) (
                        $entry->uncompressed_size ?? 0
                    ),
                    'is_safe' => (bool) $entry->is_safe,
                    'is_previewable' => (bool) (
                        $entry->is_previewable
                    ),
                    'status' => $entry->status,
                    'error' => $entry->error,
                ])
                ->values(),
            'archive' => [
                'id' => (int) $archive->id,
                'name' => $archive->original_name,
                'index_status' =>
                    $archive->archive_index_status,
                'index_error' =>
                    $archive->archive_index_error,
                'indexed_at' =>
                    $archive->archive_indexed_at?->toIso8601String(),
            ],
            'meta' => [
                'current_page' => $entries->currentPage(),
                'last_page' => $entries->lastPage(),
                'total' => $entries->total(),
            ],
        ])->header('Cache-Control', 'private, no-store');
    }
}
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentPreviewController extends Controller
{
    /*
     * Только форматы, которые разрешаем отображать напрямую.
     * HTML и SVG здесь намеренно отсутствуют.
     */
    private const PREVIEW_MIMES = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'image',
        'image/png' => 'image',
        'image/webp' => 'image',
        'image/gif' => 'image',
        'image/avif' => 'image',
    ];

    /**
     * Axios передаёт Bearer-токен и получает
     * краткоживущий адрес просмотра.
     */
    public function link(
        Request $request,
        int $applicationId,
        int $documentId,
        int $fileId
    ): JsonResponse {
        $file = $this->resolveFile(
            $applicationId,
            $documentId,
            $fileId,
            $request->user()
        );

        $kind = self::PREVIEW_MIMES[$file->mime_type] ?? null;

        if ($kind === null) {
            return response()->json([
                'message' =>
                    'Для этого формата ещё не подготовлено представление. '
                    . 'Для DOCX/XLSX нужна серверная конвертация, '
                    . 'для архивов — каталог содержимого.',
            ], 422);
        }

        return response()->json([
            'data' => [
                'kind' => $kind,
                'name' => $file->original_name,

                /*
                 * Отдельная подпись именно для просмотра.
                 * Ссылку скачивания не переиспользуем.
                 */
                'url' => URL::temporarySignedRoute(
                    'api.documents.files.preview',
                    now()->addMinutes(5),
                    [
                        'applicationId' => $applicationId,
                        'documentId' => $documentId,
                        'fileId' => $fileId,
                        'preview_user' => $request->user()->id,
                    ],
                    absolute: false
                ),
            ],
        ])->header('Cache-Control', 'private, no-store');
    }

    public function show(
        Request $request,
        int $applicationId,
        int $documentId,
        int $fileId
    ): StreamedResponse {
        abort_unless(
            $request->hasValidRelativeSignature(),
            403,
            'Ссылка просмотра недействительна или истекла.'
        );

        $userId = filter_var(
            $request->query('preview_user'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        abort_unless($userId !== false, 403);

        $user = User::findOrFail($userId);

        $file = $this->resolveFile(
            $applicationId,
            $documentId,
            $fileId,
            $user
        );

        abort_unless(
            isset(self::PREVIEW_MIMES[$file->mime_type]),
            415,
            'Этот формат нельзя показать напрямую.'
        );

        abort_unless(
            filled($file->storage_disk)
            && filled($file->storage_path),
            404
        );

        $disk = Storage::disk($file->storage_disk);

        abort_unless(
            $disk->exists($file->storage_path),
            404,
            'Файл отсутствует в хранилище.'
        );

        $name = preg_replace(
            '/[\x00-\x1F\x7F\/\\\\]/u',
            '_',
            (string) $file->original_name
        );

        $name = trim($name ?? '');

        if ($name === '' || in_array($name, ['.', '..'], true)) {
            $name = 'document-' . $file->id;
        }

        $disposition = HeaderUtils::makeDisposition(
            'inline',
            $name,
            'document-' . $file->id
        );

        $stream = $disk->readStream($file->storage_path);

        abort_unless(
            is_resource($stream),
            404,
            'Не удалось открыть файл.'
        );

        return response()->stream(
            function () use ($stream): void {
                try {
                    fpassthru($stream);
                } finally {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }
            },
            200,
            [
                // inline, а не attachment: показываем содержимое.
                'Content-Type' => $file->mime_type,
                'Content-Disposition' => $disposition,
                'X-Content-Type-Options' => 'nosniff',
                'X-Frame-Options' => 'SAMEORIGIN',
                'Referrer-Policy' => 'no-referrer',
                'Cache-Control' => 'private, no-store',
            ]
        );
    }

    private function resolveFile(
        int $applicationId,
        int $documentId,
        int $fileId,
        User $user
    ): DocumentFile {
        $application = Application::findOrFail($applicationId);

        Gate::forUser($user)->authorize('view', $application);
        Gate::forUser($user)->authorize(
            'viewDocuments',
            $application
        );

        /*
         * Те же ограничения, что у действующего списка.
         * Подмена ID не позволяет выйти за пределы дела.
         */
        $document = Document::query()
            ->where('application_id', $applicationId)
            ->where('is_archived', false)
            ->where('lifecycle_status', '!=', 'deleted')
            ->findOrFail($documentId);

        return $document->allFiles()
            ->where('is_current', true)
            ->where('processing_status', 'ready')
            ->whereIn('file_role', [
                'original',
                'generated_docx',
                'generated_pdf',
            ])
            ->findOrFail($fileId);
    }
}
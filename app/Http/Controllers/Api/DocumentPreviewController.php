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
use App\Jobs\Documents\BuildOfficePreview;
use App\Services\Documents\OfficePreviewService;
use Illuminate\Support\Facades\Cache;

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
        $request->validate([
            'retry' => ['sometimes', 'boolean'],
        ]);

        $file = $this->resolveFile(
            $applicationId,
            $documentId,
            $fileId,
            $request->user()
        );

        $extension = strtolower((string) $file->extension);

        if ($extension === 'txt') {
            return $this->textPreview($file);
        }

        $kind = self::PREVIEW_MIMES[$file->mime_type] ?? null;

        if ($kind === null) {
            $office = app(OfficePreviewService::class);

            if (!$office->supports($file)) {
                return response()->json([
                    'message' =>
                        'Для этого формата просмотр пока не подключён. '
                        . 'Исходный файл можно скачать.',
                ], 422);
            }

            $prepared = $office->ready($file);

            if (!$prepared) {
                $errorKey = $office->errorKey($file->id);

                if ($request->boolean('retry')) {
                    Cache::forget($errorKey);
                } elseif ($error = Cache::get($errorKey)) {
                    return response()->json([
                        'message' => $error,
                    ], 422);
                }

                BuildOfficePreview::dispatch($file->id)
                    ->onQueue('document-previews');

                return response()->json([
                    'data' => [
                        'status' => 'processing',
                    ],
                ], 202)->header('Cache-Control', 'private, no-store');
            }

            $kind = 'pdf';
        }

        /*
         * В ссылке остаётся ID исходника.
         * show() самостоятельно выбирает разрешённое представление.
         */
        return response()->json([
            'data' => [
                'status' => 'ready',
                'kind' => $kind,
                'name' => $file->original_name,

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

        if (!isset(self::PREVIEW_MIMES[$file->mime_type])) {
            $office = app(OfficePreviewService::class);

            abort_unless(
                $office->supports($file),
                415,
                'Этот формат нельзя показать напрямую.'
            );

            $prepared = $office->ready($file);

            abort_unless(
                $prepared !== null,
                409,
                'Представление документа ещё не готово.'
            );

            $file = $prepared;
        }

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
    private function textPreview(DocumentFile $file): JsonResponse
    {
        $limit = (int) config(
            'documents.text_preview.max_bytes',
            1024 * 1024
        );

        abort_if(
            $file->file_size > $limit,
            422,
            'Текст слишком большой для встроенного просмотра. '
            . 'Скачайте исходный файл.'
        );

        $stream = Storage::disk($file->storage_disk)
            ->readStream($file->storage_path);

        abort_unless(is_resource($stream), 404);

        try {
            $text = stream_get_contents($stream, $limit + 1);
        } finally {
            fclose($stream);
        }

        abort_if(
            $text === false || strlen($text) > $limit,
            422,
            'Не удалось прочитать текстовый файл.'
        );

        // UTF-8 BOM.
        if (str_starts_with($text, "\xEF\xBB\xBF")) {
            $text = substr($text, 3);
        }

        abort_if(
            str_contains($text, "\0"),
            422,
            'Эта кодировка текста пока не поддерживается.'
        );

        if (!preg_match('//u', $text)) {
            abort_unless(
                function_exists('iconv'),
                422,
                'Для просмотра текста требуется преобразование кодировки.'
            );

            /*
             * Для старых русскоязычных TXT пробуем Windows-1251.
             * Другие кодировки позже добавим явным переключателем.
             */
            $converted = @iconv(
                'Windows-1251',
                'UTF-8',
                $text
            );

            abort_if(
                $converted === false,
                422,
                'Не удалось определить кодировку текста.'
            );

            $text = $converted;
        }

        return response()->json([
            'data' => [
                'status' => 'ready',
                'kind' => 'text',
                'name' => $file->original_name,
                'text' => $text,
            ],
        ])->header('Cache-Control', 'private, no-store');
    }
}
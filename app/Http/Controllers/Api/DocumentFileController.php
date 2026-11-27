<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Document;
use App\Models\DocumentFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Models\User;
use Illuminate\Support\Facades\URL;

class DocumentFileController extends Controller
{
    /**
     * Только текущие исходники и сгенерированные документы.
     * Превью и другие производные файлы в список не включаем.
     */
    private const FILE_ROLES = [
        'original',
        'generated_docx',
        'generated_pdf',
    ];

    public function index(
        Request $request,
        int $applicationId,
        int $documentId
    ): JsonResponse {
        $document = $this->resolveDocument(
            $applicationId,
            $documentId
        );

        $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $paginator = $document->allFiles()
            ->where('is_current', true)
            ->where('processing_status', 'ready')
            ->whereIn('file_role', self::FILE_ROLES)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(50);

        /*
         * Не сериализуем модель целиком:
         * диски и физические пути остаются на сервере.
         */
        $items = $paginator->getCollection()->map(
            function (DocumentFile $file) use ($applicationId, $documentId): array {
                return [
                    'id' => (int) $file->id,
                    'name' => $file->original_name,
                    'extension' => $file->extension,
                    'mime_type' => $file->mime_type,
                    'file_size' => (int) $file->file_size,
                    'created_at' =>
                        $file->created_at?->toIso8601String(),

                    /*
                     * Относительный URL того же приложения.
                     * Это не публичный адрес файла в хранилище.
                     */
                    'download_link_url' => route(
                        'api.documents.files.download-link',
                        [
                            'applicationId' => $applicationId,
                            'documentId' => $documentId,
                            'fileId' => $file->id,
                        ],
                        false
                    ),
                ];
            }
        )->values();

        return response()->json([
            'data' => $items,
            'document' => [
                'id' => (int) $document->id,
                'title' => $document->title ?: 'Без названия',
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ])->header('Cache-Control', 'private, no-store');
    }

    public function download(
        Request $request,
        int $applicationId,
        int $documentId,
        int $fileId
    ): StreamedResponse {
        /*
         * Подпись проверяет middleware signed:relative.
         * Дополнительная проверка защищает метод от случайного
         * подключения к маршруту без этого middleware.
         */
        abort_unless(
            $request->hasValidRelativeSignature(),
            403,
            'Ссылка недействительна или срок её действия истёк.'
        );

        $userId = filter_var(
            $request->query('download_user'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        abort_unless(
            $userId !== false,
            403,
            'Недействительная ссылка.'
        );

        $user = User::findOrFail($userId);

        /*
         * Заново проверяем права: если доступ отозван после
         * получения ссылки, скачивание не разрешается.
         */
        $document = $this->resolveDocument(
            $applicationId,
            $documentId,
            $user
        );

        /*
         * Файл обязательно ищется через документ.
         * Одного совпадения fileId недостаточно.
         */
        $file = $document->allFiles()
            ->where('is_current', true)
            ->where('processing_status', 'ready')
            ->whereIn('file_role', self::FILE_ROLES)
            ->findOrFail($fileId);

        abort_unless(
            is_string($file->storage_disk)
            && $file->storage_disk !== ''
            && is_string($file->storage_path)
            && $file->storage_path !== '',
            404,
            'Файл недоступен.'
        );

        $disk = Storage::disk($file->storage_disk);

        abort_unless(
            $disk->exists($file->storage_path),
            404,
            'Файл отсутствует в хранилище.'
        );

        /*
         * Открываем поток до формирования успешного ответа.
         * Подходит для закрытого локального диска и S3.
         */
        $stream = $disk->readStream($file->storage_path);

        abort_unless(
            is_resource($stream),
            404,
            'Не удалось открыть файл.'
        );

        $filename = $this->downloadName(
            $file->original_name,
            $file->id
        );

        $disposition = HeaderUtils::makeDisposition(
            'attachment',
            $filename,
            'document-' . $file->id
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
                /*
                 * На этом этапе только скачивание.
                 * Не открываем активное содержимое внутри CRM.
                 */
                'Referrer-Policy' => 'no-referrer',
                'Content-Type' => 'application/octet-stream',
                'Content-Disposition' => $disposition,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ]
        );
    }

    private function resolveDocument(
        int $applicationId,
        int $documentId,
        ?User $user = null
    ): Document {
        $user ??= auth()->user();

        abort_unless(
            $user !== null,
            401,
            'Необходима авторизация.'
        );

        $application = Application::findOrFail($applicationId);

        Gate::forUser($user)->authorize('view', $application);
        Gate::forUser($user)->authorize('viewDocuments', $application);

        return Document::query()
            ->where('application_id', $applicationId)
            ->where('is_archived', false)
            ->where('lifecycle_status', '!=', 'deleted')
            ->findOrFail($documentId);
    }

    private function downloadName(
        ?string $name,
        int $fileId
    ): string {
        /*
         * Имя используется только в заголовке скачивания.
         * Не разрешаем разделители пути и управляющие символы.
         */
        $name = preg_replace(
            '/[\x00-\x1F\x7F\/\\\\]/u',
            '_',
            (string) $name
        );

        $name = trim($name ?? '');

        return $name !== '' && !in_array($name, ['.', '..'], true)
            ? $name
            : 'document-' . $fileId;
    }

    public function downloadLink(
        Request $request,
        int $applicationId,
        int $documentId,
        int $fileId
    ): JsonResponse {
        // Здесь действует auth:sanctum — пользователь получен по токену.
        $document = $this->resolveDocument(
            $applicationId,
            $documentId
        );

        $document->allFiles()
            ->where('is_current', true)
            ->where('processing_status', 'ready')
            ->whereIn('file_role', self::FILE_ROLES)
            ->findOrFail($fileId);

        $url = URL::temporarySignedRoute(
            'api.documents.files.download',
            now()->addSeconds(60),
            [
                'applicationId' => $applicationId,
                'documentId' => $documentId,
                'fileId' => $fileId,

                // Этот параметр тоже защищён подписью.
                'download_user' => $request->user()->id,
            ],
            absolute: false
        );

        return response()->json([
            'data' => [
                'url' => $url,
                'expires_in' => 60,
            ],
        ])->header('Cache-Control', 'private, no-store');
    }
}
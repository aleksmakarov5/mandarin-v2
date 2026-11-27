<?php

use Illuminate\Support\Facades\Route;

// Контроллеры авторизации и основных модулей
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ApplicationController;
use App\Http\Controllers\Api\ApplicationGeneralController;
use App\Http\Controllers\Api\ApplicationDocumentController;
use App\Http\Controllers\Api\EstimateController;
use App\Http\Controllers\Api\SparePartController;
use App\Http\Controllers\Api\WorkController;
use App\Http\Controllers\Api\TroubleshootingController;
use App\Http\Controllers\Api\AttachmentController;
use App\Http\Controllers\Api\LawyerController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\DocumentFileController;

Route::get(
    'applications/{id}/documents',
    [DocumentController::class, 'index']
)->whereNumber('id');

// Контроллеры модуля администрирования
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\Admin\UserGroupController;
use App\Http\Controllers\Api\Admin\InsuranceCompanyController;
use App\Http\Controllers\Api\Admin\ApplicationStatusController;
use App\Http\Controllers\Api\Admin\CityController;
use App\Http\Controllers\Api\Admin\LegalEntityController;
use App\Http\Controllers\Api\Admin\OurCompanyController;
use App\Http\Controllers\Api\Admin\FinanceStatusController;

/*|--------------------------------------------------------------------------
| Публичные маршруты
|--------------------------------------------------------------------------
*/
Route::post('/auth/login', [AuthController::class, 'login']);
/*
 * Доступ только по краткоживущей подписанной ссылке.
 * Права пользователя дополнительно проверяет контроллер.
 */
Route::get(
    'applications/{applicationId}/documents/{documentId}'
    . '/files/{fileId}/download',
    [
        \App\Http\Controllers\Api\DocumentFileController::class,
        'download',
    ]
)
    ->middleware(['signed:relative', 'throttle:30,1'])
    ->whereNumber('applicationId')
    ->whereNumber('documentId')
    ->whereNumber('fileId')
    ->name('api.documents.files.download');
Route::get(
    'applications/{applicationId}/documents/{documentId}'
    . '/files/{fileId}/preview',
    [
        \App\Http\Controllers\Api\DocumentPreviewController::class,
        'show',
    ]
)
    ->middleware(['signed:relative', 'throttle:120,1'])
    ->whereNumber('applicationId')
    ->whereNumber('documentId')
    ->whereNumber('fileId')
    ->name('api.documents.files.preview');
Route::get(
    'applications/{applicationId}/documents/{documentId}'
    . '/gallery/photos/{fileId}/presentation/{kind}',
    [
        \App\Http\Controllers\Api\PhotoPresentationController::class,
        'show',
    ]
)
    ->middleware('signed:relative')
    ->whereNumber('applicationId')
    ->whereNumber('documentId')
    ->whereNumber('fileId')
    ->where('kind', 'thumbnail|screen')
    ->name('api.documents.photos.presentation');

/*
 * Готовые изображения галереи.
 * Доступ по подписанной ссылке с повторной проверкой прав.
 */
Route::get(
    'applications/{applicationId}/documents/{documentId}'
    . '/gallery/photos/{fileId}/media/{kind}',
    [
        \App\Http\Controllers\Api\PhotoGalleryMediaController::class,
        'show',
    ]
)
    ->middleware('signed:relative')
    ->whereNumber('applicationId')
    ->whereNumber('documentId')
    ->whereNumber('fileId')
    ->where('kind', 'thumbnail|main')
    ->name('api.documents.gallery.media');
Route::get(
    'applications/{applicationId}/documents/{documentId}'
    . '/download-content/{fileId}',
    [
        \App\Http\Controllers\Api\DocumentDownloadController::class,
        'content',
    ]
)
    ->middleware('signed:relative')
    ->whereNumber('applicationId')
    ->whereNumber('documentId')
    ->whereNumber('fileId')
    ->name('api.documents.download.content');
/*
|--------------------------------------------------------------------------
| Защищенные маршруты (Sanctum Bearer Token)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {


    // 1. Фильтры ДО маршрута с {id}
    Route::get('/applications/filters', [ApplicationController::class, 'filterOptions']);
    Route::get('/applications/branch-employees', [ApplicationController::class, 'getBranchEmployees']);
    // 2. Список и создание
    Route::get('/applications', [ApplicationController::class, 'index']);
    Route::post('/applications', [ApplicationController::class, 'store']);
    Route::get(
        '/applications/general-options',
        [ApplicationGeneralController::class, 'options']
    );

    Route::get(
        '/applications/general-branch-employees',
        [ApplicationGeneralController::class, 'branchEmployees']
    );

    Route::get(
        '/applications/{id}/general',
        [ApplicationGeneralController::class, 'show']
    )->whereNumber('id');

    Route::put(
        '/applications/{id}/general',
        [ApplicationGeneralController::class, 'update']
    )->whereNumber('id');

    Route::get(
        '/general-lawyers',
        [ApplicationGeneralController::class, 'lawyers']
    );

    Route::post(
        '/general-lawyers',
        [ApplicationGeneralController::class, 'storeLawyer']
    );
    // 3. Конкретное дело
    Route::get('/applications/{id}', [ApplicationController::class, 'show']);
    Route::put('/applications/{id}', [ApplicationController::class, 'update']);
    Route::delete('/applications/{id}', [ApplicationController::class, 'destroy']);

    // 4. Документы
    Route::post('/applications/{id}/documents', [ApplicationDocumentController::class, 'store']);
    Route::get('/applications/{id}/documents/generate', [ApplicationDocumentController::class, 'generatePdf']);
    // Текущий пользователь и выход
    Route::get('/auth/me', [AuthController::class, 'me']);
    // Маршрут обновления профиля (PUT)
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    // 1. ЗАЯВКИ (APPLICATIONS)
    // ==========================================
    Route::apiResource('applications', ApplicationController::class);
    // Смета, Запчасти и Работы
    // Route::get('applications/{id}/estimate', [\App\Http\Controllers\Api\EstimateController::class, 'show']);
    // Route::post('applications/{id}/estimate/parts', [\App\Http\Controllers\Api\EstimateController::class, 'savePart']);
    // Route::delete('estimate/parts/{id}', [\App\Http\Controllers\Api\EstimateController::class, 'deletePart']);
    // Route::post('applications/{id}/estimate/works', [\App\Http\Controllers\Api\EstimateController::class, 'saveWork']);
    // Route::delete('estimate/works/{id}', [\App\Http\Controllers\Api\EstimateController::class, 'deleteWork']);

    // ==========================================
    // 2. ЗАПЧАСТИ (SPARE PARTS)
    // ==========================================
    // Route::get('applications/{id}/spare-parts', [SparePartController::class, 'index']);
    // Route::post('applications/{id}/spare-parts', [SparePartController::class, 'store']);
    // Route::post('applications/{id}/spare-parts/batch-save', [SparePartController::class, 'batchSave']);
    // Route::put('spare-parts/{id}', [SparePartController::class, 'update']);
    // Route::delete('spare-parts/{id}', [SparePartController::class, 'destroy']);

    // // ==========================================
    // // 3. РАБОТЫ И НОРМО-ЧАСЫ (WORKS)
    // // ==========================================
    // Route::get('applications/{id}/works', [WorkController::class, 'index']);
    // Route::post('applications/{id}/works', [WorkController::class, 'store']);
    // Route::put('works/{id}', [WorkController::class, 'update']);
    // Route::delete('works/{id}', [WorkController::class, 'destroy']);

    // ==========================================
    // 4. ДЕФЕКТОВКА И АКТЫ WORD (TROUBLESHOOTING)
    // ==========================================
    // Route::get('applications/{id}/troubleshootings', [TroubleshootingController::class, 'index']);
    // Route::post('applications/{id}/troubleshootings', [TroubleshootingController::class, 'store']);
    // Route::put('troubleshootings/{id}', [TroubleshootingController::class, 'update']);
    // Route::delete('troubleshootings/{id}', [TroubleshootingController::class, 'destroy']);
    // Route::get('applications/{id}/export-act', [TroubleshootingController::class, 'exportDocx']);

    // // ==========================================
    // // 5. ДОКУМЕНТЫ И ФАЙЛЫ S3 (ATTACHMENTS)
    // // ==========================================
    // Route::get('applications/{id}/attachments', [AttachmentController::class, 'index']);
    // Route::post('applications/{id}/attachments', [AttachmentController::class, 'upload']);
    // Route::delete('attachments/{id}', [AttachmentController::class, 'destroy']);

    Route::prefix('applications/{id}/estimate')->group(function () {
        Route::get('/', [EstimateController::class, 'show']);

        // Запчасти
        Route::post('/spare-parts', [EstimateController::class, 'storeSparePart']);
        Route::put('/spare-parts/{partId}', [EstimateController::class, 'updateSparePart']);
        Route::delete('/spare-parts/{partId}', [EstimateController::class, 'destroySparePart']);

        // Работы
        Route::post('/works', [EstimateController::class, 'storeWork']);
        Route::put('/works/{workId}', [EstimateController::class, 'updateWork']);
        Route::delete('/works/{workId}', [EstimateController::class, 'destroyWork']);
    });
    Route::prefix('applications/{id}/spare-parts')->group(function () {
        Route::get('/', [SparePartController::class, 'index']);
        Route::post('/', [SparePartController::class, 'store']);
        Route::post('/small-parts', [SparePartController::class, 'handleSmallParts']);
        Route::post('/casco-discount', [SparePartController::class, 'updateCascoDiscount']); // <-- ДОЛЖЕН БЫТЬ Route::post
        Route::get('/history', [SparePartController::class, 'history']);
        Route::post('/bulk-pzn-percent', [SparePartController::class, 'bulkIncreasePzn']);
        Route::post('/{partId}/comments', [SparePartController::class, 'storeComment']);
        Route::put('/{partId}/field', [SparePartController::class, 'updateField']);
        Route::put('/{partId}/toggle-mrk', [SparePartController::class, 'toggleSelectedMrk']);
        Route::delete('/{partId}', [SparePartController::class, 'destroy']);
    });

    // Справочники типовых работ и цен СТО
    Route::prefix('standard-works')->group(function () {
        Route::get('services-list', [\App\Http\Controllers\Api\StandardWorkController::class, 'getServicesList']);
        Route::get('service-prices/{serviceId}', [\App\Http\Controllers\Api\StandardWorkController::class, 'getServicePrices']);
        Route::get('/', [\App\Http\Controllers\Api\StandardWorkController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\StandardWorkController::class, 'store']);
        Route::put('{id}/field', [\App\Http\Controllers\Api\StandardWorkController::class, 'updateField']);
        Route::post('service-prices/{serviceId}', [\App\Http\Controllers\Api\StandardWorkController::class, 'setServicePrice']);
        Route::delete('{id}', [\App\Http\Controllers\Api\StandardWorkController::class, 'destroy']);
        Route::get('/application-options', [\App\Http\Controllers\Api\StandardWorkController::class, 'getApplicationOptions']);
    });

    // Маршруты для плановых работ дела
    Route::prefix('applications/{id}/plan-works')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\PlanWorkController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\PlanWorkController::class, 'store']); // <-- ЭТОГО МАРШРУТА НЕ БЫЛО
        Route::put('/{workId}/field', [\App\Http\Controllers\Api\PlanWorkController::class, 'updateField']);
        Route::delete('/{workId}', [\App\Http\Controllers\Api\PlanWorkController::class, 'destroy']);
        Route::match(['put', 'patch', 'post'], '/{workId}/row-approval', [\App\Http\Controllers\Api\PlanWorkController::class, 'setRowApproval']);
        Route::post('/sync-service-prices', [\App\Http\Controllers\Api\PlanWorkController::class, 'syncPricesWithService']);
        Route::post('/service-approval', [\App\Http\Controllers\Api\PlanWorkController::class, 'submitServiceApproval']);
        Route::post('/complete-approval', [\App\Http\Controllers\Api\PlanWorkController::class, 'completeApproval']);
        Route::post('/sync-service-prices', [\App\Http\Controllers\Api\PlanWorkController::class, 'syncPricesWithService']);
        Route::get('/history', [\App\Http\Controllers\Api\PlanWorkController::class, 'history']);
    });

    Route::prefix('applications/{id}/fact-works')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\FactWorkController::class, 'index']);
        Route::get('/history', [\App\Http\Controllers\Api\FactWorkController::class, 'history']);
        Route::post('/', [\App\Http\Controllers\Api\FactWorkController::class, 'store']);
        Route::post('/import-from-plan', [\App\Http\Controllers\Api\FactWorkController::class, 'importFromPlan']);
        Route::put('/{workId}/field', [\App\Http\Controllers\Api\FactWorkController::class, 'updateField']);
        Route::delete('/{workId}', [\App\Http\Controllers\Api\FactWorkController::class, 'destroy']);
    });

    Route::get(
        'applications/{id}/finance/summary',
        [
            \App\Http\Controllers\Api\ApplicationFinanceController::class,
            'summary',
        ]
    )->whereNumber('id');

    Route::prefix('applications/{id}/extra-expenses')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\ExtraExpenseController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\ExtraExpenseController::class, 'store']);
        Route::get('/history', [\App\Http\Controllers\Api\ExtraExpenseController::class, 'history']);
        Route::put('/{expenseId}/field', [\App\Http\Controllers\Api\ExtraExpenseController::class, 'updateField']);
        Route::delete('/{expenseId}', [\App\Http\Controllers\Api\ExtraExpenseController::class, 'destroy']);
    });

    Route::prefix('applications/{id}/calculation-works')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\CalculationWorkController::class, 'index']);
        Route::get('/history', [\App\Http\Controllers\Api\CalculationWorkController::class, 'history']);
        Route::post('/', [\App\Http\Controllers\Api\CalculationWorkController::class, 'store']);
        Route::post('/materials', [\App\Http\Controllers\Api\CalculationWorkController::class, 'saveMaterials']);
        Route::put('/{workId}/field', [\App\Http\Controllers\Api\CalculationWorkController::class, 'updateField']);
        Route::post('/{workId}/transfer', [\App\Http\Controllers\Api\CalculationWorkController::class, 'transfer']);
        Route::delete('/{workId}', [\App\Http\Controllers\Api\CalculationWorkController::class, 'destroy']);
    });

    Route::prefix('estimate')->group(function () {
        Route::get('/application/{applicationId}', [\App\Http\Controllers\Api\EstimateController::class, 'showByApplication']);
        Route::post('/{id}/update', [\App\Http\Controllers\Api\EstimateController::class, 'update']);
    });
    Route::patch(
        'applications/{id}/finance/vat-mode',
        [
            \App\Http\Controllers\Api\ApplicationFinanceController::class,
            'updateVatMode',
        ]
    )->whereNumber('id');
    Route::patch(
        'applications/{id}/finance/approved-sum',
        [
            \App\Http\Controllers\Api\ApplicationFinanceController::class,
            'updateApprovedSum',
        ]
    )->whereNumber('id');

    Route::patch(
        'applications/{id}/finance/cash',
        [
            \App\Http\Controllers\Api\ApplicationFinanceController::class,
            'updateDds',
        ]
    )->whereNumber('id');

    Route::get(
        'applications/{id}/finance/salary-rates',
        [
            \App\Http\Controllers\Api\ApplicationFinanceController::class,
            'salaryRates',
        ]
    )->whereNumber('id');

    Route::patch(
        'applications/{id}/finance/salary-rates',
        [
            \App\Http\Controllers\Api\ApplicationFinanceController::class,
            'updateZpPercentages',
        ]
    )->whereNumber('id');

    Route::get(
        'applications/{id}/documents',
        [DocumentController::class, 'index']
    )->whereNumber('id');

    Route::patch(
        'applications/{id}/documents/{documentId}/type',
        [
            \App\Http\Controllers\Api\DocumentController::class,
            'assignType',
        ]
    )
        ->whereNumber('id')
        ->whereNumber('documentId');

    Route::post(
        'applications/{id}/document-upload-batches',
        [
            \App\Http\Controllers\Api\DocumentUploadBatchController::class,
            'store',
        ]
    )->whereNumber('id');

    Route::get(
        'applications/{id}/document-upload-batches/{batchId}',
        [
            \App\Http\Controllers\Api\DocumentUploadBatchController::class,
            'show',
        ]
    )
        ->whereNumber('id')
        ->whereNumber('batchId');

    Route::post(
        'applications/{id}/document-upload-batches/{batchId}'
        . '/items/{itemId}/chunks/{chunkIndex}',
        [
            \App\Http\Controllers\Api\DocumentUploadChunkController::class,
            'store',
        ]
    )
        ->whereNumber('id')
        ->whereNumber('batchId')
        ->whereNumber('itemId')
        ->whereNumber('chunkIndex');

    Route::get(
        'applications/{id}/document-upload-batches/{batchId}'
        . '/items/{itemId}/upload-state',
        [
            \App\Http\Controllers\Api\DocumentUploadChunkController::class,
            'show',
        ]
    )
        ->whereNumber('id')
        ->whereNumber('batchId')
        ->whereNumber('itemId');

    Route::post(
        'applications/{id}/document-upload-batches/{batchId}'
        . '/items/{itemId}/complete',
        [
            \App\Http\Controllers\Api\DocumentUploadCompletionController::class,
            'store',
        ]
    )
        ->whereNumber('id')
        ->whereNumber('batchId')
        ->whereNumber('itemId');

    Route::get(
        'applications/{applicationId}/documents/{documentId}/files',
        [
            \App\Http\Controllers\Api\DocumentFileController::class,
            'index',
        ]
    )
        ->whereNumber('applicationId')
        ->whereNumber('documentId')
        ->name('api.documents.files.index');

    Route::post(
        'applications/{applicationId}/documents/{documentId}'
        . '/files/{fileId}/download-link',
        [
            \App\Http\Controllers\Api\DocumentFileController::class,
            'downloadLink',
        ]
    )
        ->whereNumber('applicationId')
        ->whereNumber('documentId')
        ->whereNumber('fileId')
        ->name('api.documents.files.download-link');
    Route::post(
        'applications/{applicationId}/documents/{documentId}'
        . '/files/{fileId}/preview-link',
        [
            \App\Http\Controllers\Api\DocumentPreviewController::class,
            'link',
        ]
    )
        ->whereNumber('applicationId')
        ->whereNumber('documentId')
        ->whereNumber('fileId');

    Route::get(
        'applications/{applicationId}/documents/{documentId}/gallery',
        [
            \App\Http\Controllers\Api\PhotoGalleryController::class,
            'index',
        ]
    )
        ->whereNumber('applicationId')
        ->whereNumber('documentId');

    Route::put(
        'applications/{applicationId}/documents/{documentId}/gallery/order',
        [
            \App\Http\Controllers\Api\PhotoGalleryController::class,
            'reorder',
        ]
    )
        ->whereNumber('applicationId')
        ->whereNumber('documentId');
    Route::patch(
        'applications/{applicationId}/documents/{documentId}/title',
        [
            \App\Http\Controllers\Api\DocumentActionController::class,
            'rename',
        ]
    )
        ->whereNumber('applicationId')
        ->whereNumber('documentId')
        ->name('api.documents.rename');
    // Комплекты назначения для копирования и перемещения фотографий.
    Route::get(
        'applications/{applicationId}/documents/{documentId}/photo-destinations',
        [
            \App\Http\Controllers\Api\DocumentActionController::class,
            'destinations',
        ]
    )
        ->whereNumber('applicationId')
        ->whereNumber('documentId');

    // Удаление, копирование и перемещение выбранных фотографий.
    Route::post(
        'applications/{applicationId}/documents/{documentId}/photo-actions',
        [
            \App\Http\Controllers\Api\DocumentActionController::class,
            'photos',
        ]
    )
        ->whereNumber('applicationId')
        ->whereNumber('documentId');

    // Удаление целого документа или фотокомплекта.
    Route::delete(
        'applications/{applicationId}/documents/{documentId}',
        [
            \App\Http\Controllers\Api\DocumentActionController::class,
            'destroy',
        ]
    )
        ->whereNumber('applicationId')
        ->whereNumber('documentId')
        ->name('api.documents.destroy');
    Route::post(
        'applications/{applicationId}/documents/{documentId}/download',
        [
            \App\Http\Controllers\Api\DocumentDownloadController::class,
            'prepare',
        ]
    )
        ->whereNumber('applicationId')
        ->whereNumber('documentId');
    Route::get(
        'applications/{applicationId}/documents/{documentId}'
        . '/archives/{archiveFileId}/entries',
        [
            \App\Http\Controllers\Api\ArchiveEntryController::class,
            'index',
        ]
    )
        ->whereNumber('applicationId')
        ->whereNumber('documentId')
        ->whereNumber('archiveFileId');
    // Эндпоинт опций справочника работ для сметы

});

// ==========================================
// 6. ПАНЕЛЬ АДМИНИСТРИРОВАНИЯ (ADMIN)
// ==========================================
Route::middleware(['auth:sanctum', \App\Http\Middleware\EnsureIsSystemAdmin::class])
    ->prefix('admin')
    ->group(function () {
        Route::apiResource('users', UserController::class);
        Route::apiResource('user-groups', UserGroupController::class);
        Route::apiResource('legal-entities', LegalEntityController::class);
        Route::apiResource('our-companies', OurCompanyController::class);
        Route::apiResource('cities', CityController::class);
        Route::apiResource('finance-statuses', FinanceStatusController::class);
        Route::apiResource('insurance-companies', InsuranceCompanyController::class);
        Route::apiResource('application-statuses', ApplicationStatusController::class);
        Route::get('services/users', [\App\Http\Controllers\Api\Admin\ServiceController::class, 'getServiceUsers']);
        Route::apiResource('services', \App\Http\Controllers\Api\Admin\ServiceController::class);
        Route::post('calendar-exceptions/defaults', [\App\Http\Controllers\Api\Admin\CalendarExceptionController::class, 'generateDefaults']);
        Route::apiResource('calendar-exceptions', \App\Http\Controllers\Api\Admin\CalendarExceptionController::class);

    });
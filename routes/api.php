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
    Route::delete('/applications/{id}/documents/{documentId}', [ApplicationDocumentController::class, 'destroy']);
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

    // Калькуляция: ремонтные и окрасочные работы
    Route::get('applications/{id}/calc-works', [\App\Http\Controllers\Api\EstimateWorksController::class, 'getCalcWorks']);
    Route::post('applications/{id}/calc-works', [\App\Http\Controllers\Api\EstimateWorksController::class, 'storeCalcWork']);
    Route::put('applications/{id}/calc-works/{workId}/field', [\App\Http\Controllers\Api\EstimateWorksController::class, 'updateCalcWorkField']);
    Route::post('applications/{id}/calc-works/{workId}/toggle-category', [\App\Http\Controllers\Api\EstimateWorksController::class, 'toggleCategory']);
    Route::delete('applications/{id}/calc-works/{workId}', [\App\Http\Controllers\Api\EstimateWorksController::class, 'destroyCalcWork']);

    // Фактические работы СТО
    Route::get('applications/{id}/fact-works', [\App\Http\Controllers\Api\EstimateWorksController::class, 'getFactWorks']);
    Route::post('applications/{id}/fact-works', [\App\Http\Controllers\Api\EstimateWorksController::class, 'storeFactWork']);
    Route::put('applications/{id}/fact-works/{workId}/field', [\App\Http\Controllers\Api\EstimateWorksController::class, 'updateFactWorkField']);
    Route::delete('applications/{id}/fact-works/{workId}', [\App\Http\Controllers\Api\EstimateWorksController::class, 'destroyFactWork']);

    // Дополнительные затраты
    Route::get('applications/{id}/extra-expenses', [\App\Http\Controllers\Api\EstimateWorksController::class, 'getExtraExpenses']);
    Route::post('applications/{id}/extra-expenses', [\App\Http\Controllers\Api\EstimateWorksController::class, 'storeExtraExpense']);
    Route::put('applications/{id}/extra-expenses/{expenseId}/field', [\App\Http\Controllers\Api\EstimateWorksController::class, 'updateExtraExpenseField']);
    Route::delete('applications/{id}/extra-expenses/{expenseId}', [\App\Http\Controllers\Api\EstimateWorksController::class, 'destroyExtraExpense']);
    // Маршруты для плановых работ дела
    Route::prefix('applications/{id}/plan-works')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\PlanWorkController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\PlanWorkController::class, 'store']); // <-- ЭТОГО МАРШРУТА НЕ БЫЛО
        Route::put('/{workId}/field', [\App\Http\Controllers\Api\PlanWorkController::class, 'updateField']);
        Route::delete('/{workId}', [\App\Http\Controllers\Api\PlanWorkController::class, 'destroy']);
        Route::post('/sync-service-prices', [\App\Http\Controllers\Api\PlanWorkController::class, 'syncPricesWithService']);
        Route::post('/service-approval', [\App\Http\Controllers\Api\PlanWorkController::class, 'submitServiceApproval']);
        Route::post('/complete-approval', [\App\Http\Controllers\Api\PlanWorkController::class, 'completeApproval']);
    });

    // Эндпоинт опций справочника работ для сметы
    Route::get('standard-works/application-options', [\App\Http\Controllers\Api\Admin\StandardWorkController::class, 'getApplicationOptions']);

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
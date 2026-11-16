<?php

use Illuminate\Support\Facades\Route;

// Контроллеры авторизации и основных модулей
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ApplicationController;
use App\Http\Controllers\Api\SparePartController;
use App\Http\Controllers\Api\WorkController;
use App\Http\Controllers\Api\TroubleshootingController;
use App\Http\Controllers\Api\AttachmentController;

// Контроллеры модуля администрирования
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\Admin\UserGroupController;
use App\Http\Controllers\Api\Admin\InsuranceCompanyController;
use App\Http\Controllers\Api\Admin\ApplicationStatusController;
use App\Http\Controllers\Api\Admin\CityController;
use App\Http\Controllers\Api\Admin\LegalEntityController;
use App\Http\Controllers\Api\Admin\OurCompanyController;
/*
|--------------------------------------------------------------------------
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

    // Текущий пользователь и выход
    Route::get('/auth/me', [AuthController::class, 'me']);
    // Маршрут обновления профиля (PUT)
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // ==========================================
    // 1. ЗАЯВКИ (APPLICATIONS)
    // ==========================================
    Route::apiResource('applications', ApplicationController::class);

    // ==========================================
    // 2. ЗАПЧАСТИ (SPARE PARTS)
    // ==========================================
    Route::get('applications/{id}/spare-parts', [SparePartController::class, 'index']);
    Route::post('applications/{id}/spare-parts', [SparePartController::class, 'store']);
    Route::post('applications/{id}/spare-parts/batch-save', [SparePartController::class, 'batchSave']);
    Route::put('spare-parts/{id}', [SparePartController::class, 'update']);
    Route::delete('spare-parts/{id}', [SparePartController::class, 'destroy']);

    // ==========================================
    // 3. РАБОТЫ И НОРМО-ЧАСЫ (WORKS)
    // ==========================================
    Route::get('applications/{id}/works', [WorkController::class, 'index']);
    Route::post('applications/{id}/works', [WorkController::class, 'store']);
    Route::put('works/{id}', [WorkController::class, 'update']);
    Route::delete('works/{id}', [WorkController::class, 'destroy']);

    // ==========================================
    // 4. ДЕФЕКТОВКА И АКТЫ WORD (TROUBLESHOOTING)
    // ==========================================
    Route::get('applications/{id}/troubleshootings', [TroubleshootingController::class, 'index']);
    Route::post('applications/{id}/troubleshootings', [TroubleshootingController::class, 'store']);
    Route::put('troubleshootings/{id}', [TroubleshootingController::class, 'update']);
    Route::delete('troubleshootings/{id}', [TroubleshootingController::class, 'destroy']);
    Route::get('applications/{id}/export-act', [TroubleshootingController::class, 'exportDocx']);

    // ==========================================
    // 5. ДОКУМЕНТЫ И ФАЙЛЫ S3 (ATTACHMENTS)
    // ==========================================
    Route::get('applications/{id}/attachments', [AttachmentController::class, 'index']);
    Route::post('applications/{id}/attachments', [AttachmentController::class, 'upload']);
    Route::delete('attachments/{id}', [AttachmentController::class, 'destroy']);



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
        Route::apiResource('insurance-companies', InsuranceCompanyController::class);
        Route::apiResource('application-statuses', ApplicationStatusController::class);
    });
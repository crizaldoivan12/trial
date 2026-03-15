<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\EditRequestController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PasswordResetRequestController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| These routes are prefixed with /api by the HTTP kernel. They return JSON
| responses only and are consumed by the Next.js frontend.
|
*/

Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/password-reset-requests', [PasswordResetRequestController::class, 'store']);

Route::middleware('auth:sanctum')->group(function () {
    // Authenticated user info & logout
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Department CRUD
    Route::get('/departments', [DepartmentController::class, 'index']);
    Route::post('/departments', [DepartmentController::class, 'store']);
    Route::post('/departments/bulk-delete', [DepartmentController::class, 'bulkDelete']);
    Route::get('/departments/{department}', [DepartmentController::class, 'show']);
    Route::put('/departments/{department}', [DepartmentController::class, 'update']);
    Route::delete('/departments/{department}', [DepartmentController::class, 'destroy']);

    // User management (admin only, enforced in controller)
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{user}', [UserController::class, 'update']);
    Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword']);
    Route::post('/users/{user}/approve', [UserController::class, 'approve']);
    Route::post('/users/{user}/reject', [UserController::class, 'reject']);

    // Password reset approval workflow
    Route::get('/password-reset-requests', [PasswordResetRequestController::class, 'index']);
    Route::get('/password-reset-requests/notifications', [PasswordResetRequestController::class, 'notifications']);
    Route::post('/password-reset-requests/notifications/mark-read', [PasswordResetRequestController::class, 'markRead']);
    Route::get('/password-reset-requests/{passwordResetRequest}/reveal', [PasswordResetRequestController::class, 'reveal']);
    Route::post('/password-reset-requests/{passwordResetRequest}/approve', [PasswordResetRequestController::class, 'approve']);
    Route::post('/password-reset-requests/{passwordResetRequest}/reject', [PasswordResetRequestController::class, 'reject']);

    // Edit request workflow (user-to-user)
    Route::post('/documents/{document}/edit-requests', [EditRequestController::class, 'store']);
    Route::get('/edit-requests/incoming', [EditRequestController::class, 'incoming']);
    Route::get('/edit-requests/outgoing', [EditRequestController::class, 'outgoing']);
    Route::get('/edit-requests/notifications', [EditRequestController::class, 'notifications']);
    Route::post('/edit-requests/notifications/mark-read', [EditRequestController::class, 'markRead']);
    Route::post('/edit-requests/{editRequest}/accept', [EditRequestController::class, 'accept']);
    Route::post('/edit-requests/{editRequest}/reject', [EditRequestController::class, 'reject']);

    // Document CRUD & filtering
    // IMPORTANT: Specific routes must come BEFORE parameterized routes
    Route::get('/documents', [DocumentController::class, 'index']);
    Route::post('/documents', [DocumentController::class, 'store']);
    Route::get('/documents/recent', [DocumentController::class, 'recent']);
    Route::get('/documents/routing-history', [DocumentController::class, 'routingHistory']);
    Route::get('/documents/export/excel', [DocumentController::class, 'exportExcel']);
    Route::get('/documents/export/pdf', [DocumentController::class, 'exportPdf']);
    Route::post('/documents/bulk-status', [DocumentController::class, 'bulkStatusUpdate']);
    Route::post('/documents/{document}/inactivity-reason', [DocumentController::class, 'setInactivityReason']);
    Route::get('/documents/{document}/routing-history', [DocumentController::class, 'documentRoutingHistory']);
    // Document history (admin-only) – must be before generic {document} route
    Route::get('/documents/{document}/history', [DocumentController::class, 'history']);
    Route::get('/documents/{document}', [DocumentController::class, 'show']);
    Route::put('/documents/{document}', [DocumentController::class, 'update']);
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy']);

    // Dashboard & utilities
    Route::get('/dashboard/metrics', [DocumentController::class, 'metrics']);

    // Admin analytics & reporting
    Route::get('/reports/analytics', [ReportController::class, 'analytics']);
    Route::get('/reports/history', [ReportController::class, 'history']);
    Route::get('/reports/schedule', [ReportController::class, 'scheduleShow']);
    Route::post('/reports/schedule', [ReportController::class, 'scheduleUpdate']);
    Route::post('/reports/schedule/run', [ReportController::class, 'scheduleRun']);
    Route::get('/reports/export/summary', [ReportController::class, 'exportSummaryPdf']);
    Route::get('/reports/export/departments', [ReportController::class, 'exportDepartmentPdf']);
    Route::get('/reports/export/monthly', [ReportController::class, 'exportMonthlyPdf']);
});

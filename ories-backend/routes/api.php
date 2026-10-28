<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DriveImportController;
use App\Http\Controllers\LookupController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrgChartController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TargetController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VerificationController;
use Illuminate\Support\Facades\Route;

// Public Routes
Route::middleware('throttle:10,1')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
});
Route::get('/lookups', [LookupController::class, 'index']);
Route::get('/certificates/verify/{code}', [CertificateController::class, 'verifyPublic'])->middleware('throttle:30,1');

// Protected Routes (Require a valid Sanctum token)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/me/password', [AuthController::class, 'updatePassword']);

    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/dashboard/people', [DashboardController::class, 'people']);

    // Records, lifecycle updates, and Means of Verification
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::post('/projects/check-title', [ProjectController::class, 'checkTitle']);
    Route::get('/projects/{project}', [ProjectController::class, 'show']);
    Route::post('/projects/{project}', [ProjectController::class, 'update']); // multipart resubmission
    Route::post('/projects/{project}/updates', [ProjectController::class, 'submitUpdate']);
    Route::post('/projects/{project}/details', [ProjectController::class, 'submitDetails']); // multipart change of details
    Route::post('/updates/{update}/withdraw', [ProjectController::class, 'withdrawUpdate']);
    Route::get('/attachments/{attachment}', [AttachmentController::class, 'download']);

    // Verification Module
    Route::get('/verification', [VerificationController::class, 'index']);
    Route::post('/verification/{update}/verify', [VerificationController::class, 'verify']);
    Route::post('/verification/{update}/return', [VerificationController::class, 'return']);

    // Certificates
    Route::get('/certificates', [CertificateController::class, 'index']);
    Route::post('/certificates', [CertificateController::class, 'store']);
    Route::get('/certificates/{certificate}', [CertificateController::class, 'show']);
    Route::post('/certificates/{certificate}/issue', [CertificateController::class, 'issue']);
    Route::post('/certificates/{certificate}/decline', [CertificateController::class, 'decline']);

    // Dynamic Reports Module
    Route::get('/reports', [ReportController::class, 'types']);
    Route::get('/reports/{type}', [ReportController::class, 'show']);
    Route::get('/targets', [TargetController::class, 'index']);
    Route::put('/targets', [TargetController::class, 'save']);

    Route::get('/org-chart', [OrgChartController::class, 'index']);
    Route::get('/org-chart/people/{user}', [OrgChartController::class, 'person']);
    Route::get('/people', [LookupController::class, 'people']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read']);

    // Admin Module
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{user}', [UserController::class, 'update']);
    Route::post('/users/{user}/approve', [UserController::class, 'approve']);

    Route::get('/drive/status', [DriveImportController::class, 'status']);
    Route::get('/drive/files', [DriveImportController::class, 'files']);
    Route::post('/drive/import', [DriveImportController::class, 'import']);
});

<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DeliverableController;
use App\Http\Controllers\IngestionController;

// Public Routes
Route::post('/login', [AuthController::class, 'login']);

// Protected Routes (Require a valid Sanctum token)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/deliverables', [DeliverableController::class, 'store']);
    Route::get('/deliverables', [DeliverableController::class, 'index']);
    Route::patch('/deliverables/{id}/status', [DeliverableController::class, 'updateStatus']);
    Route::get('/ingestion/sync', [IngestionController::class, 'fetchDriveFiles']);
    Route::get('/deliverables/{id}', [DeliverableController::class, 'show']);
    Route::put('/deliverables/{id}', [DeliverableController::class, 'update']);
    
    Route::get('/user', function (Request $request) {
        return $request->user()->load('campus'); 
    });

    // Deliverable Encoding Route
    Route::post('/deliverables', [DeliverableController::class, 'store']);
});
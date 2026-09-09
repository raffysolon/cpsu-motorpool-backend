<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\CoordinatorAssignmentController;
use App\Http\Controllers\MyAssignmentController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\TripController;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/drivers', [DriverController::class, 'index']);
    Route::post('/drivers', [DriverController::class, 'store']);
    Route::put('/drivers/{driver}', [DriverController::class, 'update']);
    Route::delete('/drivers/{driver}', [DriverController::class, 'destroy']);
    Route::post('/drivers/{driver}/reset-password', [DriverController::class, 'resetPassword']);

    Route::get('/vehicles', [VehicleController::class, 'index']);
    Route::post('/vehicles', [VehicleController::class, 'store']);
    Route::put('/vehicles/{vehicle}', [VehicleController::class, 'update']);
    Route::delete('/vehicles/{vehicle}', [VehicleController::class, 'destroy']);

    Route::get('/coordinator-assignments', [CoordinatorAssignmentController::class, 'index']);
    Route::post('/coordinator-assignments', [CoordinatorAssignmentController::class, 'store']);
    Route::put('/coordinator-assignments/{assignment}', [CoordinatorAssignmentController::class, 'update']);
    Route::delete('/coordinator-assignments/{assignment}', [CoordinatorAssignmentController::class, 'destroy']);

    Route::get('/my-assignment', [MyAssignmentController::class, 'show']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::put('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::put('/change-password', [AuthController::class, 'changePassword']);

    Route::get('/trips', [TripController::class, 'index']);
    Route::get('/my-trips', [TripController::class, 'myTrips']);
    Route::get('/trips/preview-pdf', [TripController::class, 'previewPdf']);
    Route::post('/trips', [TripController::class, 'store']);
    Route::post('/trips/admin-create', [TripController::class, 'adminStore']);
    Route::get('/available-drivers', [TripController::class, 'availableDrivers']);
    Route::get('/available-vehicles', [TripController::class, 'availableVehicles']);
    Route::get('/trips/{trip}', [TripController::class, 'show']);
    Route::put('/trips/{trip}', [TripController::class, 'update']);
    Route::post('/trips/{trip}/start', [TripController::class, 'start']);
    Route::post('/trips/{trip}/end', [TripController::class, 'end']);
    Route::post('/trips/{trip}/start-return', [TripController::class, 'startReturn']);
    Route::post('/trips/{trip}/end-return', [TripController::class, 'endReturn']);
    Route::put('/trips/{trip}/approve', [TripController::class, 'approve']);
    Route::put('/trips/{trip}/deny', [TripController::class, 'deny']);
    Route::get('/trips/{trip}/print', [TripController::class, 'print']);
});

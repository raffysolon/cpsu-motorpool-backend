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

// Simple test route
Route::get('/test', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'Laravel API is working!',
        'timestamp' => now()
    ]);
});

// Test auth without middleware
Route::get('/auth-test', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'Auth system ready!',
        'sanctum_loaded' => class_exists('Laravel\Sanctum\Sanctum'),
        'user_model' => class_exists('App\Models\User')
    ]);
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Login endpoint - STRICT rate limit (5 attempts per minute)
Route::middleware('throttle:login')->post('/login', [AuthController::class, 'login']);

// Debug login endpoint without throttling
Route::post('/debug-login', function (Request $request) {
    try {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        return response()->json([
            'status' => 'validation_passed',
            'database_connection' => config('database.default'),
            'cache_store' => config('cache.default'),
            'timestamp' => now()
        ]);
    } catch (Exception $e) {
        return response()->json([
            'error' => 'Validation or config error',
            'message' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => basename($e->getFile()),
            'timestamp' => now()
        ], 500);
    }
});

// Simple login test
Route::post('/simple-login', function (Request $request) {
    return response()->json([
        'status' => 'received',
        'email' => $request->email,
        'has_password' => !empty($request->password),
        'timestamp' => now()
    ]);
});

// Simple config check route (force redeploy)
Route::get('/config-check', function () {
    return response()->json([
        'db_default' => config('database.default'),
        'cache_default' => config('cache.default'),
        'app_env' => config('app.env'),
        'timestamp' => now()->toISOString()
    ]);
});

// Test database connection and configuration
Route::get('/test-db', function () {
    try {
        return response()->json([
            'database_default' => config('database.default'),
            'connection_name' => config('database.connections.'.config('database.default').'.driver'),
            'database_name' => config('database.connections.'.config('database.default').'.database'),
            'host' => config('database.connections.'.config('database.default').'.host'),
            'cache_store' => config('cache.default'),
            'cache_database' => config('cache.stores.database.connection'),
            'timestamp' => now()
        ]);
    } catch (Exception $e) {
        return response()->json([
            'message' => 'Configuration error',
            'error' => $e->getMessage(),
            'timestamp' => now()
        ], 500);
    }
});

// Test actual database connection
Route::get('/test-db-connection', function () {
    try {
        $connection = \DB::connection();
        $pdo = $connection->getPdo();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        
        // Test if we can write to database
        $canWrite = false;
        try {
            \DB::table('cache')->where('key', 'test_write')->delete();
            \DB::table('cache')->insert([
                'key' => 'test_write',
                'value' => 'test',
                'expiration' => time() + 60
            ]);
            \DB::table('cache')->where('key', 'test_write')->delete();
            $canWrite = true;
        } catch (Exception $writeError) {
            $canWrite = false;
        }
        
        return response()->json([
            'pdo_driver' => $driver,
            'connection_name' => $connection->getName(),
            'can_write' => $canWrite,
            'tables_exist' => [
                'users' => \Schema::hasTable('users'),
                'personal_access_tokens' => \Schema::hasTable('personal_access_tokens'),
                'cache' => \Schema::hasTable('cache'),
                'jobs' => \Schema::hasTable('jobs')
            ],
            'timestamp' => now()
        ]);
    } catch (Exception $e) {
        return response()->json([
            'message' => 'Database connection error',
            'error' => $e->getMessage(),
            'timestamp' => now()
        ], 500);
    }
});

// ============================================
// ADMIN-ONLY ROUTES (with rate limiting)
// ============================================
Route::middleware(['auth:sanctum', 'role:admin', 'throttle:api'])->group(function () {
    // Driver Management (Admin Only)
    Route::get('/drivers', [DriverController::class, 'index']);
    Route::post('/drivers', [DriverController::class, 'store']);
    Route::put('/drivers/{driver}', [DriverController::class, 'update']);
    Route::delete('/drivers/{driver}', [DriverController::class, 'destroy']);
    Route::middleware('throttle:password-reset')->post('/drivers/{driver}/reset-password', [DriverController::class, 'resetPassword']);

    // Vehicle Management (Admin Only)
    Route::get('/vehicles', [VehicleController::class, 'index']);
    Route::post('/vehicles', [VehicleController::class, 'store']);
    Route::put('/vehicles/{vehicle}', [VehicleController::class, 'update']);
    Route::delete('/vehicles/{vehicle}', [VehicleController::class, 'destroy']);

    // Coordinator Assignment Management (Admin Only)
    Route::get('/coordinator-assignments', [CoordinatorAssignmentController::class, 'index']);
    Route::post('/coordinator-assignments', [CoordinatorAssignmentController::class, 'store']);
    Route::put('/coordinator-assignments/{assignment}', [CoordinatorAssignmentController::class, 'update']);
    Route::delete('/coordinator-assignments/{assignment}', [CoordinatorAssignmentController::class, 'destroy']);

    // Trip Management - Admin Actions
    Route::get('/trips', [TripController::class, 'index']); // View all trips
    Route::post('/trips/admin-create', [TripController::class, 'adminStore']); // Create trip as admin
    Route::get('/available-drivers', [TripController::class, 'availableDrivers']);
    Route::get('/available-vehicles', [TripController::class, 'availableVehicles']);
    Route::put('/trips/{trip}/approve', [TripController::class, 'approve']);
    Route::put('/trips/{trip}/deny', [TripController::class, 'deny']);
});

// ============================================
// AUTHENTICATED ROUTES (Admin + Driver with rate limiting)
// ============================================
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    // My Assignment (Driver/Coordinator can view their own)
    Route::get('/my-assignment', [MyAssignmentController::class, 'show']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::put('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    
    // Change Password (with stricter limit)
    Route::middleware('throttle:password-reset')->put('/change-password', [AuthController::class, 'changePassword']);

    // Trip Management - Shared Actions
    Route::get('/my-trips', [TripController::class, 'myTrips']); // Driver sees own trips
    Route::middleware('throttle:heavy')->get('/trips/preview-pdf', [TripController::class, 'previewPdf']);
    Route::post('/trips', [TripController::class, 'store']); // Driver creates trip request
    Route::get('/trips/{trip}', [TripController::class, 'show']);
    Route::put('/trips/{trip}', [TripController::class, 'update']); // Has auth check inside controller
    Route::post('/trips/{trip}/start', [TripController::class, 'start']); // Driver only
    Route::post('/trips/{trip}/end', [TripController::class, 'end']); // Driver only
    Route::post('/trips/{trip}/start-return', [TripController::class, 'startReturn']); // Driver only
    Route::post('/trips/{trip}/end-return', [TripController::class, 'endReturn']); // Driver only
    Route::middleware('throttle:heavy')->get('/trips/{trip}/print', [TripController::class, 'print']);
});

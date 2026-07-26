<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\FoodLogController;
use App\Http\Controllers\API\ProfileController;
use App\Http\Controllers\API\ScanController;
use App\Http\Controllers\API\ChatbotController;
use App\Http\Controllers\API\DashboardController;
use App\Http\Controllers\API\MealPlannerController;
use App\Http\Controllers\API\HealthReportController;

// Public Routes - Authentication
Route::post('/register', [AuthController::class, 'register']);
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/resend-otp', [AuthController::class, 'resendOtp']);

// Protected Routes - Require Authentication
Route::middleware('auth:sanctum')->group(function () {
    // User Info
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::post('/logout', [AuthController::class, 'logout']);

    // Profile Management (Diabetes Mellitus System)
    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'getProfile']);
        Route::post('/basic-data', [ProfileController::class, 'storeBasicData']); // Step 1
        Route::put('/health-condition', [ProfileController::class, 'updateHealthCondition']); // Step 2
        Route::put('/health-targets', [ProfileController::class, 'updateHealthTargets']); // Step 3
        Route::put('/', [ProfileController::class, 'updateProfile']); // Update full profile
    });

    // AI NutriScan - Food Recognition & Analysis
    Route::post('/scan', [ScanController::class, 'scan']);

    // Food Logs
    Route::prefix('food-logs')->group(function () {
        Route::get('/', [FoodLogController::class, 'index']); // Get all logs
        Route::post('/', [FoodLogController::class, 'store']); // Save food log
        Route::get('/today', [FoodLogController::class, 'todayMenu']); // Get today's menu
        Route::post('/analyze-menu', [FoodLogController::class, 'analyzeMenu']); // Analyze multiple food items
        Route::get('/{id}', [FoodLogController::class, 'show']); // Get single log detail
        Route::put('/{id}', [FoodLogController::class, 'update']); // Update log notes
        Route::delete('/{id}', [FoodLogController::class, 'destroy']); // Delete log
    });

    // Dashboard
    Route::prefix('dashboard')->group(function () {
        Route::get('/summary', [DashboardController::class, 'summary']); // Daily summary
        Route::get('/weekly', [DashboardController::class, 'weeklyProgress']); // Weekly chart
    });

    // AI Meal Planner
    Route::prefix('meal-planner')->group(function () {
        Route::get('/', [MealPlannerController::class, 'index']); // Get all meal plans
        Route::post('/generate', [MealPlannerController::class, 'generate']); // Generate new meal plan
        Route::get('/{id}', [MealPlannerController::class, 'show']); // Get meal plan detail
        Route::delete('/{id}', [MealPlannerController::class, 'destroy']); // Delete meal plan
    });

    // NutriBot - AI Chatbot with RAG
    Route::post('/chatbot', [ChatbotController::class, 'chat']);

    // Health Report
    Route::prefix('health-report')->group(function () {
        Route::post('/generate', [HealthReportController::class, 'generate']); // Generate report
        Route::post('/export-pdf', [HealthReportController::class, 'exportPdf']); // Export PDF
    });
});

<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\FoodLogController;
use App\Http\Controllers\API\GrowthController;
use App\Http\Controllers\API\ProfileController;
use App\Http\Controllers\API\ScanController;
use App\Http\Controllers\API\ChatbotController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/resend-otp', [AuthController::class, 'resendOtp']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/profile', [ProfileController::class, 'getProfile']);
    Route::post('/profile/child', [ProfileController::class, 'storeChild']);
    Route::put('/profile/child/{id}', [ProfileController::class, 'updateChild']);
    Route::delete('/profile/child/{id}', [ProfileController::class, 'destroyChild']);
    Route::put('/profile/mother', [ProfileController::class, 'updateMotherProfile']);

    Route::get('/food-logs', [FoodLogController::class, 'index']);
    Route::post('/food-logs', [FoodLogController::class, 'store']);
    Route::put('/food-logs/{id}', [FoodLogController::class, 'update']);
    Route::delete('/food-logs/{id}', [FoodLogController::class, 'destroy']);
    Route::get('/dashboard/summary', [FoodLogController::class, 'getSummary']);

    Route::post('/scan', [ScanController::class, 'scan']);
    Route::post('/chatbot', [ChatbotController::class, 'chat']);

    Route::get('/growth-records', [GrowthController::class, 'index']);
    Route::post('/growth-records', [GrowthController::class, 'store']);
});

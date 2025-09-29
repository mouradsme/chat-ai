<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ChatbotController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\QueryController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Chatbot management routes
Route::prefix('chatbots')->group(function () {
    Route::get('/', [ChatbotController::class, 'index']);
    Route::post('/', [ChatbotController::class, 'store']);
    Route::get('/{chatbot}', [ChatbotController::class, 'show']);
    Route::put('/{chatbot}', [ChatbotController::class, 'update']);
    Route::delete('/{chatbot}', [ChatbotController::class, 'destroy']);
    
    // Document management for specific chatbot
    Route::prefix('{chatbot}/documents')->group(function () {
        Route::get('/', [DocumentController::class, 'index']);
        Route::post('/upload', [DocumentController::class, 'upload']);
        Route::delete('/', [DocumentController::class, 'destroyAll']);
        Route::get('/stats', [DocumentController::class, 'stats']);
    });
    
    // Query endpoints for specific chatbot
    Route::prefix('{chatbot}/query')->group(function () {
        Route::post('/', [QueryController::class, 'query']);
        Route::get('/status', [QueryController::class, 'status']);
    });
});

// Health check endpoint
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now(),
        'version' => '1.0.0'
    ]);
});
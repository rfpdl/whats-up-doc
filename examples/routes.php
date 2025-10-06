<?php

use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes Example
|--------------------------------------------------------------------------
| 
| This shows how What's Up Doc will automatically detect and document
| your API routes that use Laravel Data classes.
|
*/

Route::prefix('api/v1')->group(function () {
    // User management routes
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);
});

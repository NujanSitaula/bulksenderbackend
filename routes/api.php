<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BatchController;
use App\Http\Controllers\ContactController;

Route::post('/auth/login', [AuthController::class, 'login']);

Route::get('/auth/me', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/contacts', [ContactController::class, 'index']);
    Route::post('/contacts', [ContactController::class, 'store']);
    Route::delete('/contacts/{id}', [ContactController::class, 'destroy']);

    Route::get('/batches', [BatchController::class, 'index']);
    Route::post('/batches', [BatchController::class, 'store']);
    Route::get('/batches/{id}', [BatchController::class, 'show']);
    Route::post('/batches/{id}/retry', [BatchController::class, 'retry']);
    Route::get('/batches/{id}/recipients', [BatchController::class, 'recipients']);
});

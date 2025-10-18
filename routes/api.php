<?php

use App\Http\Controllers\UserController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\ShareController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/test', function() {
    return response()->json(['message' => 'API is working']);
});

Route::post('/register-user', [UserController::class, 'register']);
Route::post('/login-user', [UserController::class, 'login']);
Route::post('/logout-user', [UserController::class, 'logout'])->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/my-files/{folderId?}', [FileController::class, 'myFiles'])->whereNumber('folderId');
    Route::get('/trash', [FileController::class, 'trash']);
    Route::post('/create-folder', [FileController::class, 'createFolder']);
    Route::post('/upload-files', [FileController::class, 'store']);
    Route::delete('/delete-file', [FileController::class, 'destroy']);
    Route::post('/share-file/{file}', [ShareController::class, 'store']);
    Route::post('/restore-file', [FileController::class, 'restore']);
    Route::get('/shared-file/{file_id}', [ShareController::class, 'index']);
    Route::get('/user-shared-files/{file_id}/{email}', [ShareController::class, 'userSharedFiles']);
    Route::get('/shared-with-me', [ShareController::class, 'sharedWithMe']);
});

Route::get('email/verify/{id}/{hash}', [UserController::class, 'verifyEmail'])
    ->name('verification.verify');

Route::get('email/resend', [UserController::class, 'resendVerificationEmail'])
    ->name('verification.resend');

Route::post('/forgot-password', [UserController::class, 'sendResetToken']);
Route::post('/verify-token', [UserController::class, 'verifyToken']);
Route::post('/reset-password', [UserController::class, 'resetPassword']);


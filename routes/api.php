<?php

use App\Http\Controllers\LabelController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\ShareController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Contracts\Role;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/test', function() {
    return response()->json(['message' => 'API is working']);
});
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
    Route::delete('/delete-file', [FileController::class, 'destroy']);
    Route::post('/share-file/{file_id}', [ShareController::class, 'store']);
    Route::post('/restore-file', [FileController::class, 'restore']);
    Route::post('/restore-file', [FileController::class, 'restore']);
    Route::get('/shared-file/{file_id}', [ShareController::class, 'index']);
    Route::get('/user-shared-files/{file_id}/{email}', [ShareController::class, 'userSharedFiles']);
    Route::get('/shared-with-me', [ShareController::class, 'sharedWithMe']);
    Route::get('/labels', [LabelController::class, 'index']);
    Route::get('/label/{labelId}', [LabelController::class, 'show']);
    Route::post('/create-label', [LabelController::class, 'store']);
    Route::patch('/update-label/{labelId}', [LabelController::class, 'update']);
    Route::delete('/delete-label/{labelId}', [LabelController::class, 'destroy']);
});

Route::get('email/verify/{id}/{hash}', [UserController::class, 'verifyEmail'])
    ->name('verification.verify');

Route::get('email/resend', [UserController::class, 'resendVerificationEmail'])
    ->name('verification.resend');

Route::post('/forgot-password', [UserController::class, 'sendResetToken']);
Route::post('/verify-token', [UserController::class, 'verifyToken']);
Route::post('/reset-password', [UserController::class, 'resetPassword']);


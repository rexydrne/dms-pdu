<?php

use App\Http\Controllers\LabelController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\ShareController;
use App\Models\Shareable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Contracts\Role;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/users', [UserController::class, 'index'])->middleware('auth:sanctum');

Route::get('/test', function() {
    return response()->json(['message' => 'API is working']);
});

Route::post('/register-user', [UserController::class, 'register']);
Route::post('/login-user', [UserController::class, 'login']);
// Route::get( '/unauthenticated', [UserController::class, 'unauthenticated'])->name('login');


Route::middleware('auth:sanctum')->group(function () {
    Route::get('/last-opened-files', [FileController::class, 'lastOpenedFiles']);
    Route::get('/recommended-files', [FileController::class, 'recommendedFiles']);
    Route::patch('/update-profile', [UserController::class, 'updateUserProfile']);
    Route::post('/logout-user', [UserController::class, 'logout']);
    Route::post('/change-password', [UserController::class, 'changePassword']);
    Route::post('/delete-photo-profile', [UserController::class, 'deletePhotoProfile']);

    Route::get('/my-files/{folderId?}', [FileController::class, 'myFiles'])->whereNumber('folderId');
    Route::post('/create-folder', [FileController::class, 'createFolder']);
    Route::post('/upload-files', [FileController::class, 'store']);
    Route::patch('/update-file/{fileId}', [FileController::class, 'update']);
    Route::delete('/delete-file/{fileId}', [FileController::class, 'destroy']);
    Route::get('view-file/{fileId}', [FileController::class, 'viewFile'])->name('file.view');
    Route::post('/download', [FileController::class, 'download']);
    Route::get('/storage-file', [FileController::class, 'serveStorageFile']);
    Route::get('/file-info/{fileId}', [FileController::class, 'getFileInfo']);
    Route::post('/duplicate-file', [FileController::class, 'duplicate']);

    Route::get('/trash', [FileController::class, 'trash']);
    Route::post('/restore-file', [FileController::class, 'restore']);
    Route::delete('/force-delete-file', [FileController::class, 'forceDestroy']);

    Route::post('/share-file/{file_id}', [ShareController::class, 'store']);
    Route::get('/shared-file/{file_id}', [ShareController::class, 'index']);
    Route::get('/user-shared-files/{file_id}/{email}', [ShareController::class, 'userSharedFiles']);
    Route::get('/shared-with-me', [ShareController::class, 'sharedWithMe']);
    Route::patch('/update-share/{share_id}', [ShareController::class, 'update']);
    Route::delete('/remove-share/{share_id}', [ShareController::class, 'destroy']);

    Route::get('/share/{token}', [ShareController::class, 'accessSharedFile'])
    ->name('file.share');

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

Route::get('/s/{token}', [ShareController::class, 'viewFilePublic'])
    ->name('file.share.public');



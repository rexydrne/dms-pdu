<?php

use App\Http\Controllers\LabelController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\ShareController;
use App\Http\Controllers\TrashController;
use App\Http\Controllers\LastOpenedController;
use App\Http\Controllers\RecommendedController;
use App\Http\Controllers\ShareNotificationController;
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

Route::get('/search-users', [UserController::class, 'searchUsers']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/recommended-files', [RecommendedController::class, 'recommendedFiles']);
    Route::get('/last-opened-files', [LastOpenedController::class, 'lastOpenedFiles']);
    Route::get('/my-files/{folderId?}', [FileController::class, 'myFiles'])->whereNumber('folderId');
    Route::patch('/update-profile', [UserController::class, 'updateUserProfile']);
    Route::post('/logout-user', [UserController::class, 'logout']);
    Route::post('/change-password', [UserController::class, 'changePassword']);
    Route::post('/delete-photo-profile', [UserController::class, 'deletePhotoProfile']);
    Route::get('/email/resend', [UserController::class, 'resendVerificationEmail'])
        ->name('verification.resend');

    Route::post('/create-folder', [FileController::class, 'createFolder']);
    Route::post('/upload-files', [FileController::class, 'store']);
    Route::patch('/update-file/{fileId}', [FileController::class, 'update']);
    Route::delete('/delete-file/{fileId}', [FileController::class, 'destroy']);
    Route::get('view-file/{fileId}', [FileController::class, 'viewFile'])->name('file.view');
    Route::post('/download', [FileController::class, 'download']);
    Route::get('/storage-file', [FileController::class, 'serveStorageFile']);
    Route::get('/file-info/{fileId}', [FileController::class, 'getFileInfo']);
    Route::post('/duplicate-file', [FileController::class, 'duplicate']);

    Route::get('/trash', [TrashController::class, 'trash']);
    Route::post('/restore-file', [TrashController::class, 'restore']);
    Route::delete('/force-delete-file', [TrashController::class, 'forceDestroy']);

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

    Route::get('/share-notifications', [ShareNotificationController::class, 'getAllNotifications']);
    Route::get('/share-notifications/unread', [ShareNotificationController::class, 'getUnreadNotifications']);
    Route::get('/share-notifications/read', [ShareNotificationController::class, 'getReadNotifications']);
    Route::post('/share-notifications/mark-as-read/{id}', [ShareNotificationController::class, 'markAsRead']);

});

Route::get('email/verify/{id}/{hash}', [UserController::class, 'verifyEmail'])
    ->name('verification.verify');

Route::post('/forgot-password', [UserController::class, 'sendResetToken']);
Route::post('/verify-token', [UserController::class, 'verifyToken']);
Route::post('/reset-password', [UserController::class, 'resetPassword']);

Route::get('/s/{token}', [ShareController::class, 'viewFilePublic'])
    ->name('file.share.public');



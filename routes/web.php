<?php

use Illuminate\Support\Facades\Route;
use Komalnakrani\ClientPortal\Http\Controllers\AuthController;
use Komalnakrani\ClientPortal\Http\Controllers\PortalController;
use Komalnakrani\ClientPortal\Http\Middleware\RedirectGuestsToLogin;

Route::prefix('portal')->name('client-portal.')->group(function () {
    Route::get('login', [AuthController::class, 'login'])->name('login');
    Route::get('register', [AuthController::class, 'register'])->name('register');
    Route::get('forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password');
    Route::get('reset-password', [AuthController::class, 'resetPassword'])->name('reset-password');

    Route::middleware(RedirectGuestsToLogin::class)->group(function () {
        Route::get('/', [PortalController::class, 'index'])->name('index');
        Route::get('{portal}', [PortalController::class, 'show'])->name('show');
        Route::post('{portal}/messages', [PortalController::class, 'message'])->middleware('throttle:20,1')->name('message');
        Route::get('{portal}/{module}', [PortalController::class, 'page'])->name('page');
        Route::get('{portal}/{module}/files/{index}', [PortalController::class, 'download'])->whereNumber('index')->name('download');
        Route::get('{portal}/{module}/uploads/{key}', [PortalController::class, 'downloadUpload'])->where('key', '[a-z0-9]+')->name('download-upload');
        Route::post('{portal}/{module}/complete', [PortalController::class, 'complete'])->name('complete');
        Route::post('{portal}/{module}/upload', [PortalController::class, 'upload'])->middleware('throttle:30,1')->name('upload');
    });
});

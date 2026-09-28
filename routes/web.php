<?php

use Illuminate\Support\Facades\Route;
use Komalnakrani\ClientPortal\Http\Controllers\PortalController;
use Komalnakrani\ClientPortal\Http\Middleware\RedirectGuestsToLogin;

Route::prefix('portal')->name('client-portal.')->group(function () {
    Route::get('login', [PortalController::class, 'login'])->name('login');

    Route::middleware(RedirectGuestsToLogin::class)->group(function () {
        Route::get('/', [PortalController::class, 'index'])->name('index');
        Route::get('{portal}', [PortalController::class, 'show'])->name('show');
        Route::get('{portal}/{module}', [PortalController::class, 'page'])->name('page');
    });
});

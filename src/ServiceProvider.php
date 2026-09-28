<?php

namespace Komalnakrani\ClientPortal;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Komalnakrani\ClientPortal\Console\InstallCommand;
use Komalnakrani\ClientPortal\Http\Controllers\CP\PortalController as CPPortalController;
use Komalnakrani\ClientPortal\Http\Controllers\CP\TemplateController as CPTemplateController;
use Komalnakrani\ClientPortal\Tags\ClientPortalTags;
use Statamic\Contracts\Entries\Entry;
use Statamic\Facades\CP\Nav;
use Statamic\Facades\User;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    protected $viewNamespace = 'client-portal';

    protected $tags = [
        ClientPortalTags::class,
    ];

    protected $commands = [
        InstallCommand::class,
    ];

    protected $publishables = [
        __DIR__.'/../resources/dist' => '',
    ];

    public function bootAddon(): void
    {
        Gate::define('view-client-portal', function ($user, Entry $portal): bool {
            return Portals::userCanView(User::fromUser($user), $portal);
        });

        Nav::extend(function ($nav) {
            $nav->content('Client Portals')
                ->section('Content')
                ->route('client-portal.index')
                ->icon('briefcase');
        });

        $this->registerCpRoutes(function () {
            Route::name('client-portal.')->prefix('client-portal')->group(function () {
                Route::get('/', [CPPortalController::class, 'index'])->name('index');
                Route::get('create', [CPPortalController::class, 'create'])->name('create');
                Route::post('/', [CPPortalController::class, 'store'])->name('store');
                Route::post('{portal}/duplicate', [CPPortalController::class, 'duplicate'])->name('duplicate');
                Route::delete('{portal}', [CPPortalController::class, 'destroy'])->name('destroy');
                Route::get('templates', [CPTemplateController::class, 'index'])->name('templates.index');
            });
        });
    }
}

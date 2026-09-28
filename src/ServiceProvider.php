<?php

namespace Komalnakrani\ClientPortal;

use Illuminate\Support\Facades\Gate;
use Komalnakrani\ClientPortal\Console\InstallCommand;
use Statamic\Contracts\Entries\Entry;
use Statamic\Facades\User;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    protected $viewNamespace = 'client-portal';

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
    }
}

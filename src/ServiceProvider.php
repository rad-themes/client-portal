<?php

namespace Komalnakrani\ClientPortal;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Gate;
use Komalnakrani\ClientPortal\Actions\ApplyTemplate;
use Komalnakrani\ClientPortal\Actions\NotifyClients;
use Komalnakrani\ClientPortal\Console\ExportCommand;
use Komalnakrani\ClientPortal\Console\ImportCommand;
use Komalnakrani\ClientPortal\Console\InstallCommand;
use Komalnakrani\ClientPortal\Console\SendDigestCommand;
use Komalnakrani\ClientPortal\Console\SendRemindersCommand;
use Komalnakrani\ClientPortal\Listeners\PreserveClientProgress;
use Komalnakrani\ClientPortal\Listeners\SetUpRegisteredClient;
use Komalnakrani\ClientPortal\Listeners\VerifyCaptcha;
use Komalnakrani\ClientPortal\Tags\ClientPortal;
use Statamic\Contracts\Entries\Entry;
use Statamic\Events\EntrySaving;
use Statamic\Events\UserRegistered;
use Statamic\Events\UserRegistering;
use Statamic\Facades\User;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    protected $viewNamespace = 'client-portal';

    protected $actions = [
        ApplyTemplate::class,
        NotifyClients::class,
    ];

    protected $commands = [
        InstallCommand::class,
        SendRemindersCommand::class,
        SendDigestCommand::class,
        ExportCommand::class,
        ImportCommand::class,
    ];

    protected $listen = [
        EntrySaving::class => [PreserveClientProgress::class],
        UserRegistering::class => [VerifyCaptcha::class],
        UserRegistered::class => [SetUpRegisteredClient::class],
    ];

    protected $tags = [
        ClientPortal::class,
    ];

    protected $publishables = [
        __DIR__.'/../resources/dist' => '',
    ];

    public function register(): void
    {
        parent::register();

        if (! config('filesystems.disks.'.Portals::FILES_DISK)) {
            config(['filesystems.disks.'.Portals::FILES_DISK => [
                'driver' => 'local',
                'root' => storage_path('app/client-portal'),
                'visibility' => 'private',
                'throw' => false,
            ]]);
        }
    }

    public function bootAddon(): void
    {
        $this->loadJsonTranslationsFrom(__DIR__.'/../lang');

        Gate::define('view-client-portal', function ($user, Entry $portal): bool {
            return Portals::userCanView(User::fromUser($user), $portal);
        });
    }

    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('client-portal:send-reminders')->dailyAt('08:00');
        $schedule->command('client-portal:send-digest')->dailyAt('17:00');
    }
}

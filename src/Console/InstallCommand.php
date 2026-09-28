<?php

namespace Komalnakrani\ClientPortal\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Komalnakrani\ClientPortal\Portals;
use Statamic\Console\RunsInPlease;
use Statamic\Facades\Collection;
use Statamic\Facades\Role;

class InstallCommand extends Command
{
    use RunsInPlease;

    protected $signature = 'client-portal:install';

    protected $description = 'Create the portals collection, its blueprint and the client role';

    public function handle(): int
    {
        if (! Collection::find(Portals::COLLECTION)) {
            Collection::make(Portals::COLLECTION)
                ->title('Client Portals')
                ->revisionsEnabled(false)
                ->save();

            $this->components->info('Created the [portals] collection.');
        }

        $blueprint = resource_path('blueprints/collections/'.Portals::COLLECTION.'/portal.yaml');

        if (! File::exists($blueprint)) {
            File::ensureDirectoryExists(dirname($blueprint));
            File::copy(__DIR__.'/../../resources/stubs/portal.yaml', $blueprint);

            $this->components->info('Published the portal blueprint.');
        }

        if (! Role::find('client')) {
            Role::make('client')->title('Client')->save();

            $this->components->info('Created the [client] role.');
        }

        $this->call('vendor:publish', ['--tag' => 'client-portal', '--force' => true]);

        return self::SUCCESS;
    }
}

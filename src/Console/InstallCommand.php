<?php

namespace RadThemes\ClientPortal\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RadThemes\ClientPortal\Portals;
use Statamic\Console\RunsInPlease;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Role;
use Statamic\Facades\YAML;

class InstallCommand extends Command
{
    use RunsInPlease;

    protected $signature = 'client-portal:install {--no-example : Skip creating the example template portal}';

    protected $description = 'Set up the portals collection, private file storage, the client role and an example template';

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

        if (! AssetContainer::find(Portals::FILES_CONTAINER)) {
            AssetContainer::make(Portals::FILES_CONTAINER)
                ->title('Portal Files')
                ->disk(Portals::FILES_DISK)
                ->save();

            $this->components->info('Created the private [portal_files] asset container.');
        }

        if (! AssetContainer::find('assets') && config('filesystems.disks.assets')) {
            AssetContainer::make('assets')->title('Assets')->disk('assets')->save();
        }

        if (! Role::find('client')) {
            Role::make('client')->title('Client')->save();

            $this->components->info('Created the [client] role.');
        }

        if (! $this->option('no-example') && Portals::query()->count() === 0) {
            Entry::make()
                ->collection(Portals::COLLECTION)
                ->slug('website-project-template')
                ->published(true)
                ->data(YAML::file(__DIR__.'/../../resources/stubs/example-template.yaml')->parse())
                ->save();

            $this->components->info('Created an example portal template.');
        }

        $this->call('vendor:publish', ['--tag' => 'client-portal', '--force' => true]);

        return self::SUCCESS;
    }
}

<?php

namespace Komalnakrani\ClientPortal\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Komalnakrani\ClientPortal\Portals;
use Statamic\Console\RunsInPlease;
use Statamic\Facades\Entry;

class ImportCommand extends Command
{
    use RunsInPlease;

    protected $signature = 'client-portal:import {path : JSON file created by client-portal:export} {--slug= : Use a different slug}';

    protected $description = 'Import a portal or template from JSON (created unpublished, without clients)';

    public function handle(): int
    {
        $json = File::exists($path = $this->argument('path')) ? json_decode(File::get($path), true) : null;

        if (! is_array($json) || ! isset($json['slug'], $json['data']['title'])) {
            $this->components->error('That file is not a Client Portal export.');

            return self::FAILURE;
        }

        $slug = $this->option('slug') ?? $json['slug'];

        if (Entry::query()->where('collection', Portals::COLLECTION)->where('slug', $slug)->exists()) {
            $this->components->error("A portal with the slug [{$slug}] already exists. Use --slug to pick another.");

            return self::FAILURE;
        }

        Entry::make()
            ->collection(Portals::COLLECTION)
            ->slug($slug)
            ->published(false)
            ->data(array_merge($json['data'], ['clients' => []]))
            ->save();

        $this->components->info("Imported [{$slug}]. It is unpublished until you assign clients and publish it.");

        return self::SUCCESS;
    }
}

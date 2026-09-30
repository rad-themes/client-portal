<?php

namespace RadThemes\ClientPortal\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RadThemes\ClientPortal\Portals;
use Statamic\Console\RunsInPlease;

class ExportCommand extends Command
{
    use RunsInPlease;

    protected $signature = 'client-portal:export {slug : The portal or template to export} {path? : Where to write the JSON file}';

    protected $description = 'Export a portal or template (without clients, uploads or messages) to JSON';

    public function handle(): int
    {
        $portal = Portals::query()->where('slug', $this->argument('slug'))->first();

        if (! $portal) {
            $this->components->error('Portal not found.');

            return self::FAILURE;
        }

        $data = $portal->data()->except(['clients', 'messages', 'updated_by', 'updated_at'])->all();
        $data['phases'] = collect((array) ($data['phases'] ?? []))->map(function (array $phase) {
            $phase['modules'] = collect((array) ($phase['modules'] ?? []))
                ->map(fn (array $module) => array_diff_key($module, array_flip(['completed_at', 'completed_by'])))
                ->all();

            return $phase;
        })->all();

        $path = $this->argument('path') ?? base_path("portal-{$portal->slug()}.json");

        File::put($path, json_encode(['slug' => $portal->slug(), 'data' => $data], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $this->components->info("Exported to {$path}");

        return self::SUCCESS;
    }
}

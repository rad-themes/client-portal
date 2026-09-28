<?php

namespace Komalnakrani\ClientPortal\Tags;

use Komalnakrani\ClientPortal\Portals;
use Statamic\Facades\Entry;
use Statamic\Facades\User;
use Statamic\Support\Str;
use Statamic\Tags\Tags;

class ClientPortal extends Tags
{
    protected static $handle = 'client_portal';

    /**
     * The files of a file or upload module, for people who can see the portal.
     *
     * {{ client_portal:files portal="{page:id}" module="{id}" }} {{ name }} {{ url }} {{ preview_url }} {{ /client_portal:files }}
     *
     * @return array<int, array{name: string, size: string, url: string, preview_url: ?string}>
     */
    public function files(): array
    {
        $portal = Entry::find((string) $this->params->get('portal'));
        $user = User::current();
        $moduleId = (string) $this->params->get('module');

        if (! $portal || ! $user || ! Portals::userCanView($user, $portal) || ! $module = Portals::findModule($portal, $moduleId)['module'] ?? null) {
            return [];
        }

        $filesystem = Portals::container()->disk()->filesystem();

        $files = match ($module['type'] ?? null) {
            'file' => collect((array) ($module['files'] ?? []))->map(fn (string $path, int $index) => [$path, route('client-portal.download', [$portal->slug(), $moduleId, $index])]),
            'upload' => collect(Portals::uploads($portal, $moduleId))->map(fn (string $path, string $key) => [$path, route('client-portal.download-upload', [$portal->slug(), $moduleId, $key])]),
            default => collect(),
        };

        return $files
            ->filter(fn (array $file) => $filesystem->exists($file[0]))
            ->map(fn (array $file) => [
                'name' => basename($file[0]),
                'size' => Str::fileSizeForHumans($filesystem->size($file[0])),
                'url' => $file[1],
                'preview_url' => isset(Portals::PREVIEWABLE[strtolower(pathinfo($file[0], PATHINFO_EXTENSION))]) ? $file[1].'?preview=1' : null,
            ])
            ->values()
            ->all();
    }
}

<?php

namespace RadThemes\ClientPortal;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Statamic\Contracts\Assets\AssetContainer as AssetContainerContract;
use Statamic\Contracts\Auth\User;
use Statamic\Contracts\Entries\Entry;
use Statamic\Contracts\Query\Builder;
use Statamic\Facades\Addon;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\Site;
use Statamic\Facades\User as UserFacade;

class Portals
{
    public const COLLECTION = 'portals';

    public const FILES_CONTAINER = 'portal_files';

    public const FILES_DISK = 'client_portal';

    /**
     * Extensions that are shown in the browser instead of downloaded, with the type they're served as.
     */
    public const PREVIEWABLE = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'mp3' => 'audio/mpeg',
        'txt' => 'text/plain',
    ];

    public static function setting(string $key, mixed $default = null): mixed
    {
        return Addon::get('rad-themes/client-portal')->setting($key, $default) ?? $default;
    }

    /**
     * Portals in the default site. Localizations share their origin's clients and modules.
     */
    public static function query(): Builder
    {
        return EntryFacade::query()
            ->where('collection', self::COLLECTION)
            ->where('site', Site::default()->handle());
    }

    /**
     * Published, non-template portal with the given slug.
     */
    public static function findBySlug(string $slug): ?Entry
    {
        $portal = self::query()->where('slug', $slug)->where('published', true)->first();

        return $portal && ! $portal->get('is_template') ? $portal : null;
    }

    /**
     * Portals the user is assigned to as a client, or every portal for staff.
     *
     * @return Collection<int, Entry>
     */
    public static function forUser(User $user): Collection
    {
        return self::all()
            ->when(! self::isStaff($user), fn (Collection $portals) => $portals->filter(
                fn (Entry $portal) => in_array($user->id(), self::clientIds($portal), true)
            ))
            ->sortBy(fn (Entry $portal) => mb_strtolower((string) $portal->get('title')))
            ->values();
    }

    /**
     * Every published, non-template portal.
     *
     * @return Collection<int, Entry>
     */
    public static function all(): Collection
    {
        return self::query()
            ->where('published', true)
            ->get()
            ->reject(fn (Entry $portal) => $portal->get('is_template'))
            ->values();
    }

    public static function userCanView(User $user, Entry $portal): bool
    {
        return self::isStaff($user) || in_array($user->id(), self::clientIds($portal), true);
    }

    public static function isStaff(User $user): bool
    {
        return $user->isSuper() || $user->hasPermission('view '.self::COLLECTION.' entries');
    }

    /**
     * @return array<int, string>
     */
    public static function clientIds(Entry $portal): array
    {
        return array_values(array_filter((array) $portal->get('clients', [])));
    }

    /**
     * @return Collection<int, User>
     */
    public static function clients(Entry $portal): Collection
    {
        return collect(self::clientIds($portal))->map(fn (string $id) => UserFacade::find($id))->filter()->values();
    }

    /**
     * Find a module anywhere in the portal's phases by its row id.
     *
     * @return array{phase: array<string, mixed>, module: array<string, mixed>}|null
     */
    public static function findModule(Entry $portal, string $moduleId): ?array
    {
        foreach (self::modules($portal) as $found) {
            if (($found['module']['id'] ?? null) === $moduleId) {
                return $found;
            }
        }

        return null;
    }

    /**
     * @return array<int, array{phase: array<string, mixed>, module: array<string, mixed>}>
     */
    public static function modules(Entry $portal): array
    {
        $modules = [];

        foreach ((array) $portal->get('phases', []) as $phase) {
            foreach ((array) ($phase['modules'] ?? []) as $module) {
                $modules[] = ['phase' => $phase, 'module' => $module];
            }
        }

        return $modules;
    }

    /**
     * Apply a change to one module and save the portal.
     *
     * @param  callable(array<string, mixed>): array<string, mixed>  $change
     */
    public static function updateModule(Entry $portal, string $moduleId, callable $change): void
    {
        self::locked($portal, function (Entry $fresh) use ($moduleId, $change) {
            $phases = (array) $fresh->get('phases', []);

            foreach ($phases as $phaseIndex => $phase) {
                foreach ((array) ($phase['modules'] ?? []) as $moduleIndex => $module) {
                    if (($module['id'] ?? null) === $moduleId) {
                        $phases[$phaseIndex]['modules'][$moduleIndex] = $change($module);
                    }
                }
            }

            $fresh->set('phases', $phases)->save();
        });
    }

    /**
     * Mark a module complete on behalf of a client, and log it so an open editor can't undo it (see PreserveClientProgress).
     */
    public static function completeModule(Entry $portal, string $moduleId, User $user): void
    {
        $completion = ['completed_at' => now()->toIso8601String(), 'completed_by' => $user->id()];

        self::updateModule($portal, $moduleId, fn (array $module) => array_merge($module, ['status' => 'complete'], $completion));

        $completions = self::completions($portal);
        $completions[$moduleId] = $completion;
        self::container()->disk()->filesystem()->put(self::completionsPath($portal), json_encode($completions));
    }

    /**
     * Client completions per module id.
     *
     * @return array<string, array{completed_at: string, completed_by: string}>
     */
    public static function completions(Entry $portal): array
    {
        $filesystem = self::container()->disk()->filesystem();
        $path = self::completionsPath($portal);

        return $filesystem->exists($path) ? (array) json_decode($filesystem->get($path), true) : [];
    }

    private static function completionsPath(Entry $portal): string
    {
        return ".completions/{$portal->id()}.json";
    }

    public static function addMessage(Entry $portal, User $user, string $body): void
    {
        self::locked($portal, function (Entry $fresh) use ($user, $body) {
            $fresh->set('messages', array_merge((array) $fresh->get('messages', []), [[
                'id' => (string) Str::ulid(),
                'user' => $user->id(),
                'body' => $body,
                'at' => now()->toIso8601String(),
            ]]))->save();
        });
    }

    /**
     * The portal's messages, oldest first, with their authors resolved.
     *
     * @return array<int, array{id: string, author: string, is_staff: bool, body: string, at: string}>
     */
    public static function messages(Entry $portal): array
    {
        return collect((array) $portal->get('messages', []))->map(function (array $message) {
            $author = UserFacade::find($message['user'] ?? '');

            return [
                'id' => $message['id'],
                'author' => $author ? ($author->name() ?: $author->email()) : __('Former user'),
                'is_staff' => $author ? self::isStaff($author) : false,
                'body' => $message['body'],
                'at' => $message['at'],
            ];
        })->all();
    }

    /**
     * @param  array<string, mixed>  $module
     */
    public static function status(array $module): string
    {
        return $module['status'] ?? 'active';
    }

    /**
     * Percentage of non-inactive modules that are complete.
     */
    public static function progress(Entry $portal): int
    {
        $statuses = collect(self::modules($portal))
            ->map(fn (array $found) => self::status($found['module']))
            ->reject(fn (string $status) => $status === 'inactive');

        if ($statuses->isEmpty()) {
            return 0;
        }

        return (int) round($statuses->filter(fn (string $status) => $status === 'complete')->count() / $statuses->count() * 100);
    }

    public static function container(): AssetContainerContract
    {
        return AssetContainer::find(self::FILES_CONTAINER) ?? abort(500, 'Run php please client-portal:install');
    }

    public static function uploadFolder(Entry $portal, string $moduleId): string
    {
        return "uploads/{$portal->id()}/{$moduleId}";
    }

    /**
     * Files a client uploaded to a module, keyed by their upload key, newest last.
     *
     * @return array<string, string>
     */
    public static function uploads(Entry $portal, string $moduleId): array
    {
        $folder = self::uploadFolder($portal, $moduleId);
        $uploads = [];

        foreach (self::container()->disk()->filesystem()->allFiles($folder) as $path) {
            $relative = substr($path, strlen($folder) + 1);

            if (substr_count($relative, '/') === 1 && ! str_contains($relative, '.meta')) {
                $uploads[strtok($relative, '/')] = $path;
            }
        }

        ksort($uploads);

        return $uploads;
    }

    /**
     * Copy a template's phases onto a portal, keeping the state of modules it already has.
     */
    public static function applyTemplate(Entry $template, Entry $portal): void
    {
        $existing = collect(self::modules($portal))->pluck('module')->keyBy('id');
        $stateKeys = ['status', 'completed_at', 'completed_by'];

        $phases = collect((array) $template->get('phases', []))->map(function (array $phase) use ($existing, $stateKeys) {
            $phase['modules'] = collect((array) ($phase['modules'] ?? []))->map(function (array $module) use ($existing, $stateKeys) {
                $current = $existing->get($module['id'] ?? null);

                return $current ? array_merge($module, array_intersect_key($current, array_flip($stateKeys))) : $module;
            })->all();

            return $phase;
        })->all();

        $portal->set('phases', $phases);

        if (! $portal->get('welcome') && $template->get('welcome')) {
            $portal->set('welcome', $template->get('welcome'));
        }

        $portal->save();
    }

    /**
     * Create a new portal for the given clients from a template.
     *
     * @param  array<int, string>  $clientIds
     */
    public static function createFromTemplate(Entry $template, string $title, array $clientIds): Entry
    {
        $data = $template->data()->except(['is_template', 'clients', 'messages'])->all();

        $slug = str($title)->slug()->toString() ?: 'portal';
        $unique = $slug;

        for ($i = 2; self::query()->where('slug', $unique)->count() > 0; $i++) {
            $unique = "{$slug}-{$i}";
        }

        return tap(EntryFacade::make()
            ->collection(self::COLLECTION)
            ->locale(Site::default()->handle())
            ->slug($unique)
            ->published(true)
            ->data(array_merge($data, ['title' => $title, 'clients' => $clientIds])))
            ->save();
    }

    /**
     * Run a read-modify-write on the latest saved copy of the portal, one request at a time.
     *
     * @param  callable(Entry): void  $callback
     */
    private static function locked(Entry $portal, callable $callback): void
    {
        Cache::lock('client-portal:'.$portal->id(), 10)->block(5, function () use ($portal, $callback) {
            $callback(EntryFacade::find($portal->id()) ?? $portal);
        });
    }
}

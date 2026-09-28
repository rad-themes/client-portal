<?php

namespace Komalnakrani\ClientPortal;

use Illuminate\Support\Collection;
use Statamic\Contracts\Auth\User;
use Statamic\Contracts\Entries\Entry;
use Statamic\Facades\Addon;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\User as UserFacade;

class Portals
{
    public const COLLECTION = 'portals';

    public const FILES_CONTAINER = 'portal_files';

    public const FILES_DISK = 'client_portal';

    public static function setting(string $key, mixed $default = null): mixed
    {
        return Addon::get('komalnakrani/client-portal')->setting($key, $default) ?? $default;
    }

    /**
     * Published, non-template portal with the given slug.
     */
    public static function findBySlug(string $slug): ?Entry
    {
        $portal = EntryFacade::query()
            ->where('collection', self::COLLECTION)
            ->where('slug', $slug)
            ->where('published', true)
            ->first();

        return $portal && ! $portal->get('is_template') ? $portal : null;
    }

    /**
     * Portals the user is assigned to as a client, or every portal for staff.
     *
     * @return Collection<int, Entry>
     */
    public static function forUser(User $user): Collection
    {
        $query = EntryFacade::query()
            ->where('collection', self::COLLECTION)
            ->where('published', true)
            ->orderBy('title');

        if (! self::isStaff($user)) {
            $query->whereJsonContains('clients', $user->id());
        }

        return $query->get()->reject(fn (Entry $portal) => $portal->get('is_template'))->values();
    }

    /**
     * Every published, non-template portal.
     *
     * @return Collection<int, Entry>
     */
    public static function all(): Collection
    {
        return EntryFacade::query()
            ->where('collection', self::COLLECTION)
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
        $phases = (array) $portal->get('phases', []);

        foreach ($phases as $phaseIndex => $phase) {
            foreach ((array) ($phase['modules'] ?? []) as $moduleIndex => $module) {
                if (($module['id'] ?? null) === $moduleId) {
                    $phases[$phaseIndex]['modules'][$moduleIndex] = $change($module);
                }
            }
        }

        $portal->set('phases', $phases)->save();
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

    /**
     * Copy a template's phases onto a portal, keeping the state of modules it already has.
     */
    public static function applyTemplate(Entry $template, Entry $portal): void
    {
        $existing = collect(self::modules($portal))->pluck('module')->keyBy('id');
        $stateKeys = ['status', 'completed_at', 'completed_by', 'uploads'];

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
        $data = $template->data()->except(['is_template', 'clients'])->all();

        $slug = str($title)->slug()->toString();
        $unique = $slug;

        for ($i = 2; EntryFacade::query()->where('collection', self::COLLECTION)->where('slug', $unique)->exists(); $i++) {
            $unique = "{$slug}-{$i}";
        }

        return tap(EntryFacade::make()
            ->collection(self::COLLECTION)
            ->slug($unique)
            ->published(true)
            ->data(array_merge($data, ['title' => $title, 'clients' => $clientIds])))
            ->save();
    }
}

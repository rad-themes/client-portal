<?php

namespace Komalnakrani\ClientPortal;

use Illuminate\Support\Collection;
use Statamic\Contracts\Auth\User;
use Statamic\Contracts\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;

class Portals
{
    public const COLLECTION = 'portals';

    public static function findBySlug(string $slug): ?Entry
    {
        return EntryFacade::query()
            ->where('collection', self::COLLECTION)
            ->where('slug', $slug)
            ->where('published', true)
            ->first();
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

        return $query->get();
    }

    public static function userCanView(User $user, Entry $portal): bool
    {
        return self::isStaff($user)
            || in_array($user->id(), (array) $portal->get('clients', []), true);
    }

    /**
     * Find a module anywhere in the portal's phases by its row id.
     *
     * @return array{phase: array<string, mixed>, module: array<string, mixed>}|null
     */
    public static function findModule(Entry $portal, string $moduleId): ?array
    {
        foreach ((array) $portal->get('phases', []) as $phase) {
            foreach ((array) ($phase['modules'] ?? []) as $module) {
                if (($module['id'] ?? null) === $moduleId) {
                    return ['phase' => $phase, 'module' => $module];
                }
            }
        }

        return null;
    }

    private static function isStaff(User $user): bool
    {
        return $user->isSuper() || $user->hasPermission('view '.self::COLLECTION.' entries');
    }
}

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

    public static function findByToken(string $token): ?Entry
    {
        return EntryFacade::query()
            ->where('collection', self::COLLECTION)
            ->where('access_token', $token)
            ->where('published', true)
            ->first();
    }

    /**
     * Portals the user is assigned to as a client, or every portal for staff.
     *
     * @return Collection<int, Entry>
     */
    public static function forUser(?User $user): Collection
    {
        if (! $user) {
            return collect();
        }

        $query = EntryFacade::query()
            ->where('collection', self::COLLECTION)
            ->where('published', true)
            ->orderBy('title');

        if (! self::isStaff($user)) {
            $query->whereJsonContains('clients', $user->id());
        }

        return $query->get();
    }

    /**
     * Retrieve all portals for CP management.
     *
     * @return Collection<int, Entry>
     */
    public static function allPortals(): Collection
    {
        return EntryFacade::query()
            ->where('collection', self::COLLECTION)
            ->orderBy('title')
            ->get();
    }

    public static function userCanView(?User $user, Entry $portal, ?string $inputPassword = null, ?string $inputToken = null): bool
    {
        if ($user && self::isStaff($user)) {
            return true;
        }

        $accessType = $portal->get('access_type', 'user_login');

        if ($accessType === 'public') {
            return true;
        }

        if ($accessType === 'token') {
            $token = $portal->get('access_token');

            return $token && ($inputToken === $token || request('token') === $token);
        }

        if ($accessType === 'password') {
            $expected = $portal->get('access_password');
            $sessionKey = 'client_portal_pass_'.$portal->id();

            if (! $expected) {
                return true;
            }

            if ($inputPassword && $inputPassword === $expected) {
                session([$sessionKey => true]);

                return true;
            }

            return session($sessionKey) === true;
        }

        // Default: user_login
        if (! $user) {
            return false;
        }

        $clients = (array) $portal->get('clients', []);

        return in_array($user->id(), $clients, true);
    }

    /**
     * Find a module anywhere in the portal's phases by its row id.
     *
     * @return array{phase_index: int, module_index: int, phase: array<string, mixed>, module: array<string, mixed>}|null
     */
    public static function findModule(Entry $portal, string $moduleId): ?array
    {
        foreach ((array) $portal->get('phases', []) as $phaseIndex => $phase) {
            foreach ((array) ($phase['modules'] ?? []) as $moduleIndex => $module) {
                if (($module['id'] ?? null) === $moduleId) {
                    return [
                        'phase_index' => $phaseIndex,
                        'module_index' => $moduleIndex,
                        'phase' => $phase,
                        'module' => $module,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Update module status in a portal.
     */
    public static function updateModuleStatus(Entry $portal, string $moduleId, string $newStatus): bool
    {
        $phases = (array) $portal->get('phases', []);

        foreach ($phases as $phaseIndex => $phase) {
            $modules = (array) ($phase['modules'] ?? []);
            foreach ($modules as $moduleIndex => $module) {
                if (($module['id'] ?? null) === $moduleId) {
                    $phases[$phaseIndex]['modules'][$moduleIndex]['status'] = $newStatus;
                    if ($newStatus === 'complete') {
                        $phases[$phaseIndex]['modules'][$moduleIndex]['completed_at'] = now()->toIso8601String();
                    }
                    $portal->set('phases', $phases)->save();

                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Calculate completion percentage (0 to 100) for a portal.
     */
    public static function calculateProgress(Entry $portal): int
    {
        $total = 0;
        $completed = 0;

        foreach ((array) $portal->get('phases', []) as $phase) {
            foreach ((array) ($phase['modules'] ?? []) as $module) {
                $status = is_array($module['status'] ?? null) ? ($module['status']['value'] ?? 'active') : ($module['status'] ?? 'active');
                if ($status === 'inactive') {
                    continue;
                }

                $total++;
                if (in_array($status, ['complete', 'completed', 'approved'], true)) {
                    $completed++;
                }
            }
        }

        if ($total === 0) {
            return 0;
        }

        return (int) round(($completed / $total) * 100);
    }

    /**
     * Duplicate an existing portal entry.
     */
    public static function duplicatePortal(Entry $portal, string $newTitle, string $newSlug): Entry
    {
        $data = $portal->data()->toArray();
        $data['title'] = $newTitle;
        $data['slug'] = $newSlug;
        if (! empty($data['access_token'])) {
            $data['access_token'] = bin2hex(random_bytes(16));
        }

        $newPortal = EntryFacade::make()
            ->collection(self::COLLECTION)
            ->slug($newSlug)
            ->data($data);

        $newPortal->save();

        return $newPortal;
    }

    public static function isStaff(User $user): bool
    {
        return $user->isSuper() || $user->hasPermission('view '.self::COLLECTION.' entries');
    }
}

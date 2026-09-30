<?php

namespace RadThemes\ClientPortal;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use RadThemes\ClientPortal\Notifications\ActivityDigest;
use RadThemes\ClientPortal\Notifications\ClientActivity;
use Statamic\Contracts\Auth\User;
use Statamic\Contracts\Entries\Entry;
use Statamic\Facades\User as UserFacade;

/**
 * Tells admins what clients do in their portals, instantly or as a daily digest.
 */
class Activity
{
    public static function record(Entry $portal, User $user, string $description): void
    {
        $mode = Portals::setting('admin_notifications', 'instant');

        if ($mode === 'off' || $portal->get('mute_notifications') || Portals::isStaff($user)) {
            return;
        }

        $item = [
            'emails' => array_values(array_filter((array) $portal->get('notification_emails', []))),
            'portal' => $portal->get('title'),
            'url' => route('client-portal.show', $portal->slug()),
            'user' => $user->name() ?: $user->email(),
            'description' => $description,
            'at' => now()->toIso8601String(),
        ];

        if ($mode === 'digest') {
            File::ensureDirectoryExists(dirname(self::digestPath()));
            File::append(self::digestPath(), json_encode($item).PHP_EOL, lock: true);

            return;
        }

        self::notifyAdmins(new ClientActivity($item), $item['emails']);
    }

    /**
     * Send and clear the queued digest. Returns the number of items sent.
     */
    public static function sendDigest(): int
    {
        if (! File::exists(self::digestPath())) {
            return 0;
        }

        $items = collect(explode(PHP_EOL, trim(File::get(self::digestPath()))))
            ->filter()
            ->map(fn (string $line) => json_decode($line, true))
            ->all();

        File::delete(self::digestPath());

        // Portals with their own recipients get their own digest; everything else goes to the default recipients.
        collect($items)
            ->groupBy(fn (array $item) => implode(',', $item['emails'] ?? []))
            ->each(fn ($group, string $emails) => self::notifyAdmins(new ActivityDigest($group->all()), array_filter(explode(',', $emails))));

        return count($items);
    }

    public static function digestPath(): string
    {
        return storage_path('client-portal/activity-digest.jsonl');
    }

    /**
     * @param  array<int, string>  $emails  Recipients for this portal, or empty for the default recipients
     */
    private static function notifyAdmins(object $notification, array $emails = []): void
    {
        $emails = $emails ?: array_filter((array) Portals::setting('admin_emails', []));

        if (! $emails) {
            $emails = UserFacade::all()->filter->isSuper()->map->email()->all();
        }

        if ($emails) {
            Notification::route('mail', array_values($emails))->notify($notification);
        }
    }
}

<?php

namespace Komalnakrani\ClientPortal;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Komalnakrani\ClientPortal\Notifications\ActivityDigest;
use Komalnakrani\ClientPortal\Notifications\ClientActivity;
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

        if ($mode === 'off' || Portals::isStaff($user)) {
            return;
        }

        $item = [
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

        self::notifyAdmins(new ClientActivity($item));
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

        if ($items) {
            self::notifyAdmins(new ActivityDigest($items));
        }

        return count($items);
    }

    public static function digestPath(): string
    {
        return storage_path('client-portal/activity-digest.jsonl');
    }

    private static function notifyAdmins(object $notification): void
    {
        $emails = array_filter((array) Portals::setting('admin_emails', []));

        if (! $emails) {
            $emails = UserFacade::all()->filter->isSuper()->map->email()->all();
        }

        if ($emails) {
            Notification::route('mail', array_values($emails))->notify($notification);
        }
    }
}

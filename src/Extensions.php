<?php

namespace RadThemes\ClientPortal;

use Closure;
use Statamic\Contracts\Auth\User;

/**
 * Lets other addons add pages to the portal, e.g. a CRM's invoices.
 *
 *     Extensions::page('billing', 'Billing', fn (User $user) => view('my-addon::billing', [...])->render(), fn (User $user) => ...);
 *
 * Pages live at /portal/-/{slug}, need a logged-in user, render inside the portal layout
 * and are linked from the portal header for users they're visible to.
 */
class Extensions
{
    /**
     * @var array<string, array{label: string, render: Closure, visible: ?Closure}>
     */
    private static array $pages = [];

    /**
     * @param  Closure(User): string  $render  returns the page's HTML (escape any user data yourself)
     * @param  Closure(User): bool|null  $visible  whether the user sees the page (default: everyone logged in)
     */
    public static function page(string $slug, string $label, Closure $render, ?Closure $visible = null): void
    {
        self::$pages[$slug] = ['label' => $label, 'render' => $render, 'visible' => $visible];
    }

    /**
     * @return array{label: string, render: Closure, visible: ?Closure}|null
     */
    public static function find(string $slug, ?User $user): ?array
    {
        $page = self::$pages[$slug] ?? null;

        return $page && $user && (! $page['visible'] || ($page['visible'])($user)) ? $page : null;
    }

    /**
     * Header links for the user.
     *
     * @return array<int, array{slug: string, label: string, url: string}>
     */
    public static function navFor(?User $user): array
    {
        return collect(self::$pages)
            ->filter(fn ($page, $slug) => self::find($slug, $user))
            ->map(fn ($page, $slug) => ['slug' => $slug, 'label' => $page['label'], 'url' => route('client-portal.extension', $slug)])
            ->values()
            ->all();
    }

    public static function forget(string $slug): void
    {
        unset(self::$pages[$slug]);
    }
}

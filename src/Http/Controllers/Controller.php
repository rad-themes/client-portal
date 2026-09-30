<?php

namespace RadThemes\ClientPortal\Http\Controllers;

use RadThemes\ClientPortal\Portals;
use Statamic\Facades\Asset;
use Statamic\View\View;

abstract class Controller
{
    /**
     * @param  array<string, mixed>  $data
     */
    protected function view(string $template, array $data = []): View
    {
        return View::make("client-portal::{$template}", array_merge(['portal_brand' => $this->branding()], $data))
            ->layout('client-portal::layout');
    }

    /**
     * @return array{name: string, logo: ?string, color: ?string, custom_css: ?string, login_heading: ?string, login_intro: ?string, allow_registration: bool, captcha: ?string, captcha_site_key: ?string}
     */
    private function branding(): array
    {
        $logo = collect(Portals::setting('logo'))->first();

        return [
            'name' => Portals::setting('portal_name') ?: config('app.name'),
            'logo' => $logo ? Asset::find(str_contains($logo, '::') ? $logo : "assets::{$logo}")?->url() : null,
            'color' => $this->safeColor(Portals::setting('brand_color')),
            'custom_css' => Portals::setting('custom_css'),
            'login_heading' => Portals::setting('login_heading'),
            'login_intro' => Portals::setting('login_intro'),
            'allow_registration' => (bool) Portals::setting('allow_registration'),
            'captcha' => Portals::setting('captcha_site_key') && Portals::setting('captcha_secret_key') ? Portals::setting('captcha_provider') : null,
            'captcha_site_key' => Portals::setting('captcha_site_key'),
        ];
    }

    private function safeColor(?string $color): ?string
    {
        return $color && preg_match('/^#[0-9a-f]{3,8}$/i', $color) ? $color : null;
    }
}

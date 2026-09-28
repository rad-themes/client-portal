<?php

namespace Komalnakrani\ClientPortal\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Komalnakrani\ClientPortal\Portals;
use Statamic\Contracts\Entries\Entry;
use Statamic\Facades\User;
use Statamic\View\View;

class PortalController
{
    public function login(): View|RedirectResponse
    {
        if (User::current()) {
            return redirect()->route('client-portal.index');
        }

        return $this->view('login', ['title' => __('Log in')]);
    }

    public function index(): View|RedirectResponse
    {
        $portals = Portals::forUser(User::current());

        if ($portals->count() === 1) {
            return redirect()->route('client-portal.show', $portals->first()->slug());
        }

        return $this->view('index', ['title' => __('Your portals'), 'portals' => $portals]);
    }

    public function show(string $portal): View
    {
        $entry = $this->authorizedPortal($portal);

        return $this->view('show')->cascadeContent($entry);
    }

    public function page(string $portal, string $module): View
    {
        $entry = $this->authorizedPortal($portal);
        $found = Portals::findModule($entry, $module);

        abort_unless(
            $found
            && $found['module']['type'] === 'content'
            && ($found['module']['status'] ?? 'active') !== 'inactive',
            404
        );

        return $this->view('page', ['module_id' => $module])->cascadeContent($entry);
    }

    private function authorizedPortal(string $slug): Entry
    {
        $entry = Portals::findBySlug($slug) ?? abort(404);

        Gate::authorize('view-client-portal', $entry);

        return $entry;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function view(string $template, array $data = []): View
    {
        return View::make("client-portal::{$template}", $data)->layout('client-portal::layout');
    }
}

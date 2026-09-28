<?php

namespace Komalnakrani\ClientPortal\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function show(Request $request, string $portal): View|RedirectResponse
    {
        $entry = Portals::findBySlug($portal) ?? abort(404);

        if (! Portals::userCanView(User::current(), $entry, $request->input('password'), $request->query('token'))) {
            if ($entry->get('access_type') === 'password') {
                return $this->view('password', [
                    'portal' => $entry,
                    'title' => $entry->get('title'),
                    'error' => $request->isMethod('post') ? __('Incorrect password. Please try again.') : null,
                ]);
            }

            if ($entry->get('access_type') === 'token') {
                abort(403, 'Access denied. Valid access token required.');
            }

            if (! User::current()) {
                return redirect()->route('client-portal.login');
            }

            abort(403, 'Access denied.');
        }

        $progress = Portals::calculateProgress($entry);

        return $this->view('show', ['progress' => $progress])->cascadeContent($entry);
    }

    public function verifyPassword(Request $request, string $portal): View|RedirectResponse
    {
        $entry = Portals::findBySlug($portal) ?? abort(404);
        $password = $request->input('password');

        if (Portals::userCanView(User::current(), $entry, $password)) {
            return redirect()->route('client-portal.show', $entry->slug());
        }

        return $this->view('password', [
            'portal' => $entry,
            'title' => $entry->get('title'),
            'error' => __('Incorrect password. Please try again.'),
        ]);
    }

    public function page(string $portal, string $module): View
    {
        $entry = $this->authorizedPortal($portal);
        $found = Portals::findModule($entry, $module);

        $status = is_array($found['module']['status'] ?? null) ? ($found['module']['status']['value'] ?? 'active') : ($found['module']['status'] ?? 'active');

        abort_unless($found && ($found['module']['type'] ?? '') === 'content' && $status !== 'inactive', 404);

        return $this->view('page', ['module_id' => $module])->cascadeContent($entry);
    }

    public function toggleStatus(Request $request, string $portal, string $module): RedirectResponse
    {
        $entry = $this->authorizedPortal($portal);
        $found = Portals::findModule($entry, $module);

        abort_unless($found, 404);

        $newStatus = $request->input('status', 'complete');
        Portals::updateModuleStatus($entry, $module, $newStatus);

        return redirect()->route('client-portal.show', $entry->slug())
            ->with('success', __('Module status updated successfully.'));
    }

    private function authorizedPortal(string $slug): Entry
    {
        $entry = Portals::findBySlug($slug) ?? abort(404);

        if (! Portals::userCanView(User::current(), $entry)) {
            if (! User::current()) {
                abort(401);
            }
            abort(403);
        }

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

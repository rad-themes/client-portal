<?php

namespace Komalnakrani\ClientPortal\Http\Controllers\CP;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Komalnakrani\ClientPortal\Portals;
use Komalnakrani\ClientPortal\Templates;
use Statamic\Contracts\Entries\Entry;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\User;
use Statamic\Http\Controllers\CP\CpController;

class PortalController extends CpController
{
    public function index()
    {
        $portals = Portals::allPortals()->map(function (Entry $portal) {
            return [
                'id' => $portal->id(),
                'title' => $portal->get('title'),
                'slug' => $portal->slug(),
                'project_status' => $portal->get('project_status', 'Active'),
                'access_type' => $portal->get('access_type', 'user_login'),
                'clients' => (array) $portal->get('clients', []),
                'progress' => Portals::calculateProgress($portal),
                'cp_edit_url' => $portal->editUrl(),
                'public_url' => route('client-portal.show', $portal->slug()),
            ];
        });

        return view('client-portal::cp.portals.index', [
            'portals' => $portals,
            'collection' => Collection::find(Portals::COLLECTION),
        ]);
    }

    public function create()
    {
        $templates = Templates::defaults();
        $users = User::all();

        return view('client-portal::cp.portals.create', [
            'templates' => $templates,
            'users' => $users,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255',
            'template' => 'nullable|string',
            'clients' => 'nullable|array',
            'access_type' => 'required|string',
            'access_password' => 'nullable|string',
        ]);

        $data = [
            'title' => $validated['title'],
            'project_status' => 'In progress',
            'clients' => $validated['clients'] ?? [],
            'access_type' => $validated['access_type'],
            'access_password' => $validated['access_password'] ?? null,
            'access_token' => bin2hex(random_bytes(16)),
            'phases' => [],
        ];

        if (! empty($validated['template']) && isset(Templates::defaults()[$validated['template']])) {
            $templateData = Templates::defaults()[$validated['template']];
            $data['project_status'] = $templateData['project_status'] ?? 'In progress';
            $data['welcome'] = $templateData['welcome'] ?? null;
            $data['phases'] = $templateData['phases'] ?? [];
        }

        $portal = EntryFacade::make()
            ->collection(Portals::COLLECTION)
            ->slug($validated['slug'])
            ->data($data);

        $portal->save();

        return redirect()->to($portal->editUrl())
            ->with('success', __('Client Portal created! Customize phases and deliverables below.'));
    }

    public function duplicate(string $portal): RedirectResponse
    {
        $entry = EntryFacade::find($portal) ?? abort(404);

        $newSlug = $entry->slug().'-copy-'.time();
        $newTitle = $entry->get('title').' (Copy)';

        $duplicated = Portals::duplicatePortal($entry, $newTitle, $newSlug);

        return redirect()->route('statamic.cp.client-portal.index')
            ->with('success', __("Portal duplicated successfully as ':title'.", ['title' => $newTitle]));
    }

    public function destroy(string $portal): RedirectResponse
    {
        $entry = EntryFacade::find($portal) ?? abort(404);
        $title = $entry->get('title');
        $entry->delete();

        return redirect()->route('statamic.cp.client-portal.index')
            ->with('success', __("Portal ':title' deleted.", ['title' => $title]));
    }
}

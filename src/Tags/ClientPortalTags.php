<?php

namespace Komalnakrani\ClientPortal\Tags;

use Komalnakrani\ClientPortal\Portals;
use Statamic\Facades\User;
use Statamic\Tags\Tags;

class ClientPortalTags extends Tags
{
    protected static $handle = 'client_portal';

    /**
     * {{ client_portal }}
     * Yields list of portals for the current user or specific portal if slug parameter provided.
     */
    public function index()
    {
        $slug = $this->params->get(['slug', 'portal']);

        if ($slug) {
            return $this->portal();
        }

        return $this->portals();
    }

    /**
     * {{ client_portal:portals }}
     */
    public function portals()
    {
        $user = User::current();
        $portals = Portals::forUser($user);

        return $this->parseLoop($portals->map(function ($portal) {
            return array_merge($portal->data()->toArray(), [
                'id' => $portal->id(),
                'title' => $portal->get('title'),
                'slug' => $portal->slug(),
                'url' => route('client-portal.show', $portal->slug()),
                'progress' => Portals::calculateProgress($portal),
            ]);
        })->all());
    }

    /**
     * {{ client_portal:portal slug="acme" }}
     */
    public function portal()
    {
        $slug = $this->params->get(['slug', 'portal']);

        if (! $slug) {
            return null;
        }

        $entry = Portals::findBySlug($slug);

        if (! $entry) {
            return null;
        }

        $user = User::current();

        if (! Portals::userCanView($user, $entry)) {
            return null;
        }

        $data = array_merge($entry->data()->toArray(), [
            'id' => $entry->id(),
            'slug' => $entry->slug(),
            'url' => route('client-portal.show', $entry->slug()),
            'progress' => Portals::calculateProgress($entry),
        ]);

        return $this->parse($data);
    }

    /**
     * {{ client_portal:progress slug="acme" }}
     */
    public function progress()
    {
        $slug = $this->params->get(['slug', 'portal']);

        if (! $slug) {
            return 0;
        }

        $entry = Portals::findBySlug($slug);

        if (! $entry) {
            return 0;
        }

        return Portals::calculateProgress($entry);
    }
}

<?php

namespace Komalnakrani\ClientPortal\Listeners;

use Komalnakrani\ClientPortal\Portals;
use Statamic\Events\UserRegistered;
use Statamic\Facades\Entry;

/**
 * Gives clients who sign up on the portal registration page the client role,
 * and their own portal copied from the registration template.
 */
class SetUpRegisteredClient
{
    public function handle(UserRegistered $event): void
    {
        if (! request()->boolean('_client_portal') || ! Portals::setting('allow_registration')) {
            return;
        }

        $event->user->assignRole('client')->save();

        $templateId = collect(Portals::setting('registration_template'))->first();
        $template = $templateId ? Entry::find($templateId) : null;

        if ($template && $template->get('is_template')) {
            Portals::createFromTemplate($template, $event->user->name() ?: $event->user->email(), [$event->user->id()]);
        }
    }
}

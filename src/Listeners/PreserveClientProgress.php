<?php

namespace Komalnakrani\ClientPortal\Listeners;

use Komalnakrani\ClientPortal\Portals;
use Statamic\Events\EntrySaving;

/**
 * Stops a Control Panel save from undoing a completion a client made while the portal was open in the editor.
 *
 * The editor round-trips each module's completed_at. If the completion log has a newer completion than the
 * one being saved, the client completed the module after the editor loaded, so their completion wins.
 * An editor that loaded after the completion saves the same completed_at, so it can still reopen the module.
 */
class PreserveClientProgress
{
    public function handle(EntrySaving $event): void
    {
        $entry = $event->entry;

        if ($entry->collectionHandle() !== Portals::COLLECTION || ! $entry->id() || ! $completions = Portals::completions($entry)) {
            return;
        }

        $phases = collect((array) $entry->get('phases', []))->map(function (array $phase) use ($completions) {
            $phase['modules'] = collect((array) ($phase['modules'] ?? []))->map(function (array $module) use ($completions) {
                $logged = $completions[$module['id'] ?? ''] ?? null;

                if ($logged && ($module['completed_at'] ?? '') < $logged['completed_at']) {
                    $module = array_merge($module, ['status' => 'complete'], $logged);
                }

                return $module;
            })->all();

            return $phase;
        })->all();

        $entry->set('phases', $phases);
    }
}

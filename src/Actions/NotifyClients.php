<?php

namespace Komalnakrani\ClientPortal\Actions;

use Illuminate\Support\Facades\Notification;
use Komalnakrani\ClientPortal\Notifications\PortalUpdated;
use Komalnakrani\ClientPortal\Portals;
use Statamic\Actions\Action;
use Statamic\Contracts\Entries\Entry;

class NotifyClients extends Action
{
    public $icon = 'mail-send';

    public static function title()
    {
        return __('Notify clients');
    }

    public function visibleTo($item)
    {
        return $item instanceof Entry
            && $item->collectionHandle() === Portals::COLLECTION
            && ! $item->get('is_template');
    }

    public function authorize($user, $item)
    {
        return $user->can('edit', $item);
    }

    public function buttonText()
    {
        return __('Send email|Send emails for :count portals');
    }

    public function run($items, $values)
    {
        $sent = 0;

        foreach ($items as $portal) {
            $clients = Portals::clients($portal);
            Notification::send($clients, new PortalUpdated($portal, $values['note'] ?? null));
            $sent += $clients->count();
        }

        return trans_choice('Emailed :count client|Emailed :count clients', $sent);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function fieldItems()
    {
        return [
            'note' => [
                'type' => 'textarea',
                'display' => __('Message (optional)'),
                'instructions' => __('Added to the email, e.g. what changed.'),
            ],
        ];
    }
}

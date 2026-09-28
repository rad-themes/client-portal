<?php

namespace Komalnakrani\ClientPortal\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Statamic\Contracts\Entries\Entry;

class PortalUpdated extends Notification
{
    public function __construct(public Entry $portal, public ?string $note = null) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $title = $this->portal->get('title');

        return (new MailMessage)
            ->subject(__('Your portal has been updated: :title', ['title' => $title]))
            ->line(__('There are new updates in your portal “:title”.', ['title' => $title]))
            ->when($this->note, fn (MailMessage $message) => $message->line($this->note))
            ->action(__('View portal'), route('client-portal.show', $this->portal->slug()));
    }
}

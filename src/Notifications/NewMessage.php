<?php

namespace RadThemes\ClientPortal\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use Statamic\Contracts\Entries\Entry;

class NewMessage extends Notification
{
    public function __construct(public Entry $portal, public string $author, public string $body) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('New message in :portal', ['portal' => $this->portal->get('title')]))
            ->line(__(':author wrote:', ['author' => $this->author]))
            ->line(Str::limit($this->body, 500))
            ->action(__('Reply in your portal'), route('client-portal.show', $this->portal->slug()).'#messages');
    }
}

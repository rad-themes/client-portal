<?php

namespace Komalnakrani\ClientPortal\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClientActivity extends Notification
{
    /**
     * @param  array{portal: string, url: string, user: string, description: string, at: string}  $item
     */
    public function __construct(public array $item) {}

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
            ->subject(__(':user :description', $this->item).' · '.$this->item['portal'])
            ->line(__(':user :description in :portal.', $this->item))
            ->action(__('Open portal'), $this->item['url']);
    }
}

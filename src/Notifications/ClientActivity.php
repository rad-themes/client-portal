<?php

namespace RadThemes\ClientPortal\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Arr;

class ClientActivity extends Notification
{
    /**
     * @param  array{emails: array<int, string>, portal: string, url: string, user: string, description: string, at: string}  $item
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
        $replace = Arr::only($this->item, ['user', 'description', 'portal']);

        return (new MailMessage)
            ->subject(__(':user :description', $replace).' · '.$this->item['portal'])
            ->line(__(':user :description in :portal.', $replace))
            ->action(__('Open portal'), $this->item['url']);
    }
}

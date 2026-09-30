<?php

namespace RadThemes\ClientPortal\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ActivityDigest extends Notification
{
    /**
     * @param  array<int, array{emails?: array<int, string>, portal: string, url: string, user: string, description: string, at: string}>  $items
     */
    public function __construct(public array $items) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject(trans_choice(':count client update in your portals|:count client updates in your portals', count($this->items)));

        foreach (collect($this->items)->groupBy('portal') as $portal => $items) {
            $message->line("**{$portal}**");

            foreach ($items as $item) {
                $message->line('• '.__(':user :description', ['user' => $item['user'], 'description' => $item['description']]));
            }
        }

        return $message;
    }
}

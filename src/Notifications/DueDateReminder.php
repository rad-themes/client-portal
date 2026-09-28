<?php

namespace Komalnakrani\ClientPortal\Notifications;

use Carbon\CarbonInterface;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Statamic\Contracts\Entries\Entry;

class DueDateReminder extends Notification
{
    public function __construct(public Entry $portal, public string $moduleTitle, public CarbonInterface $dueDate) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $replace = [
            'module' => $this->moduleTitle,
            'portal' => $this->portal->get('title'),
            'date' => $this->dueDate->isoFormat('LL'),
        ];

        return (new MailMessage)
            ->subject(__('Reminder: “:module” is due :date', $replace))
            ->line(__('“:module” in your portal “:portal” is due on :date.', $replace))
            ->action(__('Open portal'), route('client-portal.show', $this->portal->slug()));
    }
}

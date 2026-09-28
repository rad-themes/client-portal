<?php

namespace Komalnakrani\ClientPortal\Tests\Feature;

use Illuminate\Support\Facades\Notification;
use Komalnakrani\ClientPortal\Actions\NotifyClients;
use Komalnakrani\ClientPortal\Activity;
use Komalnakrani\ClientPortal\Notifications\ActivityDigest;
use Komalnakrani\ClientPortal\Notifications\DueDateReminder;
use Komalnakrani\ClientPortal\Notifications\PortalUpdated;
use Komalnakrani\ClientPortal\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class NotificationsTest extends TestCase
{
    #[Test]
    public function digest_mode_collects_activity_and_sends_it_once(): void
    {
        Notification::fake();
        $this->setSettings(['admin_notifications' => 'digest', 'admin_emails' => ['team@agency.test']]);
        $client = $this->makeUser('client@example.com');
        $portal = $this->makePortal('acme', [$client->id()]);

        Activity::record($portal, $client, 'did one thing');
        Activity::record($portal, $client, 'did another thing');

        Notification::assertNothingSent();

        $this->artisan('client-portal:send-digest')->assertSuccessful();

        Notification::assertSentOnDemandTimes(ActivityDigest::class, 1);
        Notification::assertSentOnDemand(ActivityDigest::class, fn (ActivityDigest $digest) => count($digest->items) === 2);

        $this->artisan('client-portal:send-digest')->assertSuccessful();
        Notification::assertSentOnDemandTimes(ActivityDigest::class, 1);
    }

    #[Test]
    public function admin_notifications_can_be_turned_off(): void
    {
        Notification::fake();
        $this->setSettings(['admin_notifications' => 'off']);
        $client = $this->makeUser('client@example.com');

        Activity::record($this->makePortal('acme', [$client->id()]), $client, 'did something');

        Notification::assertNothingSent();
    }

    #[Test]
    public function reminders_go_to_clients_of_active_modules_due_on_the_reminder_day(): void
    {
        Notification::fake();
        $this->setSettings(['reminder_days' => 2]);
        $client = $this->makeUser('client@example.com');
        $portal = $this->makePortal('acme', [$client->id()]);
        $this->changeModule($portal, 'link-1', ['due_date' => today()->addDays(2)->toDateString()]);
        $this->changeModule($portal, 'content-1', ['due_date' => today()->addDays(3)->toDateString()]);
        $this->changeModule($portal, 'file-1', ['due_date' => today()->addDays(2)->toDateString(), 'status' => 'complete']);

        $this->artisan('client-portal:send-reminders')->assertSuccessful();

        Notification::assertSentToTimes($client, DueDateReminder::class, 1);
        Notification::assertSentTo($client, DueDateReminder::class, fn (DueDateReminder $reminder) => $reminder->moduleTitle === 'Staging site');
    }

    #[Test]
    public function reminders_can_be_turned_off(): void
    {
        Notification::fake();
        $this->setSettings(['reminder_days' => 0]);
        $client = $this->makeUser('client@example.com');
        $this->changeModule($this->makePortal('acme', [$client->id()]), 'link-1', ['due_date' => today()->toDateString()]);

        $this->artisan('client-portal:send-reminders')->assertSuccessful();

        Notification::assertNothingSent();
    }

    #[Test]
    public function the_notify_clients_action_emails_every_client_of_the_portal(): void
    {
        Notification::fake();
        $first = $this->makeUser('first@example.com');
        $second = $this->makeUser('second@example.com');
        $portal = $this->makePortal('acme', [$first->id(), $second->id()]);

        (new NotifyClients)->run(collect([$portal]), ['note' => 'New designs are up.']);

        Notification::assertSentTo([$first, $second], PortalUpdated::class, fn (PortalUpdated $notification) => $notification->note === 'New designs are up.');
    }
}

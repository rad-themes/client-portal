<?php

namespace Komalnakrani\ClientPortal\Tests\Feature;

use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;
use Komalnakrani\ClientPortal\Notifications\ActivityDigest;
use Komalnakrani\ClientPortal\Notifications\ClientActivity;
use Komalnakrani\ClientPortal\Notifications\DueDateReminder;
use Komalnakrani\ClientPortal\Notifications\NewMessage;
use Komalnakrani\ClientPortal\Notifications\PortalUpdated;
use Komalnakrani\ClientPortal\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Other tests fake notifications, so these render each email for real.
 */
class EmailRenderingTest extends TestCase
{
    #[Test]
    public function every_email_renders(): void
    {
        $portal = $this->makePortal('acme', []);
        $item = [
            'emails' => ['pm@agency.test'],
            'portal' => 'Acme Website',
            'url' => 'https://example.com/portal/acme',
            'user' => 'Jane',
            'description' => 'uploaded 2 files to “Brand assets”',
            'at' => now()->toIso8601String(),
        ];

        $emails = [
            'Jane uploaded 2 files' => new ClientActivity($item),
            'Acme Website' => new ActivityDigest([$item, $item]),
            'Please review' => new PortalUpdated($portal, 'Please review the new designs.'),
            'due on' => new DueDateReminder($portal, 'Design sign-off', today()->addDays(2)),
            'Can we try a darker hero?' => new NewMessage($portal, 'Jane', 'Can we try a darker hero?'),
        ];

        foreach ($emails as $expected => $notification) {
            /** @var Notification $notification */
            $html = (string) $notification->toMail(new AnonymousNotifiable)->render();

            $this->assertStringContainsString($expected, html_entity_decode($html), $notification::class);
        }
    }
}

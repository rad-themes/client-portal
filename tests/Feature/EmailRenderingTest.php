<?php

namespace RadThemes\ClientPortal\Tests\Feature;

use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;
use PHPUnit\Framework\Attributes\Test;
use RadThemes\ClientPortal\Notifications\ActivityDigest;
use RadThemes\ClientPortal\Notifications\ClientActivity;
use RadThemes\ClientPortal\Notifications\DueDateReminder;
use RadThemes\ClientPortal\Notifications\NewMessage;
use RadThemes\ClientPortal\Notifications\PortalUpdated;
use RadThemes\ClientPortal\Tests\TestCase;

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

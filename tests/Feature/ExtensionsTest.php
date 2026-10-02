<?php

namespace RadThemes\ClientPortal\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use RadThemes\ClientPortal\Extensions;
use RadThemes\ClientPortal\Tests\TestCase;

class ExtensionsTest extends TestCase
{
    protected function tearDown(): void
    {
        Extensions::forget('billing');

        parent::tearDown();
    }

    #[Test]
    public function addons_can_add_pages_to_the_portal(): void
    {
        $client = $this->makeUser('client@example.com');
        $other = $this->makeUser('other@example.com');
        $this->makePortal('acme', [$client->id()]);

        Extensions::page('billing', 'Billing', fn ($user) => '<p>Invoices for '.e($user->email()).'</p>', fn ($user) => $user->email() === 'client@example.com');

        $this->get('/portal/-/billing')->assertRedirect();
        $this->actingAs($client)->get('/portal/-/billing')->assertOk()->assertSee('<p>Invoices for client@example.com</p>', false);
        $this->actingAs($client)->get('/portal/acme')->assertOk()->assertSee('/portal/-/billing', false);

        $this->actingAs($other)->get('/portal/-/billing')->assertNotFound();
        $this->actingAs($other)->get('/portal')->assertDontSee('/portal/-/billing', false);
        $this->actingAs($client)->get('/portal/-/nothing')->assertNotFound();
    }
}

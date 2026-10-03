<?php

namespace RadThemes\ClientPortal\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use RadThemes\ClientPortal\Portals;
use RadThemes\ClientPortal\Tests\TestCase;
use Statamic\Facades\User;

/**
 * Portal text that clients or visitors can influence must never render as HTML.
 */
class EscapingTest extends TestCase
{
    private const PAYLOAD = '<img src=x onerror=alert(1)>';

    #[Test]
    public function a_self_registered_name_is_escaped_when_staff_view_the_portal(): void
    {
        $template = $this->makePortal('tpl', [], ['is_template' => true, 'title' => 'Template']);
        $this->setSettings(['allow_registration' => true, 'registration_template' => [$template->id()]]);

        $this->post('/!/auth/register', [
            '_client_portal' => '1',
            'name' => self::PAYLOAD,
            'email' => 'attacker@example.com',
            'password' => 'secret-pass-123',
            'password_confirmation' => 'secret-pass-123',
        ]);

        $portal = Portals::forUser(User::findByEmail('attacker@example.com'))->first();
        $this->assertSame(self::PAYLOAD, $portal->get('title'), 'The registered name became the portal title');

        $this->makePortal('acme', []);
        $admin = $this->makeUser('admin@example.com', super: true);

        $this->actingAs($admin)->get('/portal')->assertOk()
            ->assertDontSee(self::PAYLOAD, false)
            ->assertSee('&lt;img src=x onerror=alert(1)&gt;', false);

        $this->actingAs($admin)->get('/portal/'.$portal->slug())->assertOk()->assertDontSee(self::PAYLOAD, false);
    }

    #[Test]
    public function every_plain_text_field_clients_see_is_escaped(): void
    {
        $client = $this->makeUser('client@example.com');
        $this->makePortal('acme', [$client->id()], [
            'title' => 'Title '.self::PAYLOAD,
            'project_status' => 'Status '.self::PAYLOAD,
            'phases' => [[
                'id' => 'phase-1',
                'title' => 'Phase '.self::PAYLOAD,
                'modules' => [
                    ['id' => 'link-1', 'type' => 'link', 'title' => 'Module '.self::PAYLOAD, 'description' => 'Description '.self::PAYLOAD, 'status' => 'active', 'url' => 'https://example.com', 'button_label' => 'Button '.self::PAYLOAD, 'client_can_complete' => true, 'complete_label' => 'Approve '.self::PAYLOAD],
                    ['id' => 'content-1', 'type' => 'content', 'title' => 'Page '.self::PAYLOAD, 'status' => 'active', 'body' => []],
                ],
            ]],
        ]);
        $this->makePortal('other', [$client->id()], ['title' => 'Other '.self::PAYLOAD]);

        foreach (['/portal', '/portal/acme', '/portal/acme/content-1'] as $url) {
            $this->actingAs($client)->get($url)->assertOk()->assertDontSee(self::PAYLOAD, false);
        }
    }

    #[Test]
    public function login_branding_text_is_escaped(): void
    {
        $this->setSettings(['login_heading' => 'Hi '.self::PAYLOAD, 'login_intro' => 'Intro '.self::PAYLOAD]);

        $this->get('/portal/login')->assertOk()->assertDontSee(self::PAYLOAD, false)->assertSee('Hi &lt;img', false);
    }
}

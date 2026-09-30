<?php

namespace RadThemes\ClientPortal\Tests\Feature;

use RadThemes\ClientPortal\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Testing\Concerns\FakesRoles;

class PortalAccessTest extends TestCase
{
    use FakesRoles;

    #[Test]
    public function guests_are_redirected_to_the_login_page(): void
    {
        $this->makePortal('acme', []);

        $this->get('/portal')->assertRedirect(route('client-portal.login'));
        $this->get('/portal/acme')->assertRedirect(route('client-portal.login'));
        $this->get('/portal/acme/file-1/files/0')->assertRedirect(route('client-portal.login'));
        $this->post('/portal/acme/link-1/complete')->assertRedirect(route('client-portal.login'));
    }

    #[Test]
    public function a_client_sees_their_portal_with_its_phases_modules_and_progress(): void
    {
        $client = $this->makeUser('client@example.com');
        $portal = $this->makePortal('acme', [$client->id()]);
        $this->changeModule($portal, 'content-1', ['status' => 'complete']);

        $this->actingAs($client)
            ->get('/portal/acme')
            ->assertOk()
            ->assertSee('Acme Website')
            ->assertSee('In progress')
            ->assertSee('Discovery')
            ->assertSee('https://staging.example.com')
            ->assertSee('/portal/acme/content-1')
            ->assertSee('aria-valuenow="25"', false);
    }

    #[Test]
    public function a_client_cannot_see_a_portal_they_are_not_assigned_to(): void
    {
        $this->makePortal('acme', [$this->makeUser('owner@example.com')->id()]);
        $stranger = $this->makeUser('stranger@example.com');

        $this->actingAs($stranger)->get('/portal/acme')->assertForbidden();
        $this->actingAs($stranger)->get('/portal/acme/content-1')->assertForbidden();
    }

    #[Test]
    public function unknown_unpublished_and_template_portals_are_not_found(): void
    {
        $client = $this->makeUser('client@example.com');
        $this->makePortal('draft', [$client->id()])->published(false)->save();
        $this->makePortal('template', [$client->id()], ['is_template' => true]);

        $this->actingAs($client)->get('/portal/missing')->assertNotFound();
        $this->actingAs($client)->get('/portal/draft')->assertNotFound();
        $this->actingAs($client)->get('/portal/template')->assertNotFound();
    }

    #[Test]
    public function a_client_with_one_portal_is_sent_straight_to_it(): void
    {
        $client = $this->makeUser('client@example.com');
        $this->makePortal('acme', [$client->id()]);
        $this->makePortal('other', [$this->makeUser('other@example.com')->id()]);

        $this->actingAs($client)->get('/portal')->assertRedirect(route('client-portal.show', 'acme'));
    }

    #[Test]
    public function a_client_with_several_portals_sees_only_theirs_listed(): void
    {
        $client = $this->makeUser('client@example.com');
        $this->makePortal('acme', [$client->id()], ['title' => 'Acme Website']);
        $this->makePortal('globex', [$client->id()], ['title' => 'Globex Rebrand']);
        $this->makePortal('initech', [$this->makeUser('other@example.com')->id()], ['title' => 'Initech App']);
        $this->makePortal('tpl', [$client->id()], ['title' => 'Secret Template', 'is_template' => true]);

        $this->actingAs($client)
            ->get('/portal')
            ->assertOk()
            ->assertSee('Acme Website')
            ->assertSee('Globex Rebrand')
            ->assertDontSee('Initech App')
            ->assertDontSee('Secret Template');
    }

    #[Test]
    public function super_users_can_see_every_portal(): void
    {
        $this->makePortal('acme', [$this->makeUser('client@example.com')->id()]);

        $this->actingAs($this->makeUser('admin@example.com', super: true))->get('/portal/acme')->assertOk();
    }

    #[Test]
    public function staff_with_permission_to_view_portal_entries_can_see_every_portal(): void
    {
        $this->setTestRoles(['account_manager' => ['view portals entries']]);
        $this->makePortal('acme', [$this->makeUser('client@example.com')->id()]);
        $manager = $this->makeUser('manager@example.com')->assignRole('account_manager');
        $manager->save();

        $this->actingAs($manager)->get('/portal/acme')->assertOk();
    }

    #[Test]
    public function a_content_module_renders_as_its_own_page(): void
    {
        $client = $this->makeUser('client@example.com');
        $this->makePortal('acme', [$client->id()]);

        $this->actingAs($client)
            ->get('/portal/acme/content-1')
            ->assertOk()
            ->assertSee('Project brief')
            ->assertSee('Our goals for the new site.');
    }

    #[Test]
    public function only_active_content_modules_have_pages(): void
    {
        $client = $this->makeUser('client@example.com');
        $portal = $this->makePortal('acme', [$client->id()]);

        $this->actingAs($client)->get('/portal/acme/link-1')->assertNotFound();
        $this->actingAs($client)->get('/portal/acme/nope')->assertNotFound();

        $this->changeModule($portal, 'content-1', ['status' => 'inactive']);

        $this->actingAs($client)->get('/portal/acme/content-1')->assertNotFound();
        $this->actingAs($client)->get('/portal/acme')->assertDontSee('/portal/acme/content-1');
    }

    #[Test]
    public function inactive_modules_are_shown_locked_without_their_action(): void
    {
        $client = $this->makeUser('client@example.com');
        $portal = $this->makePortal('acme', [$client->id()]);
        $this->changeModule($portal, 'link-1', ['status' => 'inactive']);

        $this->actingAs($client)
            ->get('/portal/acme')
            ->assertSee('Staging site')
            ->assertSee('Locked')
            ->assertDontSee('https://staging.example.com');
    }
}

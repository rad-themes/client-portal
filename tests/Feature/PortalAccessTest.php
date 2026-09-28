<?php

namespace Komalnakrani\ClientPortal\Tests\Feature;

use Illuminate\Support\Facades\File;
use Komalnakrani\ClientPortal\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Contracts\Auth\User as UserContract;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\User;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;

class PortalAccessTest extends TestCase
{
    use PreventsSavingStacheItemsToDisk;

    private string $blueprintDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->blueprintDirectory = sys_get_temp_dir().'/client-portal-blueprints-'.uniqid();
        File::ensureDirectoryExists($this->blueprintDirectory.'/collections/portals');
        File::copy(__DIR__.'/../../resources/stubs/portal.yaml', $this->blueprintDirectory.'/collections/portals/portal.yaml');
        Blueprint::setDirectory($this->blueprintDirectory);

        Collection::make('portals')->save();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->blueprintDirectory);

        parent::tearDown();
    }

    #[Test]
    public function guests_are_redirected_to_the_login_page(): void
    {
        $this->get('/portal')->assertRedirect(route('client-portal.login'));
        $this->get('/portal/acme')->assertRedirect(route('client-portal.login'));
    }

    #[Test]
    public function the_login_page_renders_a_login_form(): void
    {
        $this->get('/portal/login')->assertOk()->assertSee('name="password"', false);
    }

    #[Test]
    public function a_client_sees_their_portal_with_its_phases_and_modules(): void
    {
        $client = $this->makeUser('client@example.com');
        $this->makePortal('acme', [$client->id()]);

        $this->actingAs($client)
            ->get('/portal/acme')
            ->assertOk()
            ->assertSee('Acme Website')
            ->assertSee('In progress')
            ->assertSee('Discovery')
            ->assertSee('Staging site')
            ->assertSee('https://staging.example.com')
            ->assertSee('/portal/acme/content-1');
    }

    #[Test]
    public function a_client_cannot_see_a_portal_they_are_not_assigned_to(): void
    {
        $this->makePortal('acme', [$this->makeUser('owner@example.com')->id()]);

        $this->actingAs($this->makeUser('stranger@example.com'))
            ->get('/portal/acme')
            ->assertForbidden();
    }

    #[Test]
    public function unknown_and_unpublished_portals_are_not_found(): void
    {
        $client = $this->makeUser('client@example.com');
        $this->makePortal('draft', [$client->id()])->published(false)->save();

        $this->actingAs($client)->get('/portal/missing')->assertNotFound();
        $this->actingAs($client)->get('/portal/draft')->assertNotFound();
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
        $this->makePortal('acme', [$client->id()], 'Acme Website');
        $this->makePortal('globex', [$client->id()], 'Globex Rebrand');
        $this->makePortal('initech', [$this->makeUser('other@example.com')->id()], 'Initech App');

        $this->actingAs($client)
            ->get('/portal')
            ->assertOk()
            ->assertSee('Acme Website')
            ->assertSee('Globex Rebrand')
            ->assertDontSee('Initech App');
    }

    #[Test]
    public function super_users_can_see_every_portal(): void
    {
        $this->makePortal('acme', [$this->makeUser('client@example.com')->id()]);

        $this->actingAs($this->makeUser('admin@example.com')->makeSuper()->save())
            ->get('/portal/acme')
            ->assertOk();
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
    public function only_content_modules_have_pages(): void
    {
        $client = $this->makeUser('client@example.com');
        $this->makePortal('acme', [$client->id()]);

        $this->actingAs($client)->get('/portal/acme/link-1')->assertNotFound();
        $this->actingAs($client)->get('/portal/acme/nope')->assertNotFound();
    }

    #[Test]
    public function inactive_modules_hide_their_action(): void
    {
        $client = $this->makeUser('client@example.com');
        $portal = $this->makePortal('acme', [$client->id()]);
        $phases = $portal->get('phases');
        $phases[0]['modules'][0]['status'] = 'inactive';
        $portal->set('phases', $phases)->save();

        $this->actingAs($client)
            ->get('/portal/acme')
            ->assertSee('Staging site')
            ->assertDontSee('https://staging.example.com');
    }

    #[Test]
    public function inactive_content_pages_cannot_be_opened(): void
    {
        $client = $this->makeUser('client@example.com');
        $portal = $this->makePortal('acme', [$client->id()]);
        $phases = $portal->get('phases');
        $phases[0]['modules'][1]['status'] = 'inactive';
        $portal->set('phases', $phases)->save();

        $this->actingAs($client)->get('/portal/acme/content-1')->assertNotFound();
        $this->actingAs($client)->get('/portal/acme')->assertDontSee('/portal/acme/content-1');
    }

    private function makeUser(string $email): UserContract
    {
        return tap(User::make()->email($email)->data(['name' => $email]))->save();
    }

    /**
     * @param  array<int, string>  $clients
     */
    private function makePortal(string $slug, array $clients, string $title = 'Acme Website'): EntryContract
    {
        return tap(Entry::make()->collection('portals')->slug($slug)->data([
            'title' => $title,
            'project_status' => 'In progress',
            'clients' => $clients,
            'phases' => [
                [
                    'id' => 'phase-1',
                    'title' => 'Discovery',
                    'modules' => [
                        [
                            'id' => 'link-1',
                            'type' => 'link',
                            'title' => 'Staging site',
                            'status' => 'active',
                            'url' => 'https://staging.example.com',
                        ],
                        [
                            'id' => 'content-1',
                            'type' => 'content',
                            'title' => 'Project brief',
                            'status' => 'active',
                            'body' => [
                                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Our goals for the new site.']]],
                            ],
                        ],
                    ],
                ],
            ],
        ]))->save();
    }
}

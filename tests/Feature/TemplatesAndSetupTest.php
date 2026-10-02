<?php

namespace RadThemes\ClientPortal\Tests\Feature;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use RadThemes\ClientPortal\Actions\ApplyTemplate;
use RadThemes\ClientPortal\Listeners\SetUpRegisteredClient;
use RadThemes\ClientPortal\Portals;
use RadThemes\ClientPortal\Tests\TestCase;
use Statamic\Events\UserRegistered;
use Statamic\Facades\Entry;
use Statamic\Testing\Concerns\FakesRoles;

class TemplatesAndSetupTest extends TestCase
{
    use FakesRoles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setTestRoles(['client' => []]);
    }

    #[Test]
    public function applying_a_template_syncs_phases_and_keeps_module_progress(): void
    {
        $template = $this->makePortal('tpl', [], ['is_template' => true, 'phases' => [
            ['id' => 'p1', 'title' => 'New phase name', 'modules' => [
                ['id' => 'link-1', 'type' => 'link', 'title' => 'Renamed link', 'status' => 'active', 'url' => 'https://new.example.com'],
                ['id' => 'new-1', 'type' => 'content', 'title' => 'Brand new module', 'status' => 'active'],
            ]],
        ]]);
        $portal = $this->makePortal('acme', []);
        $this->changeModule($portal, 'link-1', ['status' => 'complete', 'completed_by' => 'someone']);

        (new ApplyTemplate)->run(collect([$portal]), ['template' => [$template->id()]]);

        $phases = Entry::find($portal->id())->get('phases');
        $this->assertSame('New phase name', $phases[0]['title']);
        $this->assertSame(['link-1', 'new-1'], array_column($phases[0]['modules'], 'id'));
        $this->assertSame('Renamed link', $phases[0]['modules'][0]['title']);
        $this->assertSame('complete', $phases[0]['modules'][0]['status']);
        $this->assertSame('someone', $phases[0]['modules'][0]['completed_by']);
        $this->assertSame('active', $phases[0]['modules'][1]['status']);
    }

    #[Test]
    public function portals_can_only_be_synced_from_templates(): void
    {
        $notTemplate = $this->makePortal('other', []);

        $this->expectException(\InvalidArgumentException::class);

        (new ApplyTemplate)->run(collect([$this->makePortal('acme', [])]), ['template' => [$notTemplate->id()]]);
    }

    #[Test]
    public function registering_on_the_portal_gives_the_client_role_and_their_own_portal(): void
    {
        $template = $this->makePortal('tpl', [], ['is_template' => true, 'title' => 'Template']);
        $this->setSettings(['allow_registration' => true, 'registration_template' => [$template->id()]]);
        $user = $this->makeUser('newclient@example.com');
        request()->merge(['_client_portal' => '1']);

        (new SetUpRegisteredClient)->handle(new UserRegistered($user));

        $this->assertTrue($user->hasRole('client'));

        $portal = Portals::forUser($user)->first();
        $this->assertNotNull($portal);
        $this->assertSame('Newclient', $portal->get('title'));
        $this->assertSame([$user->id()], $portal->get('clients'));
        $this->assertNull($portal->get('is_template'));
    }

    #[Test]
    public function registrations_outside_the_portal_or_when_disabled_are_ignored(): void
    {
        $this->setSettings(['allow_registration' => false]);
        $user = $this->makeUser('someone@example.com');
        request()->merge(['_client_portal' => '1']);

        (new SetUpRegisteredClient)->handle(new UserRegistered($user));

        $this->assertFalse($user->hasRole('client'));
        $this->get('/portal/register')->assertNotFound();
    }

    #[Test]
    public function the_registration_page_is_available_when_enabled(): void
    {
        $this->setSettings(['allow_registration' => true]);

        $this->get('/portal/register')->assertOk()->assertSee('name="_client_portal"', false);
        $this->get('/portal/login')->assertSee('/portal/register');
    }

    #[Test]
    public function auth_pages_render_their_forms(): void
    {
        $this->get('/portal/login')->assertOk()->assertSee('name="password"', false)->assertSee('/portal/forgot-password');
        $this->get('/portal/forgot-password')->assertOk()->assertSee('name="email"', false);
        $this->get('/portal/reset-password?token=abc&email=a@b.test')->assertOk()->assertSee('name="token"', false);
    }

    #[Test]
    public function branding_settings_are_applied(): void
    {
        $this->setSettings(['portal_name' => 'Agency Hub', 'brand_color' => '#ff0055', 'login_heading' => 'Welcome back']);

        $this->get('/portal/login')
            ->assertSee('Agency Hub')
            ->assertSee('--portal-brand: #ff0055', false)
            ->assertSee('Welcome back');
    }

    #[Test]
    public function an_invalid_brand_colour_is_ignored(): void
    {
        $this->setSettings(['brand_color' => 'red;}</style><script>alert(1)</script>']);

        $this->get('/portal/login')->assertDontSee('<script>alert(1)</script>', false);
    }

    #[Test]
    public function portals_export_and_import_without_client_data(): void
    {
        $client = $this->makeUser('client@example.com');
        $portal = $this->makePortal('acme', [$client->id()]);
        $this->changeModule($portal, 'upload-1', ['completed_by' => $client->id()]);
        Portals::addMessage($portal, $client, 'A private message');
        $path = sys_get_temp_dir().'/portal-export-'.uniqid().'.json';

        $this->artisan('client-portal:export', ['slug' => 'acme', 'path' => $path])->assertSuccessful();

        $json = File::get($path);
        $this->assertStringNotContainsString($client->id(), $json);
        $this->assertStringNotContainsString('A private message', $json);

        $this->artisan('client-portal:import', ['path' => $path])->assertFailed();
        $this->artisan('client-portal:import', ['path' => $path, '--slug' => 'acme-copy'])->assertSuccessful();

        $copy = Entry::query()->where('slug', 'acme-copy')->first();
        $this->assertFalse($copy->published());
        $this->assertSame([], $copy->get('clients'));
        $this->assertSame('Acme Website', $copy->get('title'));

        File::delete($path);
    }
}

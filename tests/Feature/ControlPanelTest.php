<?php

namespace Komalnakrani\ClientPortal\Tests\Feature;

use Illuminate\Support\Facades\File;
use Komalnakrani\ClientPortal\Portals;
use Komalnakrani\ClientPortal\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\User;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;

class ControlPanelTest extends TestCase
{
    use PreventsSavingStacheItemsToDisk;

    private string $blueprintDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->blueprintDirectory = sys_get_temp_dir().'/client-portal-cp-blueprints-'.uniqid();
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
    public function super_admin_can_access_cp_client_portal_dashboard(): void
    {
        $admin = User::make()->email('admin@example.com')->makeSuper();
        $admin->save();

        $this->actingAs($admin)
            ->get(route('statamic.cp.client-portal.index'))
            ->assertOk()
            ->assertSee('Client Portals');
    }

    #[Test]
    public function super_admin_can_create_portal_from_template(): void
    {
        $admin = User::make()->email('admin@example.com')->makeSuper();
        $admin->save();

        $this->actingAs($admin)
            ->post(route('statamic.cp.client-portal.store'), [
                'title' => 'New Client Website',
                'slug' => 'new-client-website',
                'template' => 'website-redesign',
                'access_type' => 'password',
                'access_password' => 'secret123',
            ])
            ->assertRedirect();

        $entry = Portals::findBySlug('new-client-website');
        $this->assertNotNull($entry);
        $this->assertEquals('New Client Website', $entry->get('title'));
        $this->assertEquals('password', $entry->get('access_type'));
    }

    #[Test]
    public function super_admin_can_duplicate_portal(): void
    {
        $admin = User::make()->email('admin@example.com')->makeSuper();
        $admin->save();

        $entry = Entry::make()->collection('portals')->slug('original')->data([
            'title' => 'Original Portal',
            'project_status' => 'Active',
            'phases' => [],
        ]);
        $entry->save();

        $this->actingAs($admin)
            ->post(route('statamic.cp.client-portal.duplicate', $entry->id()))
            ->assertRedirect(route('statamic.cp.client-portal.index'));

        $all = Portals::allPortals();
        $this->assertCount(2, $all);
    }
}

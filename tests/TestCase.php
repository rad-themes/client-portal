<?php

namespace Komalnakrani\ClientPortal\Tests;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Komalnakrani\ClientPortal\Portals;
use Komalnakrani\ClientPortal\ServiceProvider;
use Statamic\Contracts\Auth\User as UserContract;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Addon;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\User;
use Statamic\Testing\AddonTestCase;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;

abstract class TestCase extends AddonTestCase
{
    use PreventsSavingStacheItemsToDisk;

    protected string $addonServiceProvider = ServiceProvider::class;

    private string $blueprintDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->blueprintDirectory = sys_get_temp_dir().'/client-portal-blueprints-'.uniqid();
        File::ensureDirectoryExists($this->blueprintDirectory.'/collections/portals');
        File::copy(__DIR__.'/../resources/stubs/portal.yaml', $this->blueprintDirectory.'/collections/portals/portal.yaml');
        Blueprint::setDirectory($this->blueprintDirectory);

        Storage::fake(Portals::FILES_DISK);
        AssetContainer::make(Portals::FILES_CONTAINER)->disk(Portals::FILES_DISK)->save();
        AssetContainer::make('assets')->disk(Portals::FILES_DISK)->save();

        Collection::make(Portals::COLLECTION)->save();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->blueprintDirectory);
        File::delete(resource_path('addons/client-portal.yaml'));
        File::deleteDirectory(storage_path('client-portal'));

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    protected function setSettings(array $settings): void
    {
        Addon::get('komalnakrani/client-portal')->settings()->set($settings)->save();
    }

    protected function makeUser(string $email, bool $super = false): UserContract
    {
        $user = User::make()->email($email)->data(['name' => ucfirst(strtok($email, '@'))]);

        return tap($super ? $user->makeSuper() : $user)->save();
    }

    /**
     * A portal with one "Discovery" phase containing a link, a content page, a file and an upload module.
     *
     * @param  array<int, string>  $clients
     * @param  array<string, mixed>  $data
     */
    protected function makePortal(string $slug, array $clients, array $data = []): EntryContract
    {
        return tap(Entry::make()->collection(Portals::COLLECTION)->slug($slug)->published(true)->data(array_merge([
            'title' => 'Acme Website',
            'project_status' => 'In progress',
            'clients' => $clients,
            'phases' => [
                [
                    'id' => 'phase-1',
                    'title' => 'Discovery',
                    'modules' => [
                        ['id' => 'link-1', 'type' => 'link', 'title' => 'Staging site', 'status' => 'active', 'url' => 'https://staging.example.com', 'client_can_complete' => true, 'complete_label' => 'Approve staging'],
                        ['id' => 'content-1', 'type' => 'content', 'title' => 'Project brief', 'status' => 'active', 'body' => [
                            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Our goals for the new site.']]],
                        ]],
                        ['id' => 'file-1', 'type' => 'file', 'title' => 'Contract', 'status' => 'active', 'files' => ['docs/contract.pdf']],
                        ['id' => 'upload-1', 'type' => 'upload', 'title' => 'Brand assets', 'status' => 'active'],
                    ],
                ],
            ],
        ], $data)))->save();
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    protected function changeModule(EntryContract $portal, string $moduleId, array $changes): void
    {
        Portals::updateModule($portal, $moduleId, fn (array $module) => array_merge($module, $changes));
    }

    protected function module(string $portalSlug, string $moduleId): array
    {
        return Portals::findModule(Entry::query()->where('slug', $portalSlug)->first(), $moduleId)['module'];
    }
}

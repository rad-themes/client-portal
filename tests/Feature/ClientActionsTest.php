<?php

namespace Komalnakrani\ClientPortal\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Komalnakrani\ClientPortal\Notifications\ClientActivity;
use Komalnakrani\ClientPortal\Portals;
use Komalnakrani\ClientPortal\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ClientActionsTest extends TestCase
{
    #[Test]
    public function private_files_download_only_for_people_with_access(): void
    {
        Storage::disk(Portals::FILES_DISK)->put('docs/contract.pdf', 'PDF contents');
        $client = $this->makeUser('client@example.com');
        $this->makePortal('acme', [$client->id()]);

        $this->actingAs($client)
            ->get('/portal/acme')
            ->assertSee('contract.pdf')
            ->assertSee('/portal/acme/file-1/files/0');

        $this->actingAs($client)
            ->get('/portal/acme/file-1/files/0')
            ->assertOk()
            ->assertDownload('contract.pdf');

        $this->actingAs($this->makeUser('stranger@example.com'))
            ->get('/portal/acme/file-1/files/0')
            ->assertForbidden();
    }

    #[Test]
    public function downloads_only_serve_files_attached_to_an_active_file_module(): void
    {
        Storage::disk(Portals::FILES_DISK)->put('docs/contract.pdf', 'PDF contents');
        $client = $this->makeUser('client@example.com');
        $portal = $this->makePortal('acme', [$client->id()]);

        $this->actingAs($client)->get('/portal/acme/file-1/files/1')->assertNotFound();
        $this->actingAs($client)->get('/portal/acme/link-1/files/0')->assertNotFound();

        $this->changeModule($portal, 'file-1', ['status' => 'inactive']);

        $this->actingAs($client)->get('/portal/acme/file-1/files/0')->assertNotFound();
    }

    #[Test]
    public function a_client_can_mark_an_allowed_module_complete(): void
    {
        Notification::fake();
        $this->makeUser('admin@example.com', super: true);
        $client = $this->makeUser('client@example.com');
        $this->makePortal('acme', [$client->id()]);

        $this->actingAs($client)
            ->get('/portal/acme')
            ->assertSee('Approve staging');

        $this->actingAs($client)
            ->post('/portal/acme/link-1/complete')
            ->assertRedirect(route('client-portal.show', 'acme'));

        $module = $this->module('acme', 'link-1');
        $this->assertSame('complete', $module['status']);
        $this->assertSame($client->id(), $module['completed_by']);

        Notification::assertSentOnDemand(ClientActivity::class, function (ClientActivity $notification, array $channels, object $notifiable) {
            return $notifiable->routes['mail'] === ['admin@example.com']
                && str_contains($notification->item['description'], 'Staging site');
        });
    }

    #[Test]
    public function clients_cannot_complete_modules_that_do_not_allow_it(): void
    {
        $client = $this->makeUser('client@example.com');
        $portal = $this->makePortal('acme', [$client->id()]);

        $this->actingAs($client)->post('/portal/acme/content-1/complete')->assertForbidden();

        $this->changeModule($portal, 'link-1', ['status' => 'inactive']);
        $this->actingAs($client)->post('/portal/acme/link-1/complete')->assertNotFound();

        $this->assertSame('active', $this->module('acme', 'content-1')['status']);
        $this->assertSame('inactive', $this->module('acme', 'link-1')['status']);
    }

    #[Test]
    public function a_client_can_upload_files_to_an_upload_module(): void
    {
        Notification::fake();
        $this->setSettings(['admin_emails' => ['team@agency.test']]);
        $client = $this->makeUser('client@example.com');
        $this->makePortal('acme', [$client->id()]);

        $this->actingAs($client)
            ->post('/portal/acme/upload-1/upload', ['files' => [
                UploadedFile::fake()->create('Brand Guide.pdf', 100, 'application/pdf'),
                UploadedFile::fake()->image('logo.png'),
            ]])
            ->assertRedirect(route('client-portal.show', 'acme'));

        $portal = Portals::findBySlug('acme');
        $uploads = Portals::uploads($portal, 'upload-1');
        $this->assertCount(2, $uploads);
        $this->assertSame(['brand-guide.pdf', 'logo.png'], collect($uploads)->map(fn ($path) => basename($path))->sort()->values()->all());
        $this->assertStringStartsWith("uploads/{$portal->id()}/upload-1/", reset($uploads));

        $key = array_key_first($uploads);
        $this->actingAs($client)->get("/portal/acme/upload-1/uploads/{$key}")->assertOk()->assertDownload();
        $this->actingAs($client)->get('/portal/acme')->assertSee('brand-guide.pdf')->assertSee("/portal/acme/upload-1/uploads/{$key}");
        $this->actingAs($this->makeUser('stranger@example.com'))->get("/portal/acme/upload-1/uploads/{$key}")->assertForbidden();
        $this->actingAs($client)->get('/portal/acme/upload-1/uploads/nope')->assertNotFound();

        Notification::assertSentOnDemand(ClientActivity::class, fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === ['team@agency.test']);
    }

    #[Test]
    public function uploads_reject_dangerous_and_oversized_files(): void
    {
        $this->setSettings(['max_upload_mb' => 1]);
        $client = $this->makeUser('client@example.com');
        $this->makePortal('acme', [$client->id()]);

        $this->actingAs($client)
            ->post('/portal/acme/upload-1/upload', ['files' => [UploadedFile::fake()->create('shell.php', 1)]])
            ->assertSessionHasErrors('files.0');

        $this->actingAs($client)
            ->post('/portal/acme/upload-1/upload', ['files' => [UploadedFile::fake()->create('page.html', 1)]])
            ->assertSessionHasErrors('files.0');

        $this->actingAs($client)
            ->post('/portal/acme/upload-1/upload', ['files' => [UploadedFile::fake()->create('huge.pdf', 2048)]])
            ->assertSessionHasErrors('files.0');

        $this->assertSame([], Portals::uploads(Portals::findBySlug('acme'), 'upload-1'));
    }

    #[Test]
    public function uploads_are_only_accepted_by_upload_modules_in_accessible_portals(): void
    {
        $client = $this->makeUser('client@example.com');
        $this->makePortal('acme', [$client->id()]);
        $file = UploadedFile::fake()->create('notes.pdf', 10);

        $this->actingAs($client)->post('/portal/acme/file-1/upload', ['files' => [$file]])->assertNotFound();
        $this->actingAs($this->makeUser('stranger@example.com'))->post('/portal/acme/upload-1/upload', ['files' => [$file]])->assertForbidden();
    }

    #[Test]
    public function staff_actions_do_not_notify_admins(): void
    {
        Notification::fake();
        $this->makePortal('acme', []);

        $this->actingAs($this->makeUser('admin@example.com', super: true))->post('/portal/acme/link-1/complete');

        Notification::assertNothingSent();
    }
}

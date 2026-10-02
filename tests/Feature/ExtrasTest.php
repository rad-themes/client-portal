<?php

namespace RadThemes\ClientPortal\Tests\Feature;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use RadThemes\ClientPortal\Activity;
use RadThemes\ClientPortal\Notifications\ActivityDigest;
use RadThemes\ClientPortal\Notifications\ClientActivity;
use RadThemes\ClientPortal\Notifications\DueDateReminder;
use RadThemes\ClientPortal\Notifications\NewMessage;
use RadThemes\ClientPortal\Portals;
use RadThemes\ClientPortal\Tests\TestCase;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Facades\User;

class ExtrasTest extends TestCase
{
    #[Test]
    public function messages_are_off_unless_enabled_on_the_portal(): void
    {
        $client = $this->makeUser('client@example.com');
        $this->makePortal('acme', [$client->id()]);

        $this->actingAs($client)->get('/portal/acme')->assertDontSee('id="messages"', false);
        $this->actingAs($client)->post('/portal/acme/messages', ['body' => 'Hi'])->assertNotFound();
    }

    #[Test]
    public function clients_and_staff_can_exchange_messages(): void
    {
        Notification::fake();
        $this->makeUser('admin@example.com', super: true);
        $client = $this->makeUser('client@example.com');
        $this->makePortal('acme', [$client->id()], ['comments_enabled' => true]);

        $this->actingAs($client)
            ->post('/portal/acme/messages', ['body' => "Looks great!\n<script>alert(1)</script>"])
            ->assertRedirect(route('client-portal.show', 'acme').'#messages');

        Notification::assertSentOnDemand(ClientActivity::class, fn (ClientActivity $n) => str_contains($n->item['description'], 'Looks great!'));

        $this->actingAs(User::findByEmail('admin@example.com'))->post('/portal/acme/messages', ['body' => 'Thanks, shipping Friday.']);

        Notification::assertSentTo($client, NewMessage::class, fn (NewMessage $n) => $n->body === 'Thanks, shipping Friday.');

        $this->actingAs($client)
            ->get('/portal/acme')
            ->assertSee('Looks great!')
            ->assertSee('Thanks, shipping Friday.')
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    #[Test]
    public function messages_are_validated_and_access_checked(): void
    {
        $client = $this->makeUser('client@example.com');
        $this->makePortal('acme', [$client->id()], ['comments_enabled' => true]);

        $this->actingAs($client)->post('/portal/acme/messages', ['body' => ''])->assertSessionHasErrors('body');
        $this->actingAs($client)->post('/portal/acme/messages', ['body' => str_repeat('a', 5001)])->assertSessionHasErrors('body');
        $this->actingAs($this->makeUser('stranger@example.com'))->post('/portal/acme/messages', ['body' => 'Hi'])->assertForbidden();

        $this->assertNull(Portals::findBySlug('acme')->get('messages'));
    }

    #[Test]
    public function a_gallery_module_shows_its_images(): void
    {
        Storage::disk(Portals::FILES_DISK)->put('moodboard/one.jpg', 'x');
        Storage::disk(Portals::FILES_DISK)->put('moodboard/two.jpg', 'x');
        $client = $this->makeUser('client@example.com');
        $portal = $this->makePortal('acme', [$client->id()]);
        $phases = $portal->get('phases');
        $phases[0]['modules'][] = ['id' => 'gallery-1', 'type' => 'gallery', 'title' => 'Moodboard', 'status' => 'active', 'images' => ['moodboard/one.jpg', 'moodboard/two.jpg']];
        $portal->set('phases', $phases)->save();

        $response = $this->actingAs($client)->get('/portal/acme')->assertSee('aria-label="Moodboard"', false);

        $this->assertSame(2, substr_count($response->getContent(), 'loading="lazy" class="h-44'));
    }

    #[Test]
    public function safe_file_types_can_be_previewed_in_the_browser(): void
    {
        Storage::disk(Portals::FILES_DISK)->put('docs/contract.pdf', '%PDF');
        Storage::disk(Portals::FILES_DISK)->put('docs/archive.zip', 'PK');
        $client = $this->makeUser('client@example.com');
        $portal = $this->makePortal('acme', [$client->id()]);
        $this->changeModule($portal, 'file-1', ['files' => ['docs/contract.pdf', 'docs/archive.zip']]);

        $this->actingAs($client)->get('/portal/acme')
            ->assertSee('/portal/acme/file-1/files/0?preview=1')
            ->assertDontSee('/portal/acme/file-1/files/1?preview=1');

        $preview = $this->actingAs($client)->get('/portal/acme/file-1/files/0?preview=1')->assertOk();
        $this->assertStringStartsWith('inline', $preview->headers->get('Content-Disposition'));
        $this->assertSame('application/pdf', $preview->headers->get('Content-Type'));
        $this->assertSame('nosniff', $preview->headers->get('X-Content-Type-Options'));

        $zip = $this->actingAs($client)->get('/portal/acme/file-1/files/1?preview=1')->assertOk();
        $this->assertStringStartsWith('attachment', $zip->headers->get('Content-Disposition'));
    }

    #[Test]
    public function the_registration_captcha_must_be_passed(): void
    {
        $this->setSettings([
            'allow_registration' => true,
            'captcha_provider' => 'turnstile',
            'captcha_site_key' => 'site-key',
            'captcha_secret_key' => 'secret-key',
        ]);

        $this->get('/portal/register')->assertSee('class="cf-turnstile" data-sitekey="site-key"', false);

        Http::fake(['challenges.cloudflare.com/*' => Http::sequence()->push(['success' => false])->push(['success' => true])]);

        $this->post('/!/auth/register', $this->registration(['cf-turnstile-response' => 'bad']))->assertSessionHasErrors('captcha', null, 'user.register');
        $this->assertNull(User::findByEmail('new@example.com'));

        $this->post('/!/auth/register', $this->registration(['cf-turnstile-response' => 'good']));
        $this->assertNotNull(User::findByEmail('new@example.com'));

        Http::assertSent(fn ($request) => $request['secret'] === 'secret-key' && $request['response'] === 'good');
    }

    #[Test]
    public function a_portal_can_send_activity_to_its_own_recipients_or_be_muted(): void
    {
        Notification::fake();
        $this->setSettings(['admin_emails' => ['team@agency.test']]);
        $client = $this->makeUser('client@example.com');
        $portal = $this->makePortal('acme', [$client->id()], ['notification_emails' => ['pm@agency.test']]);
        $muted = $this->makePortal('muted', [$client->id()], ['mute_notifications' => true]);

        Activity::record($portal, $client, 'did a thing');
        Activity::record($muted, $client, 'did a quiet thing');

        Notification::assertSentOnDemandTimes(ClientActivity::class, 1);
        Notification::assertSentOnDemand(ClientActivity::class, fn ($n, $c, $notifiable) => $notifiable->routes['mail'] === ['pm@agency.test']);
    }

    #[Test]
    public function digests_are_split_by_portal_recipients(): void
    {
        Notification::fake();
        $this->setSettings(['admin_notifications' => 'digest', 'admin_emails' => ['team@agency.test']]);
        $client = $this->makeUser('client@example.com');

        Activity::record($this->makePortal('acme', [$client->id()], ['notification_emails' => ['pm@agency.test']]), $client, 'one');
        Activity::record($this->makePortal('globex', [$client->id()]), $client, 'two');
        Activity::sendDigest();

        Notification::assertSentOnDemandTimes(ActivityDigest::class, 2);
        Notification::assertSentOnDemand(ActivityDigest::class, fn ($n, $c, $notifiable) => $notifiable->routes['mail'] === ['pm@agency.test'] && count($n->items) === 1);
        Notification::assertSentOnDemand(ActivityDigest::class, fn ($n, $c, $notifiable) => $notifiable->routes['mail'] === ['team@agency.test'] && count($n->items) === 1);
    }

    #[Test]
    public function reminders_can_be_turned_off_per_portal(): void
    {
        Notification::fake();
        $client = $this->makeUser('client@example.com');
        $portal = $this->makePortal('acme', [$client->id()], ['disable_reminders' => true]);
        $this->changeModule($portal, 'link-1', ['due_date' => today()->addDays(2)->toDateString()]);

        $this->artisan('client-portal:send-reminders');

        Notification::assertNotSentTo($client, DueDateReminder::class);
    }

    #[Test]
    public function saving_a_stale_copy_does_not_undo_a_client_completion(): void
    {
        $client = $this->makeUser('client@example.com');
        $portal = $this->makePortal('acme', [$client->id()]);
        $staleEditorPhases = $portal->get('phases');

        $this->actingAs($client)->post('/portal/acme/link-1/complete');

        // The editor, opened before the client completed the module, saves with a new title.
        $staleEditorPhases[0]['modules'][0]['title'] = 'Staging site (v2)';
        $fresh = Entry::find($portal->id());
        $fresh->set('phases', $staleEditorPhases)->save();

        $module = $this->module('acme', 'link-1');
        $this->assertSame('Staging site (v2)', $module['title']);
        $this->assertSame('complete', $module['status']);
        $this->assertSame($client->id(), $module['completed_by']);
    }

    #[Test]
    public function an_editor_can_still_reopen_a_completed_module(): void
    {
        $client = $this->makeUser('client@example.com');
        $portal = $this->makePortal('acme', [$client->id()]);
        $this->actingAs($client)->post('/portal/acme/link-1/complete');

        // The editor loaded after the completion, so it round-trips the same completed_at.
        $fresh = Entry::find($portal->id());
        $phases = $fresh->get('phases');
        $phases[0]['modules'][0]['status'] = 'active';
        $fresh->set('phases', $phases)->save();

        $this->assertSame('active', $this->module('acme', 'link-1')['status']);
    }

    #[Test]
    public function localized_portals_are_not_listed_twice_and_use_the_origin(): void
    {
        config(['statamic.editions.pro' => true]);
        Site::setSites([
            'en' => ['name' => 'English', 'url' => '/', 'locale' => 'en_US'],
            'fr' => ['name' => 'French', 'url' => '/fr/', 'locale' => 'fr_FR'],
        ]);
        $client = $this->makeUser('client@example.com');
        $portal = $this->makePortal('acme', [$client->id()]);
        $portal->makeLocalization('fr')->slug('acme')->set('title', 'Site Acme')->save();

        $this->actingAs($client)->get('/portal')->assertRedirect(route('client-portal.show', 'acme'));
        $this->actingAs($client)->get('/portal/acme')->assertOk()->assertSee('Acme Website');
        $this->assertSame('en', Portals::findBySlug('acme')->locale());
    }

    /**
     * @param  array<string, string>  $extra
     * @return array<string, string>
     */
    private function registration(array $extra): array
    {
        return array_merge([
            '_client_portal' => '1',
            'name' => 'New Client',
            'email' => 'new@example.com',
            'password' => 'secret-pass-123',
            'password_confirmation' => 'secret-pass-123',
        ], $extra);
    }
}

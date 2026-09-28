<?php

namespace Komalnakrani\ClientPortal\Listeners;

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Komalnakrani\ClientPortal\Portals;
use Statamic\Events\UserRegistering;

/**
 * Checks the reCAPTCHA or Turnstile answer on the portal registration form.
 */
class VerifyCaptcha
{
    public const PROVIDERS = [
        'recaptcha' => ['field' => 'g-recaptcha-response', 'verify' => 'https://www.google.com/recaptcha/api/siteverify'],
        'turnstile' => ['field' => 'cf-turnstile-response', 'verify' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify'],
    ];

    public function handle(UserRegistering $event): void
    {
        $provider = self::PROVIDERS[Portals::setting('captcha_provider')] ?? null;
        $secret = Portals::setting('captcha_secret_key');

        if (! $provider || ! $secret || ! request()->boolean('_client_portal')) {
            return;
        }

        $answer = (string) request()->input($provider['field']);

        $passed = $answer !== '' && Http::asForm()->timeout(10)->post($provider['verify'], [
            'secret' => $secret,
            'response' => $answer,
            'remoteip' => request()->ip(),
        ])->json('success') === true;

        if (! $passed) {
            throw ValidationException::withMessages(['captcha' => __('Please confirm you’re not a robot.')]);
        }
    }
}

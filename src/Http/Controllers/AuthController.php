<?php

namespace RadThemes\ClientPortal\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use RadThemes\ClientPortal\Portals;
use Statamic\Facades\User;
use Statamic\View\View;

class AuthController extends Controller
{
    public function login(): View|RedirectResponse
    {
        return $this->guestView('login', __('Log in'));
    }

    public function register(): View|RedirectResponse
    {
        abort_unless(Portals::setting('allow_registration'), 404);

        return $this->guestView('register', __('Create an account'));
    }

    public function forgotPassword(): View|RedirectResponse
    {
        return $this->guestView('forgot-password', __('Reset your password'));
    }

    public function resetPassword(): View|RedirectResponse
    {
        return $this->guestView('reset-password', __('Choose a new password'));
    }

    private function guestView(string $template, string $title): View|RedirectResponse
    {
        if (User::current()) {
            return redirect()->route('client-portal.index');
        }

        return $this->view($template, ['title' => $title]);
    }
}

<?php

namespace RadThemes\ClientPortal\Http\Controllers;

use RadThemes\ClientPortal\Extensions;
use Statamic\Facades\User;
use Statamic\View\View;

class ExtensionController extends Controller
{
    public function show(string $page): View
    {
        $extension = Extensions::find($page, User::current()) ?? abort(404);

        return $this->view('extension', [
            'title' => $extension['label'],
            'extension_html' => ($extension['render'])(User::current()),
        ]);
    }
}

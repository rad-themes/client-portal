<?php

namespace Komalnakrani\ClientPortal\Http\Controllers\CP;

use Komalnakrani\ClientPortal\Templates;
use Statamic\Http\Controllers\CP\CpController;

class TemplateController extends CpController
{
    public function index()
    {
        $templates = Templates::defaults();

        return view('client-portal::cp.templates.index', [
            'templates' => $templates,
        ]);
    }
}

<?php

namespace Komalnakrani\ClientPortal\Tests;

use Komalnakrani\ClientPortal\ServiceProvider;
use Statamic\Facades\AssetContainer;
use Statamic\Testing\AddonTestCase;

abstract class TestCase extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;

    protected function setUp(): void
    {
        parent::setUp();

        if (! AssetContainer::find('assets')) {
            AssetContainer::make('assets')->title('Assets')->disk('local')->save();
        }
    }
}

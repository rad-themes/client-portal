<?php

namespace Komalnakrani\ClientPortal\Tests;

use Komalnakrani\ClientPortal\ServiceProvider;
use Statamic\Testing\AddonTestCase;

abstract class TestCase extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;
}

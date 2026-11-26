<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests assert application behaviour, not the presence of compiled frontend assets.
        $this->withoutVite();

        if (! class_exists('finfo', false)) {
            require_once __DIR__.'/Fixtures/finfo_compat.php';
        }
    }
}

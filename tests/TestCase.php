<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Traits\LoadsSchemaDump;

abstract class TestCase extends BaseTestCase
{
    use LoadsSchemaDump;

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Load schema dump once for all tests
        $this->loadSchemaDump();

        // Begin transaction for each test
        $this->beginDatabaseTransaction();
    }
}

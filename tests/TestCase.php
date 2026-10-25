<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        config([
            'houston.github_token' => 'test-token',
            'inertia.ssr.enabled' => false,
            // Calendar time keeps duration assertions readable; working time has dedicated tests.
            'houston.working_time.days' => [1, 2, 3, 4, 5, 6, 7],
        ]);

        Http::preventStrayRequests();
    }
}

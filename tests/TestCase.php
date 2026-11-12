<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    /**
     * Requests are made in the language of the tests (APP_LOCALE), rather than the English that Symfony gives every
     * request by default, which the language of the interface would follow.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Accept-Language', config('app.locale'));
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}

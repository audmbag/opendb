<?php

use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Route::middleware('web')->get('/testing/errors/{status}', fn (int $status) => abort($status));
});

test('an unknown address shows the error page of the interface, with the shared data of the pages', function () {
    $this->get('/this/page/does-not-exist')
        ->assertNotFound()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('error')
            ->where('status', 404)
            ->where('appName', config('app.name')));
});

test('each error with its own page is shown by the error page of the interface', function (int $status) {
    config(['app.debug' => false]);

    $this->get("/testing/errors/{$status}")
        ->assertStatus($status)
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('error')
            ->where('status', $status));
})->with([403, 404, 419, 429, 500, 503]);

test('a server error keeps the detailed report of Laravel while debugging', function () {
    config(['app.debug' => true]);

    $this->get('/testing/errors/500')
        ->assertInternalServerError()
        ->assertDontSee('"component":"error"', escape: false);
});

test('an error without its own page keeps the default answer of Laravel', function () {
    config(['app.debug' => false]);

    $this->get('/testing/errors/402')
        ->assertPaymentRequired()
        ->assertDontSee('"component":"error"', escape: false);
});

test('a request expecting JSON keeps its JSON answer', function () {
    $this->getJson('/this/page/does-not-exist')
        ->assertNotFound()
        ->assertJsonStructure(['message']);
});

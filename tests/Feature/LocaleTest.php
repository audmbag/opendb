<?php

use App\Models\User;

test('the page is rendered in the language kept in the cookie, before the one of the browser', function (string $cookie, string $acceptLanguage, string $language) {
    $this->withUnencryptedCookie('locale', $cookie)
        ->withHeader('Accept-Language', $acceptLanguage)
        ->get(route('login'))
        ->assertOk()
        ->assertSee('<html lang="'.$language.'"', escape: false);
})->with([
    'english cookie' => ['en', 'fr-FR,fr;q=0.9', 'en'],
    'french cookie' => ['fr', 'en-US,en;q=0.9', 'fr'],
]);

test('the page follows the language of the browser without a cookie', function (string $acceptLanguage, string $language) {
    $this->withHeader('Accept-Language', $acceptLanguage)
        ->get(route('login'))
        ->assertOk()
        ->assertSee('<html lang="'.$language.'"', escape: false);
})->with([
    'english browser' => ['en-GB,en;q=0.9,fr;q=0.5', 'en'],
    'french browser' => ['fr-CA,fr;q=0.9,en;q=0.5', 'fr'],
]);

test('the page falls back to the language of the application for an unsupported or missing language', function (array $cookies, string $acceptLanguage) {
    config(['app.locale' => 'en']);

    $this->withUnencryptedCookies($cookies)
        ->withHeader('Accept-Language', $acceptLanguage)
        ->get(route('login'))
        ->assertOk()
        ->assertSee('<html lang="en"', escape: false);
})->with([
    'unsupported cookie and browser' => [['locale' => 'de'], 'de-DE,de;q=0.9'],
    'no language at all' => [[], ''],
]);

test('the messages of the server are written in the language of the interface', function (string $language, string $message) {
    $this->actingAs(User::factory()->create())
        ->withUnencryptedCookie('locale', $language)
        ->withCredentials()
        ->putJson('/settings/agent', ['accessMode' => 'read-only', 'effort' => 'default', 'maxSteps' => 15, 'maxRows' => 200])
        ->assertOk()
        ->assertJsonPath('message', $message);
})->with([
    'english' => ['en', 'The agent settings have been saved.'],
    'french' => ['fr', "Les réglages de l'agent ont été enregistrés."],
]);

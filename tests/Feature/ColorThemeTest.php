<?php

test('the page is rendered with the color theme kept in the cookie', function () {
    $this->withUnencryptedCookie('color_theme', 'ocean')
        ->get(route('login'))
        ->assertOk()
        ->assertSee('data-theme="ocean"', escape: false);
});

test('the page has no color theme for the neutral one or without cookie', function (?string $colorTheme) {
    $request = $colorTheme === null ? $this : $this->withUnencryptedCookie('color_theme', $colorTheme);

    $request->get(route('login'))
        ->assertOk()
        ->assertDontSee('data-theme', escape: false);
})->with([
    'without cookie' => [null],
    'neutral' => ['neutral'],
]);

test('a color theme that is not a theme name is ignored', function () {
    $this->withUnencryptedCookie('color_theme', '"><script>alert(1)</script>')
        ->get(route('login'))
        ->assertOk()
        ->assertDontSee('data-theme', escape: false)
        ->assertDontSee('<script>alert(1)</script>', escape: false);
});

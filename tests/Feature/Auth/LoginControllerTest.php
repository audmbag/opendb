<?php

use App\Models\User;

it('suggests changing the password every installation starts with after signing in', function () {
    User::factory()->create(['name' => 'opendb', 'password' => 'opendb']);

    $this->post('/login', ['name' => 'opendb', 'password' => 'opendb'])
        ->assertRedirect('/')
        ->assertInertiaFlash('default_password', true);

    $this->assertAuthenticated();
});

it('does not suggest changing a password the user chose', function () {
    User::factory()->create(['name' => 'opendb', 'password' => 'a-password-of-my-own']);

    $this->post('/login', ['name' => 'opendb', 'password' => 'a-password-of-my-own'])
        ->assertRedirect('/')
        ->assertInertiaFlashMissing('default_password');
});

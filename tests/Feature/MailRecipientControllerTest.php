<?php

use App\Models\MailRecipient;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('redirects a guest to the login page', function () {
    $this->post('/mail-recipients', ['email' => 'paul@example.com'])->assertRedirect('/login');
});

it('lists the allowed recipients of the current user only', function () {
    MailRecipient::factory()->for($this->user)->create(['email' => 'paul@example.com']);
    MailRecipient::factory()->create(['email' => 'other@example.com']);

    $this->actingAs($this->user)->getJson('/mail-recipients')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.email', 'paul@example.com');
});

it('adds an allowed recipient', function () {
    $this->actingAs($this->user)->postJson('/mail-recipients', ['email' => 'Paul@Example.com', 'name' => 'Paul'])
        ->assertCreated()
        ->assertJsonPath('data.email', 'paul@example.com');

    expect($this->user->mailRecipients()->sole()->only('email', 'name'))->toBe(['email' => 'paul@example.com', 'name' => 'Paul']);
});

it('refuses an address that is already allowed', function () {
    MailRecipient::factory()->for($this->user)->create(['email' => 'paul@example.com']);

    $this->actingAs($this->user)->postJson('/mail-recipients', ['email' => 'paul@example.com'])
        ->assertJsonValidationErrors(['email' => 'L\'adresse « paul@example.com » fait déjà partie des destinataires autorisés.']);
});

it('validates the address of the recipient', function () {
    $this->actingAs($this->user)->postJson('/mail-recipients', ['email' => 'pas-une-adresse'])
        ->assertJsonValidationErrors(['email']);
});

it('removes an allowed recipient of the current user', function () {
    $recipient = MailRecipient::factory()->for($this->user)->create();

    $this->actingAs($this->user)->deleteJson("/mail-recipients/{$recipient->id}")->assertOk();

    expect($recipient->fresh())->toBeNull();
});

it('does not remove a recipient of another user', function () {
    $recipient = MailRecipient::factory()->create();

    $this->actingAs($this->user)->deleteJson("/mail-recipients/{$recipient->id}")->assertNotFound();

    expect($recipient->fresh())->not->toBeNull();
});

<?php

use App\Models\MailSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->user = User::factory()->create();
});

function smtpPayload(array $overrides = []): array
{
    return [
        'host' => 'smtp.example.com',
        'port' => 587,
        'encryption' => 'tls',
        'username' => 'me@example.com',
        'password' => 'app-password',
        'from_address' => 'me@example.com',
        'from_name' => 'Moi',
        ...$overrides,
    ];
}

it('redirects a guest to the login page', function () {
    $this->getJson('/mail-settings')->assertUnauthorized();
    $this->put('/mail-settings', smtpPayload())->assertRedirect('/login');
});

it('saves the smtp settings of the user with encrypted credentials and never returns the password', function () {
    $response = $this->actingAs($this->user)->putJson('/mail-settings', smtpPayload());

    $response->assertOk()
        ->assertJsonPath('data.host', 'smtp.example.com')
        ->assertJsonPath('data.has_password', true)
        ->assertJsonMissingPath('data.password');

    $row = DB::table('mail_settings')->where('user_id', $this->user->id)->sole();
    expect($row->password)->not->toBe('app-password')
        ->and($row->host)->not->toBe('smtp.example.com')
        ->and($this->user->mailSetting->password)->toBe('app-password');
});

it('keeps the saved password when the password field is left empty', function () {
    MailSetting::factory()->for($this->user)->create(['password' => 'old-password']);

    $this->actingAs($this->user)->putJson('/mail-settings', smtpPayload(['password' => '', 'host' => 'smtp.new.com']))->assertOk();

    expect($this->user->mailSetting()->sole()->only('host', 'password'))->toBe(['host' => 'smtp.new.com', 'password' => 'old-password']);
});

it('returns only the settings of the current user', function () {
    MailSetting::factory()->create();

    $this->actingAs($this->user)->getJson('/mail-settings')->assertOk()->assertExactJson(['data' => null]);
});

it('validates the required smtp settings', function () {
    $this->actingAs($this->user)->putJson('/mail-settings', [])
        ->assertJsonValidationErrors(['host', 'port', 'from_address']);
});

it('reports the error of a connection test that fails', function () {
    $response = $this->actingAs($this->user)->postJson('/mail-settings/test', smtpPayload(['host' => '127.0.0.1', 'port' => 1, 'encryption' => null]));

    $response->assertOk()->assertJsonPath('success', false);
    expect($response->json('message'))->toContain('127.0.0.1');
    expect($this->user->mailSetting()->exists())->toBeFalse();
});

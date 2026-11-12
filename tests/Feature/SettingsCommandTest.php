<?php

use App\Ai\Enums\AgentAccessModeEnum;
use App\Ai\Enums\AgentEffortEnum;
use App\Ai\Enums\SchemaChangeApplyModeEnum;
use App\Enums\MailEncryptionEnum;
use App\Enums\PrivilegeEnum;
use App\Models\MailRecipient;
use App\Models\Provider;
use App\Models\User;
use App\Services\Exporter\ExportCompression;
use App\Services\Exporter\ExportFormat;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\PendingCommand;

beforeEach(function () {
    $this->user = User::factory()->withPrivileges(PrivilegeEnum::ManageProviders)->create();
});

function openSettings(): PendingCommand
{
    return test()->artisan('settings', ['--name' => test()->user->name])
        ->expectsQuestion('Mot de passe', 'password');
}

it('refuses to open the settings with wrong credentials', function () {
    $this->artisan('settings', ['--name' => $this->user->name])
        ->expectsQuestion('Mot de passe', 'wrong-password')
        ->expectsOutputToContain('Identifiants incorrects.')
        ->assertFailed();
});

it('saves how the agent behaves by default', function () {
    openSettings()
        ->expectsQuestion('Paramètres', 'agent')
        ->expectsQuestion('Mode d\'accès par défaut', 'read-write')
        ->expectsQuestion('Effort par défaut', 'high')
        ->expectsQuestion('Changements de structure de l’assistant ERD', 'auto')
        ->expectsQuestion('Étapes par réponse', '20')
        ->expectsQuestion('Lignes lues par requête', '500')
        ->expectsOutputToContain('Les réglages de l\'agent ont été enregistrés.')
        ->expectsQuestion('Paramètres', 'quit')
        ->assertSuccessful();

    expect($this->user->refresh()->settings)
        ->agentAccessMode->toBe(AgentAccessModeEnum::ReadWrite)
        ->agentEffort->toBe(AgentEffortEnum::High)
        ->schemaApplyMode->toBe(SchemaChangeApplyModeEnum::Auto)
        ->agentMaxSteps->toBe(20)
        ->agentMaxRows->toBe(500);
});

it('keeps the settings of the agent when a value is out of bounds', function () {
    openSettings()
        ->expectsQuestion('Paramètres', 'agent')
        ->expectsQuestion('Mode d\'accès par défaut', 'read-write')
        ->expectsQuestion('Effort par défaut', 'default')
        ->expectsQuestion('Changements de structure de l’assistant ERD', 'draft')
        ->expectsQuestion('Étapes par réponse', '99')
        ->expectsQuestion('Lignes lues par requête', '200')
        ->expectsOutputToContain('La valeur de nombre d\'étapes doit être comprise entre 3 et 50.')
        ->doesntExpectOutputToContain('Les réglages de l\'agent ont été enregistrés.')
        ->expectsQuestion('Paramètres', 'quit')
        ->assertSuccessful();

    expect($this->user->refresh()->settings)
        ->agentAccessMode->toBe(AgentAccessModeEnum::ReadOnly)
        ->agentMaxSteps->toBe(15);
});

it('saves the format and the compression of the exports', function () {
    openSettings()
        ->expectsQuestion('Paramètres', 'export')
        ->expectsQuestion('Format', 'csv')
        ->expectsQuestion('Compression', 'gzip')
        ->expectsQuestion('Paramètres', 'quit')
        ->assertSuccessful();

    expect($this->user->refresh()->settings)
        ->exportFormat->toBe(ExportFormat::Csv)
        ->exportCompression->toBe(ExportCompression::Gzip);
});

it('changes the name and the password of the account once the current password is confirmed', function () {
    openSettings()
        ->expectsQuestion('Paramètres', 'account')
        ->expectsQuestion('Nom d\'utilisateur', 'nouveau-nom')
        ->expectsConfirmation('Changer le mot de passe ?', 'yes')
        ->expectsQuestion('Mot de passe actuel', 'password')
        ->expectsQuestion('Nouveau mot de passe', 'nouveau-mot-de-passe')
        ->expectsQuestion('Confirmation', 'nouveau-mot-de-passe')
        ->expectsOutputToContain('Votre compte a été mis à jour.')
        ->expectsQuestion('Paramètres', 'quit')
        ->assertSuccessful();

    $user = $this->user->refresh();
    expect($user->name)->toBe('nouveau-nom')
        ->and(Hash::check('nouveau-mot-de-passe', $user->password))->toBeTrue();
});

it('keeps the account when the current password is wrong', function () {
    openSettings()
        ->expectsQuestion('Paramètres', 'account')
        ->expectsQuestion('Nom d\'utilisateur', 'nouveau-nom')
        ->expectsConfirmation('Changer le mot de passe ?', 'yes')
        ->expectsQuestion('Mot de passe actuel', 'wrong-password')
        ->expectsQuestion('Nouveau mot de passe', 'nouveau-mot-de-passe')
        ->expectsQuestion('Confirmation', 'nouveau-mot-de-passe')
        ->expectsOutputToContain('Le mot de passe est incorrect.')
        ->doesntExpectOutputToContain('Votre compte a été mis à jour.')
        ->expectsQuestion('Paramètres', 'quit')
        ->assertSuccessful();

    $user = $this->user->refresh();
    expect($user->name)->not->toBe('nouveau-nom')
        ->and(Hash::check('password', $user->password))->toBeTrue();
});

it('makes the chosen models the only active ones of a provider', function () {
    Provider::query()->updateOrCreate(['id' => 'anthropic'], [
        'label' => 'Anthropic',
        'api_key' => 'stored-anthropic-key',
        'models' => [
            ['id' => 'claude-opus-5-5', 'label' => 'Claude Opus 5.5', 'active' => true],
            ['id' => 'claude-haiku-5-5', 'label' => 'Claude Haiku 5.5', 'active' => false],
        ],
    ]);

    openSettings()
        ->expectsQuestion('Paramètres', 'providers')
        ->expectsQuestion('Fournisseurs', 'anthropic')
        ->expectsQuestion('Anthropic', 'models')
        ->expectsQuestion('Modèles', ['claude-haiku-5-5'])
        ->expectsOutputToContain('1 modèle est actif.')
        ->expectsQuestion('Anthropic', 'back')
        ->expectsQuestion('Fournisseurs', 'back')
        ->expectsQuestion('Paramètres', 'quit')
        ->assertSuccessful();

    expect(Provider::find('anthropic')->models)->toBe([
        ['id' => 'claude-opus-5-5', 'label' => 'Claude Opus 5.5', 'active' => false],
        ['id' => 'claude-haiku-5-5', 'label' => 'Claude Haiku 5.5', 'active' => true],
    ]);
});

it('saves the SMTP server of the user', function () {
    openSettings()
        ->expectsQuestion('Paramètres', 'mail')
        ->expectsQuestion('Messagerie', 'configure')
        ->expectsQuestion('Chiffrement', 'tls')
        ->expectsQuestion('Hôte', 'smtp.example.com')
        ->expectsQuestion('Port', '465')
        ->expectsQuestion('Nom d\'utilisateur', 'mailer')
        ->expectsQuestion('Mot de passe', 'smtp-secret')
        ->expectsQuestion('Adresse d\'expéditeur', 'moi@example.com')
        ->expectsQuestion('Nom d\'expéditeur', '')
        ->expectsOutputToContain('La configuration SMTP a été enregistrée.')
        ->expectsQuestion('Messagerie', 'back')
        ->expectsQuestion('Paramètres', 'quit')
        ->assertSuccessful();

    expect($this->user->mailSetting()->first())
        ->host->toBe('smtp.example.com')
        ->port->toBe(465)
        ->encryption->toBe(MailEncryptionEnum::Tls)
        ->password->toBe('smtp-secret')
        ->from_name->toBeNull();
});

it('adds and removes the allowed recipients of the mails', function () {
    MailRecipient::factory()->for($this->user)->create(['email' => 'ancien@example.com']);

    openSettings()
        ->expectsQuestion('Paramètres', 'mail')
        ->expectsQuestion('Messagerie', 'recipients')
        ->expectsQuestion('Destinataires autorisés', 'add')
        ->expectsQuestion('Adresse', 'Alice@Example.com')
        ->expectsQuestion('Nom', 'Alice')
        ->expectsQuestion('Destinataires autorisés', 'remove')
        ->expectsQuestion('Retirer un destinataire', 'ancien@example.com')
        ->expectsQuestion('Destinataires autorisés', 'back')
        ->expectsQuestion('Messagerie', 'back')
        ->expectsQuestion('Paramètres', 'quit')
        ->assertSuccessful();

    expect($this->user->mailRecipients()->pluck('name', 'email')->all())->toBe(['alice@example.com' => 'Alice']);
});

it('refuses a recipient already allowed', function () {
    MailRecipient::factory()->for($this->user)->create(['email' => 'alice@example.com']);

    openSettings()
        ->expectsQuestion('Paramètres', 'mail')
        ->expectsQuestion('Messagerie', 'recipients')
        ->expectsQuestion('Destinataires autorisés', 'add')
        ->expectsQuestion('Adresse', 'alice@example.com')
        ->expectsQuestion('Nom', '')
        ->expectsOutputToContain('L\'adresse « alice@example.com » fait déjà partie des destinataires autorisés.')
        ->expectsQuestion('Destinataires autorisés', 'back')
        ->expectsQuestion('Messagerie', 'back')
        ->expectsQuestion('Paramètres', 'quit')
        ->assertSuccessful();

    expect($this->user->mailRecipients()->count())->toBe(1);
});

it('clears the cache once confirmed', function () {
    openSettings()
        ->expectsQuestion('Paramètres', 'about')
        ->expectsQuestion('À propos', 'clear_cache')
        ->expectsConfirmation('Vider le cache', 'yes')
        ->expectsOutputToContain('Le cache a été vidé : la liste des modèles sera relue.')
        ->expectsQuestion('À propos', 'back')
        ->expectsQuestion('Paramètres', 'quit')
        ->assertSuccessful();
});

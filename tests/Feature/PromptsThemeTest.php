<?php

use App\Console\Language\Prompts\ConfirmPromptRenderer;
use App\Console\Language\Prompts\MultiSelectPromptRenderer;
use App\Console\Language\Prompts\TextareaPromptRenderer;
use App\Console\Language\Prompts\TextPromptRenderer;
use Laravel\Prompts\ConfirmPrompt;
use Laravel\Prompts\MultiSelectPrompt;
use Laravel\Prompts\TextareaPrompt;
use Laravel\Prompts\TextPrompt;

it('answers yes or no in the language of the console, unless the call names its own answers', function () {
    $translated = new ConfirmPrompt('Supprimer ?');
    $named = new ConfirmPrompt('Supprimer ?', yes: 'Supprimer', no: 'Garder');

    expect((new ConfirmPromptRenderer($translated))($translated))->toContain('Oui')->toContain('Non')
        ->and((new ConfirmPromptRenderer($named))($named))->toContain('Supprimer')->toContain('Garder');
});

it('writes the messages of a prompt in the language of the console', function () {
    $required = new TextPrompt('Nom', required: true);
    $required->state = 'error';
    $required->error = 'Required.';

    $cancelled = new TextPrompt('Nom');
    $cancelled->state = 'cancel';

    expect((new TextPromptRenderer($required))($required))->toContain('Obligatoire.')->not->toContain('Required.')
        ->and((new TextPromptRenderer($cancelled))($cancelled))->toContain('Annulé.');
});

it('writes the texts drawn by the renderers in the language of the console', function () {
    $roles = new MultiSelectPrompt('Rôles', ['admin' => 'Admin', 'reader' => 'Lecteur', 'writer' => 'Rédacteur'], scroll: 2);
    $note = new TextareaPrompt('Note');
    $submittedRoles = new MultiSelectPrompt('Rôles', ['admin' => 'Admin']);
    $submittedRoles->state = 'submit';

    expect((new MultiSelectPromptRenderer($roles))($roles))->toContain('0 sélectionné')
        ->and((new MultiSelectPromptRenderer($submittedRoles))($submittedRoles))->toContain('Aucun')
        ->and((new TextareaPromptRenderer($note))($note))->toContain('Ctrl+D pour valider');
});

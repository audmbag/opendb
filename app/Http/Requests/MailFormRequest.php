<?php

namespace App\Http\Requests;

use App\Services\Mailer\RecipientGuard;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class MailFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'to' => ['required', 'array', 'min:1', 'max:'.RecipientGuard::MAX_RECIPIENTS],
            'to.*' => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['required', 'string', 'max:1024'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'to' => 'destinataires',
            'to.*' => 'destinataire',
            'subject' => 'objet',
            'body' => 'message',
            'attachments' => 'pièces jointes',
        ];
    }
}

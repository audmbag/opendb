<?php

namespace App\Http\Requests\Project;

use App\Services\SystemInfoService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CloningLogsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'path' => ['required', 'string', 'ends_with:.jsonl'],
        ];
    }

    /**
     * Chemin réel du journal demandé, uniquement s'il s'agit d'un fichier du dossier de logs de l'application.
     * Renvoie null pour un fichier inexistant ou situé en dehors de ~/.{app}/logs.
     */
    public function logFile(SystemInfoService $systemInfoService): ?string
    {
        $logsDirectory = realpath($systemInfoService->logDirectory()->path(''));
        $file = realpath($this->validated('path'));

        if ($logsDirectory === false || $file === false || ! is_file($file)) {
            return null;
        }

        return str_starts_with($file, $logsDirectory.DIRECTORY_SEPARATOR) ? $file : null;
    }
}

<?php

namespace App\Http\Requests;

use App\Ai\Enums\AgentAccessModeEnum;
use App\Ai\Enums\AgentEffortEnum;
use App\Http\Requests\Concerns\SelectsAiModel;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConversationMessageRequest extends FormRequest
{
    use SelectsAiModel;

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
     * The body is the one sent by the AI SDK useChat hook, read by Vercel::chat().
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'messages' => ['required', 'array', 'min:1'],
            'messages.*.id' => ['required', 'string'],
            'messages.*.role' => ['required', Rule::in(['system', 'user', 'assistant'])],
            'messages.*.parts' => ['present', 'array'],
            'access_mode' => ['sometimes', Rule::enum(AgentAccessModeEnum::class)],
            'effort' => ['sometimes', Rule::enum(AgentEffortEnum::class)],
            ...$this->aiModelRules(),
        ];
    }

    /**
     * The tools of the agent stay read-only unless the request explicitly asks for writes.
     */
    public function accessMode(): AgentAccessModeEnum
    {
        return $this->enum('access_mode', AgentAccessModeEnum::class) ?? AgentAccessModeEnum::ReadOnly;
    }

    public function effort(): AgentEffortEnum
    {
        return $this->enum('effort', AgentEffortEnum::class) ?? AgentEffortEnum::Default;
    }
}

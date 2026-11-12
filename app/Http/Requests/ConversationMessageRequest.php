<?php

namespace App\Http\Requests;

use App\Ai\Data\AgentSettings;
use App\Ai\Enums\AgentAccessModeEnum;
use App\Ai\Enums\AgentEffortEnum;
use App\Http\Requests\Concerns\SelectsAiModel;
use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConversationMessageRequest extends FormRequest
{
    use SelectsAiModel;

    private ?Project $conversationProject = null;

    /**
     * The agent acts with the privileges of the user, on the tables they hold some on: talking to it only needs to still reach
     * the project of the conversation.
     */
    public function authorize(): bool
    {
        return $this->project() !== null && $this->user()->can('view', $this->project());
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
            'trigger' => ['sometimes', Rule::in(['submit-message', 'regenerate-message'])],
            'access_mode' => ['sometimes', Rule::enum(AgentAccessModeEnum::class)],
            'effort' => ['sometimes', Rule::enum(AgentEffortEnum::class)],
            ...$this->aiModelRules(),
        ];
    }

    /**
     * The tools of the agent stay read-only unless the request explicitly asks for writes, and the user holds a privilege
     * that changes the database: the agent never does more than them.
     */
    public function accessMode(): AgentAccessModeEnum
    {
        $mode = $this->enum('access_mode', AgentAccessModeEnum::class) ?? AgentAccessModeEnum::ReadOnly;

        return $mode->allowsWrites() && ! $this->user()->can('writeWithAgent', $this->project())
            ? AgentAccessModeEnum::ReadOnly
            : $mode;
    }

    private function project(): ?Project
    {
        return $this->conversationProject ??= Project::find($this->route('conversation')?->project_id);
    }

    public function effort(): AgentEffortEnum
    {
        return $this->enum('effort', AgentEffortEnum::class) ?? AgentEffortEnum::Default;
    }

    /**
     * The model, the access mode and the effort the user chose for this answer.
     */
    public function agentSettings(): AgentSettings
    {
        return new AgentSettings($this->selectedProvider(), $this->selectedModel(), $this->accessMode(), $this->effort());
    }

    /**
     * Whether the user asks the agent to answer their last message again, instead of sending a new one.
     */
    public function regeneratesAnswer(): bool
    {
        return $this->input('trigger') === 'regenerate-message';
    }

    /**
     * The number of messages the user has sent in the conversation, including the one being answered.
     */
    public function userMessagesCount(): int
    {
        return collect($this->input('messages', []))->where('role', 'user')->count();
    }
}

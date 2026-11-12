<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Laravel\Ai\Models\Conversation;

/**
 * The conversations of the SDK belong to the user who had them, whatever their project.
 */
class ConversationPolicy
{
    /**
     * Only the user who had a conversation may open it or go on with it: for anyone else, it does not exist.
     */
    public function view(User $user, Conversation $conversation): Response
    {
        return $this->update($user, $conversation) ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * Only the user who had a conversation may restore or delete it.
     */
    public function update(User $user, Conversation $conversation): bool
    {
        return $conversation->participant_type === $user->getMorphClass()
            && (string) $conversation->participant_id === (string) $user->getKey();
    }
}

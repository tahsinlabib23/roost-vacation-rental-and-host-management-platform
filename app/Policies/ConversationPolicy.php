<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    public function view(User $user, Conversation $conversation): bool
    {
        return $this->isStoredParticipantWithCurrentRole($user, $conversation);
    }

    public function poll(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }

    public function send(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation)
            && $conversation->property()->where('host_id', $conversation->host_id)->exists();
    }

    public function markRead(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }

    private function isStoredParticipantWithCurrentRole(User $user, Conversation $conversation): bool
    {
        return match ($user->role) {
            'guest' => $conversation->guest_id === $user->id,
            'host' => $conversation->host_id === $user->id,
            default => false,
        };
    }
}

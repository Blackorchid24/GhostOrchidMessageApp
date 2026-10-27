<?php

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('conversation.{conversationId}', function (
    User $user,
    int $conversationId
) {
    return Conversation::query()
        ->whereKey($conversationId)
        ->whereHas('participants', function ($query) use ($user) {
            $query->where('users.id', $user->id);
        })
        ->exists();
});

Broadcast::channel('user.{userId}.inbox', function (
    User $user,
    int $userId
) {
    return (int) $user->id === $userId;
});

Broadcast::channel('presence-chat', function (User $user) {
    if (($user->status ?? 'active') !== 'active') {
        return false;
    }

    return [
        'id' => $user->id,
        'name' => $user->name,
        'avatar' => $user->avatar
            ?: ($user->profile_picture ?? null),
    ];
});

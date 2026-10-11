<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('chat', function ($user) {
    return auth()->user();
});

// Private chat conversation channels
Broadcast::channel('chat.{conversationId}', function ($user, $conversationId) {
    // Check if user is a participant in this conversation
    $conversation = \Modules\Chat\Entities\ChatConversation::find($conversationId);
    if (!$conversation) {
        return false;
    }
    
    return $conversation->participants()->where('user_id', $user->id)->exists();
});



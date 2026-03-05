<?php

use App\Models\Lecture;
use App\Models\Student;
use App\Models\User;
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

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('notifications.{id}', function ($user, $id) {
    // Only allow the user to listen to their own notifications
    return (int) $user->id === (int) $id;
});

Broadcast::channel('notify.{campusId}.{userId}', function ($actor, $campusId, $userId) {
    $canonicalUserId = match (true) {
        $actor instanceof User => (int) $actor->id,
        $actor instanceof Student => (int) $actor->user_id,
        $actor instanceof Lecture => (int) $actor->user_id,
        default => 0,
    };

    if ($canonicalUserId <= 0 || $canonicalUserId !== (int) $userId) {
        return false;
    }

    if ((int) $campusId === 0) {
        return true;
    }

    return match (true) {
        $actor instanceof User => $actor->campusUserRoles()->where('campus_id', (int) $campusId)->exists(),
        $actor instanceof Student => (int) $actor->campus_id === (int) $campusId,
        $actor instanceof Lecture => (int) $actor->campus_id === (int) $campusId,
        default => false,
    };
});

Broadcast::channel('users.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

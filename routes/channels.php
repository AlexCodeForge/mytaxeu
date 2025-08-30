<?php

declare(strict_types=1);

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

// User-specific job updates - users can only listen to their own job updates
Broadcast::channel('user.{userId}.jobs', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Admin job monitoring - only admins can listen to all job updates
Broadcast::channel('admin.jobs', function ($user) {
    return $user->isAdmin();
});

// Admin job log monitoring - only admins can listen to job logs
Broadcast::channel('admin.job-logs', function ($user) {
    return $user->isAdmin();
});

<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled work
|--------------------------------------------------------------------------
|
| Forge runs the scheduler every minute. Enable it when provisioning — the
| quote expiry below is not decoration; an unexpired quote outliving a price
| change is a direct margin loss.
|
*/

Schedule::command('documents:expire')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

// Housekeeping so failed_jobs and the queue tables do not grow forever.
Schedule::command('queue:prune-failed --hours=336')->daily();

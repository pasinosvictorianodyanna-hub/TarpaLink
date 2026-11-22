<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Subscription billing.
 *
 * Daily, not monthly: a monthly schedule gets one chance to run, and a machine
 * that is asleep on the 1st leaves every shop unbilled with nothing to say so.
 * The command is idempotent — it asks whether the current period already has an
 * invoice — so running it daily costs one query per active subscription and
 * issues each period exactly once.
 *
 * 01:10 rather than midnight: shops in the Philippines close late, and an
 * invoice appearing mid-evening reads as a live change.
 */
Schedule::command('billing:issue')->dailyAt('01:10')->withoutOverlapping();

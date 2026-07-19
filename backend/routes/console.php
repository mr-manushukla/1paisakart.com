<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Non-winners who never chose get their advance moved to wallet after the window.
// Needs a cron on the host, e.g. cPanel → Cron Jobs, every hour:
//   /opt/cpanel/ea-php84/root/usr/bin/php /home/<user>/onepaisakart/artisan schedule:run >/dev/null 2>&1
Schedule::command('draws:auto-credit')->hourly();

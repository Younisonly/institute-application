<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('backup:database')->dailyAt('02:00');
Schedule::command('billing:process-monthly')->monthlyOn(1, '00:00');
Schedule::command('model:prune', ['--model' => [\App\Models\AuditLog::class]])->daily();
Schedule::command('finance:audit')->weeklyOn(1, '06:00');
Schedule::command('batches:auto-close')->dailyAt('00:05');


Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

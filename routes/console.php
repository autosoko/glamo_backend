<?php

use App\Services\SuspendedOrderService;
use App\Services\EngagementReminderService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('orders:process-suspended', function (SuspendedOrderService $service) {
    $processed = $service->processDueSchedules();
    $this->info('Processed suspended orders: ' . $processed);
})->purpose('Process suspended orders that reached their scheduled time and notify parties.');

Artisan::command('notifications:run-reminders {campaign=all}', function (EngagementReminderService $service, string $campaign) {
    $results = $service->run($campaign);
    $this->info(json_encode($results, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
})->purpose('Dispatches scheduled push/database reminders (debt, no-booking, last-booking).');

Schedule::command('orders:process-suspended')->everyMinute();
Schedule::command('notifications:run-reminders provider_debt')->hourly();
Schedule::command('notifications:run-reminders client_no_booking')->everyTwoHours();
Schedule::command('notifications:run-reminders client_last_booking_4_days')->hourly();

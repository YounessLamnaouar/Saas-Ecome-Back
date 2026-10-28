<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('orders:retry-store-emails', function () {
    $ids = \Illuminate\Support\Facades\DB::table('order_email_deliveries')->whereNull('sent_at')
        ->where(function ($query) { $query->whereNull('attempted_at')->orWhere('attempted_at', '<=', now()->subMinutes(5)); })
        ->orderBy('id')->limit(25)->pluck('order_id');
    foreach ($ids as $id) app(\App\Services\StoreOrderEmail::class)->send($id);
    $this->info('Processed '.$ids->count().' pending store order emails.');
})->purpose('Retry unsent store order notifications');
\Illuminate\Support\Facades\Schedule::command('orders:retry-store-emails')->everyFiveMinutes()->withoutOverlapping();

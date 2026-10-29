<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Política "Se 48 h sem resposta": cancela OS sem aprovação no prazo e recusa reparos adicionais vencidos.
Schedule::command('service-orders:expire-approvals')->everyFifteenMinutes()->withoutOverlapping();

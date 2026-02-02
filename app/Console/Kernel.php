<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        \App\Console\Commands\ExampleCommand::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('example:run')
            ->dailyAt('01:00')
            ->withoutOverlapping()
            ->onOneServer()
            ->timezone(config('app.timezone', 'WIB'));

        if ($this->app->environment('local')) {
            $schedule->command('example:run')
                ->everyMinute()
                ->withoutOverlapping()
                ->onOneServer();
        }
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        if (file_exists(base_path('routes/console.php'))) {
            require base_path('routes/console.php');
        }
    }
}
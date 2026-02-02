<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array<int, class-string>
     */
    protected $commands = [
        \App\Console\Commands\ExampleCommand::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule): void
    {
        // Production schedule: run daily at 01:00 server time
        $schedule->command('example:run')
            ->dailyAt('01:00')
            ->withoutOverlapping()
            ->onOneServer()
            ->timezone(config('app.timezone', 'WIB'));

        // Development convenience: run every minute when in local environment
        if ($this->app->environment('local')) {
            $schedule->command('example:run')
                ->everyMinute()
                ->withoutOverlapping()
                ->onOneServer();
        }
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        if (file_exists(base_path('routes/console.php'))) {
            require base_path('routes/console.php');
        }
    }
}

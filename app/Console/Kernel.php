<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * Tambahkan class command kustom di sini jika Anda membuat
     * file di app/Console/Commands, misal:
     * \App\Console\Commands\ExampleCommand::class,
     *
     * @var array<int, class-string>
     */
    protected $commands = [
        \App\Console\Commands\ExampleCommand::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * Contoh:
     * $schedule->command('example:run')->dailyAt('01:00');
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Contoh jadwal aktif: jalankan command setiap hari jam 01:00
        // $schedule->command('example:run')->dailyAt('01:00');

        // Contoh jadwal pengembangan: jalankan setiap menit (untuk testing)
        // $schedule->command('example:run')->everyMinute();
    }

    /**
     * Register the commands for the application.
     *
     * Pastikan file routes/console.php ada (default Laravel).
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        if (file_exists(base_path('routes/console.php'))) {
            require base_path('routes/console.php');
        }
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ExampleCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'example:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Contoh command sederhana untuk demonstrasi scheduling';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Contoh aksi: tulis log atau tampilkan pesan
        \Log::info('ExampleCommand dijalankan oleh scheduler.');
        $this->info('ExampleCommand executed.');

        return 0;
    }
}

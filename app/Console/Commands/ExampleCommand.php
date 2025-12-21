<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

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
    public function handle(): int
    {
        Log::info('ExampleCommand dijalankan oleh scheduler.');
        $this->info('ExampleCommand executed.');

        return Command::SUCCESS;
    }
}

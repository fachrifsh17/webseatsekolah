<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExampleCommand extends Command
{
    protected $signature = 'example:run {--force : Run even if conditions would normally prevent it}';
    protected $description = 'Contoh command sederhana untuk demonstrasi scheduling';

    public function handle(): int
    {
        try {
            $context = [
                'command' => $this->getName(),
                'host' => gethostname(),
            ];

            Log::info('ExampleCommand started', $context);

            $this->line('<fg=blue;options=bold>ExampleCommand executed.</>');

            Log::info('ExampleCommand finished successfully', $context);

            return parent::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('ExampleCommand failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'command' => $this->getName(),
            ]);

            $this->error('ExampleCommand failed. Check logs for details.');

            return parent::FAILURE;
        }
    }
}

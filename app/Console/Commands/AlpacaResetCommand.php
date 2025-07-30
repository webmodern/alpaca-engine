<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class AlpacaResetCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'alpaca:reset';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset full cache.';

    /**
     * Execute the console command.
     */
    public function handle() : int
    {
        $this->callSilent('cache:clear');
        $this->callSilent('route:clear');
        $this->callSilent('view:clear');
        $this->callSilent('config:clear');
        $this->callSilent('event:clear');
        $this->callSilent('event:cache');
        $this->callSilent('optimize:clear');

        $this->callSilent('queue:flush');
        $this->callSilent('queue:restart');

        $logPath = storage_path('logs/laravel.log');
        if (File::exists($logPath)) {
            File::put($logPath, '');
            $this->info('Logs cleared.');
        }

        if (file_exists(base_path('composer.phar')) || file_exists(base_path('vendor/autoload.php'))) {
            exec('composer dump-autoload -o');
            $this->info('Composer autoload обновлён.');
        }

        if (class_exists(\Filament\Facades\Filament::class)) {
            $this->callSilent('filament:cache');
            $this->callSilent('filament:assets');
            $this->callSilent('filament:optimize-clear');
            $this->info('Filamentphp cache and assets have been reset.');
        }

        $this->info('All caches, logs and queues have been reset. The Alpaca Engine project is ready to work!');

        return self::SUCCESS;
    }
}

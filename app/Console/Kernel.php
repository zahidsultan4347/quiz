<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule)
    {
        // Auto-save synchronization
        $schedule->call(function () {
            \App\Jobs\SyncQuizAttempts::dispatch();
        })->everyMinute();
        
        // Clean up old auto-saves
        $schedule->command('quiz:cleanup-autosaves')->daily();
        
        // Handle abandoned attempts
        $schedule->command('quiz:check-timeouts')->everyFiveMinutes();
        
        // Sync grades with Moodle
        $schedule->command('quiz:sync-grades')->hourly();
    }
    
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');
        
        require base_path('routes/console.php');
    }
}
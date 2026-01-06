<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\QuizAttempt;
use App\Services\Moodle\MoodleService;
use Illuminate\Support\Facades\Log;

class SyncQuizAttempts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public function handle()
    {
        $attempts = QuizAttempt::where('status', 'in_progress')
            ->where('last_sync_at', '<', now()->subMinutes(5))
            ->get();
        
        foreach ($attempts as $attempt) {
            try {
                // Auto-save to Moodle if needed
                if ($attempt->answers) {
                    // This would sync answers to Moodle's backup system
                    // Implementation depends on Moodle's web services
                }
                
                $attempt->update(['last_sync_at' => now()]);
                
            } catch (\Exception $e) {
                Log::error('Failed to sync attempt:', [
                    'attempt_id' => $attempt->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
}
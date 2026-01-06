<?php

namespace App\Services\Quiz;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\QuizAttempt;

class QuizTimerService
{
    private $cachePrefix = 'quiz_timer_';
    private $disconnectionTimeout = 300; // 5 minutes
    
    public function startTimer($attemptId, $minutes)
    {
        $seconds = $minutes * 60;
        $key = $this->cachePrefix . $attemptId;
        
        Cache::put($key, [
            'started_at' => now()->timestamp,
            'remaining' => $seconds,
            'is_paused' => false,
            'last_activity' => now()->timestamp
        ], now()->addHours(2));
        
        Log::info('Timer started', ['attempt_id' => $attemptId, 'minutes' => $minutes]);
    }
    
    public function getRemainingTime($attemptId)
    {
        $key = $this->cachePrefix . $attemptId;
        $timer = Cache::get($key);
        
        if (!$timer) {
            // Fallback to database
            $attempt = QuizAttempt::find($attemptId);
            if ($attempt && $attempt->time_remaining_seconds) {
                return $attempt->time_remaining_seconds;
            }
            return 0;
        }
        
        // Calculate remaining time
        if ($timer['is_paused']) {
            return $timer['remaining'];
        }
        
        $elapsed = now()->timestamp - $timer['started_at'];
        $remaining = $timer['remaining'] - $elapsed;
        
        return max(0, $remaining);
    }
    
    public function updateRemainingTime($attemptId, $remaining)
    {
        $key = $this->cachePrefix . $attemptId;
        $timer = Cache::get($key);
        
        if ($timer) {
            $timer['remaining'] = $remaining;
            $timer['last_activity'] = now()->timestamp;
            Cache::put($key, $timer, now()->addHours(2));
        }
    }
    
    public function pauseTimer($attemptId)
    {
        $remaining = $this->getRemainingTime($attemptId);
        $key = $this->cachePrefix . $attemptId;
        
        Cache::put($key, [
            'started_at' => now()->timestamp,
            'remaining' => $remaining,
            'is_paused' => true,
            'last_activity' => now()->timestamp
        ], now()->addHours(2));
        
        Log::info('Timer paused', ['attempt_id' => $attemptId, 'remaining' => $remaining]);
    }
    
    public function resumeTimer($attemptId)
    {
        $key = $this->cachePrefix . $attemptId;
        $timer = Cache::get($key);
        
        if (!$timer) {
            throw new \Exception('Timer not found');
        }
        
        $timer['is_paused'] = false;
        $timer['started_at'] = now()->timestamp;
        $timer['last_activity'] = now()->timestamp;
        
        Cache::put($key, $timer, now()->addHours(2));
        
        Log::info('Timer resumed', ['attempt_id' => $attemptId, 'remaining' => $timer['remaining']]);
        
        return $timer['remaining'];
    }
    
    public function stopTimer($attemptId)
    {
        Cache::forget($this->cachePrefix . $attemptId);
        Log::info('Timer stopped', ['attempt_id' => $attemptId]);
    }
    
    public function handleDisconnection($attemptId)
    {
        $remaining = $this->getRemainingTime($attemptId);
        $this->pauseTimer($attemptId);
        
        // Update attempt status
        QuizAttempt::where('id', $attemptId)->update([
            'is_disconnected' => true,
            'time_remaining_seconds' => $remaining
        ]);
        
        Log::warning('Quiz disconnected', ['attempt_id' => $attemptId]);
    }
    
    public function checkForTimeouts()
    {
        // This would be run by a scheduled task
        // to handle abandoned quizzes
    }
}
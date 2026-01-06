<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quiz extends Model
{
    use HasFactory;

    protected $fillable = [
        'moodle_quiz_id',
        'moodle_course_id',
        'name',
        'description',
        'time_limit_minutes',
        'max_grade',
        'attempts_allowed',
        'available_from',
        'available_to',
        'is_active',
        'questions_data'
    ];

    protected $casts = [
        'available_from' => 'datetime',
        'available_to' => 'datetime',
        'is_active' => 'boolean',
        'questions_data' => 'array',
        'max_grade' => 'float',
    ];

    // Relationships
    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class);
    }

    // Methods
    public function isAvailable()
    {
        if (!$this->is_active) {
            return false;
        }

        $now = now();
        
        if ($this->available_from && $now->lt($this->available_from)) {
            return false;
        }

        if ($this->available_to && $now->gt($this->available_to)) {
            return false;
        }

        return true;
    }

    public function getUserAttemptsCount($userId)
    {
        return $this->attempts()
            ->where('user_id', $userId)
            ->whereIn('status', ['submitted', 'time_up'])
            ->count();
    }

    public function canUserAttempt($userId)
    {
        if (!$this->isAvailable()) {
            return false;
        }

        $attemptsCount = $this->getUserAttemptsCount($userId);
        
        return $attemptsCount < $this->attempts_allowed;
    }

    public function getUserActiveAttempt($userId)
    {
        return $this->attempts()
            ->where('user_id', $userId)
            ->whereIn('status', ['in_progress', 'needs_grading'])
            ->first();
    }

    public function getQuestionsFromData()
    {
        if (!$this->questions_data) {
            return [];
        }

        return $this->questions_data;
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizAutoSave extends Model
{
    use HasFactory;

    protected $fillable = [
        'quiz_attempt_id',
        'answers_snapshot',
        'time_remaining',
        'current_question_index',
        'session_id',
        'ip_address'
    ];

    protected $casts = [
        'answers_snapshot' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Relationships
    public function quizAttempt(): BelongsTo
    {
        return $this->belongsTo(QuizAttempt::class);
    }

    // Methods
    public function getAnswerForQuestion($questionId)
    {
        $answers = $this->answers_snapshot ?? [];
        return $answers[$questionId] ?? null;
    }

    public function getQuestionAnswers()
    {
        return $this->answers_snapshot ?? [];
    }

    // Scopes
    public function scopeRecent($query, $minutes = 5)
    {
        return $query->where('created_at', '>=', now()->subMinutes($minutes));
    }

    public function scopeBySession($query, $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }
}
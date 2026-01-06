<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class QuizAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'quiz_id',
        'moodle_attempt_id',
        'moodle_quiz_id',
        'moodle_course_id',
        'moodle_cmid',
        'status',
        'questions',
        'answers',
        'grade',
        'max_grade',
        'started_at',
        'ended_at',
        'time_limit_minutes',
        'time_remaining_seconds',
        'is_disconnected',
        'last_sync_at'
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'last_sync_at' => 'datetime',
        'questions' => 'array',
        'answers' => 'array',
        'grade' => 'float',
        'max_grade' => 'float',
        'is_disconnected' => 'boolean'
    ];

    protected $appends = [
        'time_spent',
        'progress_percentage',
        'is_active',
        'ends_at',
        'time_remaining'
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(QuizAnswer::class);
    }

    public function autoSaves(): HasMany
    {
        return $this->hasMany(QuizAutoSave::class);
    }

    public function latestAutoSave(): HasMany
    {
        return $this->hasMany(QuizAutoSave::class)->latest()->limit(1);
    }

    // Accessors
    public function getTimeSpentAttribute()
    {
        if (!$this->started_at) {
            return 0;
        }

        $endTime = $this->ended_at ?? now();
        return $endTime->diffInSeconds($this->started_at);
    }

    public function getProgressPercentageAttribute()
    {
        if (!$this->questions) {
            return 0;
        }

        $totalQuestions = count($this->questions);
        if ($totalQuestions === 0) {
            return 0;
        }

        $answeredQuestions = $this->answers ? count($this->answers) : 0;
        return ($answeredQuestions / $totalQuestions) * 100;
    }

    public function getIsActiveAttribute()
    {
        return in_array($this->status, ['in_progress', 'needs_grading']);
    }

    public function getEndsAtAttribute()
    {
        if (!$this->started_at) {
            return null;
        }

        return $this->started_at->copy()->addMinutes($this->time_limit_minutes);
    }

    public function getTimeRemainingAttribute()
    {
        if ($this->status !== 'in_progress') {
            return 0;
        }

        if ($this->time_remaining_seconds) {
            return $this->time_remaining_seconds;
        }

        $endsAt = $this->ends_at;
        if (!$endsAt) {
            return 0;
        }

        return max(0, now()->diffInSeconds($endsAt, false));
    }

    // Methods
    public function canResume()
    {
        if (!in_array($this->status, ['in_progress', 'needs_grading'])) {
            return false;
        }

        if ($this->isTimeUp()) {
            return false;
        }

        return true;
    }

    public function isTimeUp()
    {
        $endsAt = $this->ends_at;
        if (!$endsAt) {
            return false;
        }

        return now()->greaterThan($endsAt);
    }

    public function markAsDisconnected()
    {
        $this->update([
            'is_disconnected' => true,
            'time_remaining_seconds' => $this->time_remaining,
            'status' => 'needs_grading'
        ]);
    }

    public function resume()
    {
        $this->update([
            'is_disconnected' => false,
            'status' => 'in_progress'
        ]);
    }

    public function submit(array $finalAnswers = null)
    {
        if ($finalAnswers) {
            $this->answers = $finalAnswers;
        }

        $this->update([
            'status' => 'submitted',
            'ended_at' => now(),
            'time_remaining_seconds' => 0,
            'is_disconnected' => false
        ]);
    }

    public function abandon()
    {
        $this->update([
            'status' => 'abandoned',
            'ended_at' => now(),
            'is_disconnected' => false
        ]);
    }

    public function markTimeUp()
    {
        $this->update([
            'status' => 'time_up',
            'ended_at' => now(),
            'time_remaining_seconds' => 0,
            'is_disconnected' => false
        ]);
    }

    public function updateGrade($grade)
    {
        $this->update([
            'grade' => min($grade, $this->max_grade),
            'last_sync_at' => now()
        ]);
    }

    public function getAnswerForQuestion($questionId)
    {
        $answers = $this->answers ?? [];
        return $answers[$questionId] ?? null;
    }

    public function saveAnswerToDb($questionId, $answerData)
    {
        // Save to quiz_answers table
        $question = QuizQuestion::where('moodle_question_id', $questionId)
            ->where('quiz_id', $this->quiz_id)
            ->first();

        if ($question) {
            QuizAnswer::updateOrCreate(
                [
                    'quiz_attempt_id' => $this->id,
                    'quiz_question_id' => $question->id
                ],
                [
                    'answer_text' => is_string($answerData) ? $answerData : null,
                    'answer_data' => is_array($answerData) ? $answerData : null,
                    'sequence_number' => $answerData['sequence_number'] ?? 0,
                    'time_spent_seconds' => $answerData['time_spent'] ?? 0
                ]
            );
        }
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['in_progress', 'needs_grading']);
    }

    public function scopeCompleted($query)
    {
        return $query->whereIn('status', ['submitted', 'time_up']);
    }

    public function scopeByQuiz($query, $quizId)
    {
        return $query->where('quiz_id', $quizId);
    }

    public function scopeByMoodleQuiz($query, $moodleQuizId)
    {
        return $query->where('moodle_quiz_id', $moodleQuizId);
    }

    public function scopeByCourse($query, $courseId)
    {
        return $query->where('moodle_course_id', $courseId);
    }

    public function scopeNeedSync($query)
    {
        return $query->where('status', 'in_progress')
            ->where(function ($q) {
                $q->whereNull('last_sync_at')
                  ->orWhere('last_sync_at', '<', now()->subMinutes(5));
            });
    }
}
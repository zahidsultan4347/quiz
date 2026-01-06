<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'quiz_attempt_id',
        'quiz_question_id',
        'answer_text',
        'answer_data',
        'grade',
        'feedback',
        'time_spent_seconds',
        'sequence_number',
        'is_correct',
        'is_graded'
    ];

    protected $casts = [
        'answer_data' => 'array',
        'grade' => 'float',
        'is_correct' => 'boolean',
        'is_graded' => 'boolean'
    ];

    // Relationships
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(QuizAttempt::class, 'quiz_attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(QuizQuestion::class);
    }

    // Methods
    public function getAnswerValue()
    {
        if ($this->answer_text) {
            return $this->answer_text;
        }

        if ($this->answer_data) {
            return $this->answer_data;
        }

        return null;
    }

    public function autoGrade()
    {
        if ($this->is_graded) {
            return $this->grade;
        }

        $question = $this->question;
        if (!$question) {
            return null;
        }

        $userAnswer = $this->getAnswerValue();
        $grade = $question->calculateGrade($userAnswer);

        if ($grade !== null) {
            $this->update([
                'grade' => $grade,
                'is_correct' => $grade > 0,
                'is_graded' => true
            ]);
        }

        return $grade;
    }
}
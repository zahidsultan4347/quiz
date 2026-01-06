<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'quiz_id',
        'moodle_question_id',
        'type',
        'question_text',
        'default_grade',
        'answers',
        'correct_answer',
        'feedback',
        'sequence_number',
        'is_active'
    ];

    protected $casts = [
        'answers' => 'array',
        'default_grade' => 'float',
        'is_active' => 'boolean'
    ];

    // Relationships
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function questionAnswers(): HasMany
    {
        return $this->hasMany(QuizAnswer::class);
    }

    // Methods
    public function getAnswerOptions()
    {
        if (!$this->answers) {
            return [];
        }

        $options = [];
        foreach ($this->answers as $key => $answer) {
            $options[$key] = [
                'text' => $answer['text'] ?? $answer,
                'fraction' => $answer['fraction'] ?? 0,
                'feedback' => $answer['feedback'] ?? null
            ];
        }

        return $options;
    }

    public function isCorrectAnswer($userAnswer)
    {
        if (!$this->correct_answer) {
            return null; // Can't auto-grade
        }

        if ($this->type === 'multiplechoice' || $this->type === 'truefalse') {
            return $userAnswer == $this->correct_answer;
        }

        // For other types, might need more complex checking
        return null;
    }

    public function calculateGrade($userAnswer)
    {
        $isCorrect = $this->isCorrectAnswer($userAnswer);
        
        if ($isCorrect === true) {
            return $this->default_grade;
        } elseif ($isCorrect === false) {
            return 0;
        } else {
            return null; // Needs manual grading
        }
    }
}
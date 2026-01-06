<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use  HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'moodle_user_id',
        'lti_context_id',
        'lti_roles',
        'last_login_at',
        'last_activity_at'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'lti_roles' => 'array',
        'last_login_at' => 'datetime',
        'last_activity_at' => 'datetime'
    ];

    public function quizAttempts()
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function moodleTokens()
    {
        return $this->hasMany(MoodleToken::class);
    }

    public function isStudent()
    {
        return in_array('Student', $this->lti_roles ?? []);
    }

    public function isInstructor()
    {
        return in_array('Instructor', $this->lti_roles ?? []) ||
               in_array('Administrator', $this->lti_roles ?? []);
    }

    public function getActiveQuizAttempt($quizId)
    {
        return $this->quizAttempts()
            ->where('moodle_quiz_id', $quizId)
            ->whereIn('status', ['in_progress', 'paused'])
            ->first();
    }


    
}
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LtiController;
use App\Http\Controllers\QuizController;

// LTI 1.3 Routes
Route::prefix('lti')->group(function () {
    // JWKS endpoint
    Route::get('.well-known/jwks.json', [LtiController::class, 'jwks']);
    
    // OIDC Login
    Route::get('/login', [LtiController::class, 'login'])->name('lti.login');
    
    // LTI Launch
    Route::post('/launch', [LtiController::class, 'launch'])->name('lti.launch');
    
    // Token endpoint
    Route::post('/token', [LtiController::class, 'token']);
});

// Protected Quiz Routes (require LTI launch)
Route::middleware(['lti.auth'])->group(function () {
    Route::get('/quiz/available', [QuizController::class, 'getAvailableQuizzes']);
    Route::post('/quiz/start/{quizId}', [QuizController::class, 'startQuiz']);
    Route::get('/quiz/attempt/{attemptId}', [QuizController::class, 'getQuizAttempt']);
    Route::post('/quiz/attempt/{attemptId}/answer', [QuizController::class, 'saveAnswer']);
    Route::post('/quiz/attempt/{attemptId}/auto-save', [QuizController::class, 'autoSave']);
    Route::post('/quiz/attempt/{attemptId}/submit', [QuizController::class, 'submitQuiz']);
    Route::get('/quiz/attempt/{attemptId}/resume', [QuizController::class, 'resumeQuiz']);
    Route::get('/quiz/attempt/{attemptId}/timer', [QuizController::class, 'getTimer']);
});
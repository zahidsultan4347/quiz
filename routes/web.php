<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LtiController;
use App\Http\Controllers\QuizController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// ============================================
// PUBLIC LTI ENDPOINTS (No authentication needed)
// ============================================

// JWKS endpoint
Route::get('lti/.well-known/jwks.json', [LtiController::class, 'jwks'])
    ->name('lti.jwks');

// LTI Login - Handle BOTH GET and POST
Route::match(['GET', 'POST'], 'lti/login', [LtiController::class, 'login'])
    ->name('lti.login');

// LTI Launch - POST only
Route::post('lti/launch', [LtiController::class, 'launch'])
    ->name('lti.launch');

// LTI Token endpoint
Route::post('lti/token', [LtiController::class, 'token'])
    ->name('lti.token');

// ============================================
// TEST & DEBUG ROUTES
// ============================================

Route::get('/test-routes', function () {
    return response()->json([
        'lti_routes' => [
            'jwks' => route('lti.jwks'),
            'login' => route('lti.login'),
            'launch' => route('lti.launch'),
            'token' => route('lti.token'),
        ],
        'app_routes' => [
            'home' => route('home'),
            'dashboard' => route('quiz.dashboard'),
        ]
    ]);
})->name('test.routes');

Route::get('/ping', function () {
    return response()->json([
        'status' => 'ok', 
        'time' => now(),
        'session' => session()->getId(),
        'lti_authenticated' => session('lti_authenticated', false)
    ]);
})->name('ping');

// Debug session
Route::get('/debug-session', function () {
    return response()->json([
        'session' => session()->all(),
        'user' => auth()->user(),
        'lti_authenticated' => session('lti_authenticated', false),
        'moodle_data' => [
            'course_id' => session('moodle_course_id'),
            'resource_link_id' => session('moodle_resource_link_id'),
            'user_id' => session('moodle_user_id'),
        ]
    ]);
})->name('debug.session');

// ============================================
// PROTECTED ROUTES (After LTI launch)
// ============================================

// Dashboard - Only require LTI auth, not Laravel auth
Route::middleware(['lti.auth'])->group(function () {
    
    // Dashboard
    Route::get('/dashboard', function () {
        return view('quiz.dashboard', [
            'user' => auth()->user(),
            'lti_data' => session('lti_launch_data'),
            'session_data' => session()->all()
        ]);
    })->name('quiz.dashboard');
    
    // Quiz routes
    Route::prefix('quiz')->group(function () {
        Route::get('/available', [QuizController::class, 'getAvailableQuizzes'])->name('quiz.available');
        Route::post('/start/{quizId}', [QuizController::class, 'startQuiz'])->name('quiz.start');
        Route::get('/attempt/{attemptId}', [QuizController::class, 'getQuizAttempt'])->name('quiz.attempt');
        Route::post('/attempt/{attemptId}/answer', [QuizController::class, 'saveAnswer'])->name('quiz.answer');
        Route::post('/attempt/{attemptId}/auto-save', [QuizController::class, 'autoSave'])->name('quiz.auto-save');
        Route::post('/attempt/{attemptId}/submit', [QuizController::class, 'submitQuiz'])->name('quiz.submit');
        Route::get('/attempt/{attemptId}/resume', [QuizController::class, 'resumeQuiz'])->name('quiz.resume');
        Route::get('/attempt/{attemptId}/timer', [QuizController::class, 'getTimer'])->name('quiz.timer');
    });
});

// ============================================
// AUTH ROUTES (for Laravel auth if needed)
// ============================================

// Simple login/logout for testing - FIXED ROUTE NAME
Route::get('/login-test', function () {
    if (auth()->check()) {
        return redirect('/dashboard');
    }
    return view('auth.login-test');
})->name('login.test'); // Changed from 'login' to 'login.test'

Route::post('/login-test', function (Illuminate\Http\Request $request) {
    $credentials = $request->validate([
        'email' => ['required', 'email'],
        'password' => ['required'],
    ]);
    
    if (auth()->attempt($credentials)) {
        $request->session()->regenerate();
        return redirect()->intended('/dashboard');
    }
    
    return back()->withErrors([
        'email' => 'Invalid credentials.',
    ]);
})->name('login.test.post'); // Give it a different name

Route::post('/logout', function (Illuminate\Http\Request $request) {
    auth()->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return redirect('/');
})->name('logout');

// ============================================
// HOME PAGE
// ============================================

Route::get('/', function () {
    // If user is authenticated via LTI, redirect to dashboard
    if (session('lti_authenticated')) {
        return redirect()->route('quiz.dashboard');
    }
    
    // Show welcome page
    return view('welcome', [
        'lti_routes' => [
            'jwks' => route('lti.jwks'),
            'login' => route('lti.login'),
            'launch' => route('lti.launch'),
        ],
        'session_status' => [
            'has_session' => session()->isStarted(),
            'session_id' => session()->getId(),
            'lti_authenticated' => session('lti_authenticated', false)
        ]
    ]);
})->name('home');
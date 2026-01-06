<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\QuizAttempt;
use App\Services\Moodle\MoodleService;
use App\Services\Quiz\QuizTimerService;

class QuizController extends Controller
{
    protected $moodleService;
    protected $timerService;
    
    public function __construct(MoodleService $moodleService, QuizTimerService $timerService)
    {
        $this->moodleService = $moodleService;
        $this->timerService = $timerService;
    }
    
    /**
     * Get available quizzes for the current course
     */
    public function getAvailableQuizzes()
    {
        Log::info('Getting available quizzes for user', ['user_id' => auth()->id()]);
        
        $courseId = session('moodle_course_id');
        
        if (!$courseId) {
            return response()->json([
                'error' => 'No course context found in session',
                'session_data' => session()->all()
            ], 400);
        }
        
        try {
            // Get quizzes from Moodle
            $quizzes = $this->moodleService->getCourseQuizzes($courseId, auth()->id());
            
            // Get user's attempts for these quizzes
            $userAttempts = QuizAttempt::where('user_id', auth()->id())
                ->where('moodle_course_id', $courseId)
                ->get()
                ->keyBy('moodle_quiz_id');
            
            // Format response
            $formattedQuizzes = array_map(function ($quiz) use ($userAttempts) {
                $attempt = $userAttempts->get($quiz['id'] ?? null);
                
                return [
                    'id' => $quiz['id'] ?? null,
                    'name' => $quiz['name'] ?? 'Untitled Quiz',
                    'description' => $quiz['intro'] ?? '',
                    'time_limit' => $quiz['timelimit'] ?? 45,
                    'max_grade' => $quiz['grade'] ?? 100,
                    'available_from' => isset($quiz['timeopen']) ? date('Y-m-d H:i:s', $quiz['timeopen']) : null,
                    'available_to' => isset($quiz['timeclose']) ? date('Y-m-d H:i:s', $quiz['timeclose']) : null,
                    'attempts_allowed' => $quiz['attempts'] ?? 1,
                    'user_attempt' => $attempt ? [
                        'id' => $attempt->id,
                        'status' => $attempt->status,
                        'grade' => $attempt->grade,
                        'started_at' => $attempt->started_at,
                        'time_remaining' => $attempt->time_remaining_seconds,
                    ] : null,
                ];
            }, $quizzes);
            
            return response()->json([
                'success' => true,
                'quizzes' => $formattedQuizzes,
                'course_id' => $courseId,
                'user_id' => auth()->id(),
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to get quizzes:', [
                'error' => $e->getMessage(),
                'course_id' => $courseId
            ]);
            
            // Return mock data for testing
            return response()->json([
                'success' => true,
                'quizzes' => $this->getMockQuizzes(),
                'course_id' => $courseId,
                'user_id' => auth()->id(),
                'note' => 'Using mock data - Moodle connection failed'
            ]);
        }
    }
    
    /**
     * Start a new quiz attempt
     */
    public function startQuiz(Request $request, $quizId)
    {
        Log::info('Starting quiz attempt', [
            'user_id' => auth()->id(),
            'quiz_id' => $quizId
        ]);
        
        DB::beginTransaction();
        
        try {
            $user = auth()->user();
            $courseId = session('moodle_course_id');
            
            // Check for existing active attempt
            $existingAttempt = QuizAttempt::where('user_id', $user->id)
                ->where('moodle_quiz_id', $quizId)
                ->whereIn('status', ['in_progress', 'paused'])
                ->first();
            
            if ($existingAttempt) {
                return response()->json([
                    'success' => true,
                    'message' => 'Resuming existing attempt',
                    'attempt_id' => $existingAttempt->id,
                    'status' => $existingAttempt->status,
                    'time_remaining' => $existingAttempt->time_remaining_seconds,
                ]);
            }
            
            // Create new attempt
            $attempt = QuizAttempt::create([
                'user_id' => $user->id,
                'moodle_quiz_id' => $quizId,
                'moodle_course_id' => $courseId,
                'moodle_cmid' => session('moodle_cmid'),
                'status' => 'in_progress',
                'max_grade' => 100, // Default, will be updated from Moodle
                'started_at' => now(),
                'time_limit_minutes' => 45, // Default, will be updated
                'time_remaining_seconds' => 45 * 60,
            ]);
            
            // Start timer
            $this->timerService->startTimer($attempt->id, 45);
            
            Log::info('Quiz attempt started', [
                'attempt_id' => $attempt->id,
                'user_id' => $user->id,
                'quiz_id' => $quizId
            ]);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Quiz started successfully',
                'attempt_id' => $attempt->id,
                'quiz_id' => $quizId,
                'started_at' => $attempt->started_at,
                'time_remaining' => $attempt->time_remaining_seconds,
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to start quiz:', [
                'error' => $e->getMessage(),
                'quiz_id' => $quizId,
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to start quiz: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get quiz attempt details
     */
    public function getQuizAttempt($attemptId)
    {
        $attempt = QuizAttempt::findOrFail($attemptId);
        
        // Verify ownership
        if ($attempt->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        
        return response()->json([
            'success' => true,
            'attempt' => [
                'id' => $attempt->id,
                'quiz_id' => $attempt->moodle_quiz_id,
                'status' => $attempt->status,
                'grade' => $attempt->grade,
                'max_grade' => $attempt->max_grade,
                'started_at' => $attempt->started_at,
                'ended_at' => $attempt->ended_at,
                'time_limit_minutes' => $attempt->time_limit_minutes,
                'time_remaining' => $this->timerService->getRemainingTime($attempt->id),
                'progress_percentage' => $attempt->progress_percentage,
                'answers' => $attempt->answers ?? [],
            ]
        ]);
    }
    
    /**
     * Save answer for a question
     */
    public function saveAnswer(Request $request, $attemptId)
    {
        $validated = $request->validate([
            'question_id' => 'required|integer',
            'answer' => 'required',
            'sequence_number' => 'required|integer',
            'time_spent' => 'integer|min:0'
        ]);
        
        $attempt = QuizAttempt::findOrFail($attemptId);
        
        if ($attempt->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        
        if ($attempt->status !== 'in_progress') {
            return response()->json(['error' => 'Attempt is not in progress'], 400);
        }
        
        DB::beginTransaction();
        
        try {
            // Get current answers
            $answers = $attempt->answers ? json_decode($attempt->answers, true) : [];
            
            // Save answer
            $answers[$validated['question_id']] = [
                'answer' => $validated['answer'],
                'sequence_number' => $validated['sequence_number'],
                'time_spent' => $validated['time_spent'] ?? 0,
                'answered_at' => now()->toDateTimeString(),
            ];
            
            // Update attempt
            $attempt->update([
                'answers' => json_encode($answers),
                'last_sync_at' => now(),
            ]);
            
            DB::commit();
            
            Log::info('Answer saved', [
                'attempt_id' => $attemptId,
                'question_id' => $validated['question_id'],
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Answer saved successfully',
                'saved_at' => now(),
                'question_id' => $validated['question_id'],
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to save answer:', [
                'error' => $e->getMessage(),
                'attempt_id' => $attemptId,
                'question_id' => $validated['question_id']
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to save answer: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Auto-save progress
     */
    public function autoSave(Request $request, $attemptId)
    {
        $validated = $request->validate([
            'answers' => 'required|array',
            'current_question' => 'required|integer',
            'time_remaining' => 'required|integer|min:0'
        ]);
        
        $attempt = QuizAttempt::findOrFail($attemptId);
        
        if ($attempt->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        
        try {
            // Update attempt
            $attempt->update([
                'answers' => json_encode($validated['answers']),
                'time_remaining_seconds' => $validated['time_remaining'],
                'last_sync_at' => now(),
            ]);
            
            // Update timer
            $this->timerService->updateRemainingTime($attemptId, $validated['time_remaining']);
            
            Log::info('Auto-save completed', [
                'attempt_id' => $attemptId,
                'user_id' => auth()->id(),
                'questions_saved' => count($validated['answers'])
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Auto-save completed',
                'saved_at' => now(),
                'questions_saved' => count($validated['answers']),
            ]);
            
        } catch (\Exception $e) {
            Log::error('Auto-save failed:', [
                'error' => $e->getMessage(),
                'attempt_id' => $attemptId
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Auto-save failed: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Submit quiz attempt
     */
    public function submitQuiz(Request $request, $attemptId)
    {
        $attempt = QuizAttempt::findOrFail($attemptId);
        
        if ($attempt->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        
        if ($attempt->status !== 'in_progress') {
            return response()->json(['error' => 'Attempt is not in progress'], 400);
        }
        
        DB::beginTransaction();
        
        try {
            // Stop timer
            $this->timerService->stopTimer($attemptId);
            
            // Calculate grade (mock calculation for now)
            $answers = $attempt->answers ? json_decode($attempt->answers, true) : [];
            $grade = min(count($answers) * 10, 100); // Simple mock grading
            
            // Update attempt
            $attempt->update([
                'status' => 'submitted',
                'grade' => $grade,
                'ended_at' => now(),
                'time_remaining_seconds' => 0,
            ]);
            
            Log::info('Quiz submitted', [
                'attempt_id' => $attemptId,
                'user_id' => auth()->id(),
                'grade' => $grade,
                'questions_answered' => count($answers)
            ]);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Quiz submitted successfully',
                'grade' => $grade,
                'max_grade' => $attempt->max_grade,
                'submitted_at' => now(),
                'questions_answered' => count($answers),
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to submit quiz:', [
                'error' => $e->getMessage(),
                'attempt_id' => $attemptId
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to submit quiz: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Resume quiz after disconnection
     */
    public function resumeQuiz($attemptId)
    {
        $attempt = QuizAttempt::findOrFail($attemptId);
        
        if ($attempt->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        
        if (!in_array($attempt->status, ['in_progress', 'paused'])) {
            return response()->json(['error' => 'Cannot resume this attempt'], 400);
        }
        
        try {
            // Resume timer
            $remaining = $this->timerService->resumeTimer($attemptId);
            
            // Update attempt status
            $attempt->update([
                'status' => 'in_progress',
                'is_disconnected' => false,
                'time_remaining_seconds' => $remaining,
            ]);
            
            Log::info('Quiz resumed', [
                'attempt_id' => $attemptId,
                'user_id' => auth()->id(),
                'time_remaining' => $remaining
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Quiz resumed successfully',
                'attempt_id' => $attemptId,
                'status' => 'in_progress',
                'time_remaining' => $remaining,
                'answers' => $attempt->answers ? json_decode($attempt->answers, true) : [],
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to resume quiz:', [
                'error' => $e->getMessage(),
                'attempt_id' => $attemptId
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to resume quiz: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get timer status
     */
    public function getTimer($attemptId)
    {
        $attempt = QuizAttempt::findOrFail($attemptId);
        
        if ($attempt->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        
        $remaining = $this->timerService->getRemainingTime($attemptId);
        
        return response()->json([
            'success' => true,
            'time_remaining' => $remaining,
            'total_time' => $attempt->time_limit_minutes * 60,
            'is_running' => $attempt->status === 'in_progress',
            'status' => $attempt->status,
            'ends_at' => $attempt->started_at->addMinutes($attempt->time_limit_minutes),
        ]);
    }
    
    /**
     * Mock quizzes for testing
     */
    private function getMockQuizzes()
    {
        return [
            [
                'id' => 1,
                'name' => 'Chapter 1: Introduction Quiz',
                'description' => 'Test your understanding of basic concepts',
                'time_limit' => 45,
                'max_grade' => 100,
                'available_from' => now()->subDays(1)->toDateTimeString(),
                'available_to' => now()->addDays(30)->toDateTimeString(),
                'attempts_allowed' => 3,
                'user_attempt' => null,
            ],
            [
                'id' => 2,
                'name' => 'Chapter 2: Advanced Concepts',
                'description' => 'Advanced level questions',
                'time_limit' => 60,
                'max_grade' => 100,
                'available_from' => now()->toDateTimeString(),
                'available_to' => now()->addDays(15)->toDateTimeString(),
                'attempts_allowed' => 2,
                'user_attempt' => null,
            ],
            [
                'id' => 3,
                'name' => 'Final Exam Practice',
                'description' => 'Comprehensive practice test',
                'time_limit' => 90,
                'max_grade' => 100,
                'available_from' => now()->subDays(7)->toDateTimeString(),
                'available_to' => now()->addDays(7)->toDateTimeString(),
                'attempts_allowed' => 1,
                'user_attempt' => [
                    'id' => 123,
                    'status' => 'in_progress',
                    'grade' => null,
                    'started_at' => now()->subMinutes(10)->toDateTimeString(),
                    'time_remaining' => 2400, // 40 minutes
                ],
            ],
        ];
    }
}
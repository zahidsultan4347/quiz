<!DOCTYPE html>
<html>
<head>
    <title>Available Quizzes</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; }
        .header { background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        .quiz-card { 
            background: white; 
            padding: 20px; 
            border-radius: 10px; 
            margin-bottom: 15px;
            border-left: 5px solid #4CAF50;
            box-shadow: 0 2px 5px rgba(16, 9, 9, 0.1);
        }
        .quiz-card.in-progress { border-left-color: #2196F3; }
        .quiz-card.completed { border-left-color: #9C27B0; }
        .btn { 
            padding: 8px 16px; 
            background: #4CAF50; 
            color: white; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn:hover { opacity: 0.9; }
        .btn-start { background: #4CAF50; }
        .btn-resume { background: #2196F3; }
        .btn-view { background: #9C27B0; }
        .btn-back { background: #607D8B; }
        .quiz-status { 
            display: inline-block; 
            padding: 4px 8px; 
            border-radius: 3px; 
            font-size: 12px;
            margin-left: 10px;
        }
        .status-in-progress { background: #E3F2FD; color: #1976D2; }
        .status-completed { background: #F3E5F5; color: #7B1FA2; }
        .status-not-started { background: #E8F5E8; color: #388E3C; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📝 Available Quizzes</h1>
            <p>Course: {{ session('moodle_course_id') ?? 'Unknown' }}</p>
            <a href="{{ route('quiz.dashboard') }}" class="btn btn-back">← Back to Dashboard</a>
        </div>
        
        <div id="quizzes-container">
            <p>Loading quizzes...</p>
        </div>
    </div>
    
    <script>
        async function loadQuizzes() {
            try {
                const response = await fetch('/quiz/available');
                const data = await response.json();
                
                const container = document.getElementById('quizzes-container');
                container.innerHTML = '';
                
                if (data.quizzes && data.quizzes.length > 0) {
                    data.quizzes.forEach(quiz => {
                        const card = document.createElement('div');
                        card.className = 'quiz-card';
                        
                        if (quiz.user_attempt) {
                            card.className += quiz.user_attempt.status === 'in_progress' ? ' in-progress' : ' completed';
                        }
                        
                        let statusHtml = '';
                        let buttonHtml = '';
                        
                        if (quiz.user_attempt) {
                            if (quiz.user_attempt.status === 'in_progress') {
                                statusHtml = `<span class="quiz-status status-in-progress">In Progress</span>`;
                                buttonHtml = `<a href="/quiz/attempt/${quiz.user_attempt.id}" class="btn btn-resume">Resume Quiz</a>`;
                            } else if (quiz.user_attempt.status === 'submitted') {
                                statusHtml = `<span class="quiz-status status-completed">Completed - Grade: ${quiz.user_attempt.grade}/${quiz.max_grade}</span>`;
                                buttonHtml = `<a href="/quiz/results/${quiz.user_attempt.id}" class="btn btn-view">View Results</a>`;
                            }
                        } else {
                            statusHtml = `<span class="quiz-status status-not-started">Not Started</span>`;
                            buttonHtml = `<button onclick="startQuiz(${quiz.id})" class="btn btn-start">Start Quiz</button>`;
                        }
                        
                        card.innerHTML = `
                            <h3>${quiz.name} ${statusHtml}</h3>
                            <p>${quiz.description}</p>
                            <p><strong>Time Limit:</strong> ${quiz.time_limit} minutes</p>
                            <p><strong>Max Grade:</strong> ${quiz.max_grade} points</p>
                            <p><strong>Attempts Allowed:</strong> ${quiz.attempts_allowed}</p>
                            <div style="margin-top: 15px;">
                                ${buttonHtml}
                            </div>
                        `;
                        
                        container.appendChild(card);
                    });
                } else {
                    container.innerHTML = '<p>No quizzes available for this course.</p>';
                }
                
            } catch (error) {
                console.error('Error loading quizzes:', error);
                document.getElementById('quizzes-container').innerHTML = 
                    '<p>Error loading quizzes. Please try again.</p>';
            }
        }
        
        async function startQuiz(quizId) {
            try {
                const response = await fetch(`/quiz/start/${quizId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                
                const data = await response.json();
                
                if (data.success) {
                    window.location.href = `/quiz/attempt/${data.attempt_id}`;
                } else {
                    alert('Failed to start quiz: ' + (data.error || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error starting quiz:', error);
                alert('Failed to start quiz. Please try again.');
            }
        }
        
        // Load quizzes on page load
        document.addEventListener('DOMContentLoaded', loadQuizzes);
    </script>
</body>
</html>
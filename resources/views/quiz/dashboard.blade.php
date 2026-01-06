<!DOCTYPE html>
<html>
<head>
    <title>Quiz Dashboard</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            padding: 20px; 
            background: #f5f5f5;
        }
        .container { 
            max-width: 1200px; 
            margin: 0 auto; 
        }
        .header { 
            background: white; 
            padding: 20px; 
            border-radius: 10px; 
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .card { 
            background: white; 
            padding: 20px; 
            border-radius: 10px; 
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .user-info { 
            background: #e3f2fd; 
            padding: 15px; 
            border-radius: 5px; 
            margin-bottom: 15px;
        }
        .lti-data { 
            background: #f3e5f5; 
            padding: 15px; 
            border-radius: 5px; 
            margin-bottom: 15px;
        }
        pre { 
            background: #333; 
            color: white; 
            padding: 15px; 
            border-radius: 5px; 
            overflow: auto;
            font-size: 12px;
            max-height: 300px;
        }
        .btn { 
            display: inline-block; 
            padding: 10px 20px; 
            background: #4CAF50; 
            color: white; 
            text-decoration: none; 
            border-radius: 5px; 
            margin: 5px;
        }
        .btn:hover { opacity: 0.9; }
        .btn-logout { background: #f44336; }
        .btn-test { background: #ff9800; }
        .status-connected { color: #4CAF50; font-weight: bold; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 Quiz Dashboard</h1>
            <p class="status-connected">✅ Connected to Moodle via LTI 1.3</p>
        </div>
        
        <div class="grid">
            <div class="card">
                <h2>👤 User Information</h2>
                <div class="user-info">
                    <p><strong>Name:</strong> {{ $user->name ?? 'Unknown' }}</p>
                    <p><strong>Email:</strong> {{ $user->email ?? 'Unknown' }}</p>
                    <p><strong>Moodle User ID:</strong> {{ $user->moodle_user_id ?? 'Unknown' }}</p>
                    <p><strong>Logged in at:</strong> {{ $user->last_login_at ?? 'Unknown' }}</p>
                </div>
            </div>
            
            <div class="card">
                <h2>🏫 Course Information</h2>
                <div class="lti-data">
                    <p><strong>Course ID:</strong> {{ session('moodle_course_id') ?? 'Unknown' }}</p>
                    <p><strong>Resource Link ID:</strong> {{ session('moodle_resource_link_id') ?? 'Unknown' }}</p>
                    <p><strong>CMID:</strong> {{ session('moodle_cmid') ?? 'Unknown' }}</p>
                    <p><strong>Deployment ID:</strong> {{ session('moodle_deployment_id') ?? 'Unknown' }}</p>
                </div>
            </div>
        </div>
        
        <div class="card">
            <h2>🎯 Available Actions</h2>
            <div>
                <a href="{{ url('/quiz/available') }}" class="btn">📝 View Available Quizzes</a>
                <a href="{{ url('/test-routes') }}" class="btn btn-test">🧪 Test Routes</a>
                <a href="{{ url('/debug-session') }}" class="btn btn-test">🔍 Debug Session</a>
                <a href="{{ url('/') }}" class="btn">🏠 Home</a>
                <form action="{{ route('logout') }}" method="POST" style="display: inline;">
                    @csrf
                    <button type="submit" class="btn btn-logout">🚪 Logout</button>
                </form>
            </div>
        </div>
        
        <div class="card">
            <h2>🔧 Debug Information</h2>
            <details>
                <summary>Show LTI Launch Data</summary>
                <pre>{{ json_encode($lti_data ?? [], JSON_PRETTY_PRINT) }}</pre>
            </details>
            
            <details>
                <summary>Show Session Data</summary>
                <pre>{{ json_encode($session_data ?? [], JSON_PRETTY_PRINT) }}</pre>
            </details>
        </div>
    </div>
</body>
</html>
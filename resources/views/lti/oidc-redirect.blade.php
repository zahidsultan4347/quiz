<!DOCTYPE html>
<html>
<head>
    <title>Redirecting to Moodle...</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            padding: 40px; 
            text-align: center; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        .container { 
            max-width: 600px; 
            background: rgba(255,255,255,0.1);
            padding: 30px;
            border-radius: 10px;
            backdrop-filter: blur(10px);
        }
        .spinner {
            border: 4px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top: 4px solid white;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .info {
            background: rgba(0,0,0,0.2);
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
            text-align: left;
            font-family: monospace;
            font-size: 12px;
            overflow: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 LTI Authentication</h1>
        <p>Redirecting to Moodle for authentication...</p>
        
        <div class="spinner"></div>
        
        <p>Please wait while we complete the LTI 1.3 authentication flow.</p>
        
        <div class="info">
            <p><strong>Issuer:</strong> {{ $issuer }}</p>
            <p><strong>Client ID:</strong> {{ $client_id }}</p>
            <p><strong>Auth Endpoint:</strong> {{ $auth_endpoint }}</p>
        </div>
        
        <!-- Auto-submit form to Moodle -->
        <form id="oidcForm" action="{{ $auth_endpoint }}" method="POST" style="display: none;">
            @foreach($auth_params as $key => $value)
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endforeach
        </form>
        
        <script>
            // Auto-submit form after 1 second
            setTimeout(function() {
                document.getElementById('oidcForm').submit();
            }, 1000);
            
            // Manual submit button as fallback
            document.write('<p style="margin-top: 20px;">If redirect doesn\'t work, <button onclick="document.getElementById(\'oidcForm\').submit()" style="background: white; color: #667eea; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;">Click here to continue</button></p>');
        </script>
    </div>
</body>
</html>
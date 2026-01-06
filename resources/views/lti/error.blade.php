<!DOCTYPE html>
<html>
<head>
    <title>LTI Error</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 40px; }
        .error-container { 
            max-width: 800px; 
            margin: 0 auto; 
            border: 1px solid #e74c3c;
            border-radius: 10px;
            padding: 30px;
            background: #fdf7f7;
        }
        .error-title { 
            color: #e74c3c; 
            border-bottom: 2px solid #e74c3c;
            padding-bottom: 10px;
        }
        .error-message { 
            background: white;
            padding: 20px;
            border-radius: 5px;
            border-left: 4px solid #e74c3c;
            margin: 20px 0;
        }
        pre { 
            background: #333; 
            color: #fff; 
            padding: 15px; 
            border-radius: 5px; 
            overflow: auto;
            font-size: 12px;
        }
        .actions {
            margin-top: 20px;
            display: flex;
            gap: 10px;
        }
        .btn {
            padding: 10px 20px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            display: inline-block;
        }
        .btn-home { background: #2ecc71; }
        .btn-test { background: #f39c12; }
    </style>
</head>
<body>
    <div class="error-container">
        <h1 class="error-title">🚨 LTI Error</h1>
        
        <div class="error-message">
            <h3>Error Message:</h3>
            <p>{{ $message }}</p>
            
            @if(isset($error_details))
                <h3>Error Details:</h3>
                <pre>{{ $error_details }}</pre>
            @endif
            
            @if(isset($request_data))
                <h3>Request Data:</h3>
                <pre>{{ json_encode($request_data, JSON_PRETTY_PRINT) }}</pre>
            @endif
        </div>
        
        <div class="actions">
            <a href="{{ url('/') }}" class="btn btn-home">🏠 Home</a>
            <a href="{{ url('/test-routes') }}" class="btn btn-test">🧪 Test Routes</a>
            <a href="{{ url('/lti/.well-known/jwks.json') }}" class="btn">🔑 JWKS</a>
        </div>
    </div>
</body>
</html>
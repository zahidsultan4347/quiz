<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\LtiRegistration;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class LtiController extends Controller
{
    /**
     * JWKS endpoint (Public keyset)
     */
    public function jwks()
    {
        Log::info('JWKS endpoint accessed');
        
        try {
            $publicKeyPath = storage_path('keys/public.key');
            
            if (!file_exists($publicKeyPath)) {
                return response()->json(['error' => 'Public key not found'], 404);
            }
            
            $publicKey = file_get_contents($publicKeyPath);
            $keyResource = openssl_pkey_get_public($publicKey);
            
            if (!$keyResource) {
                return response()->json(['error' => 'Invalid public key'], 500);
            }
            
            $keyDetails = openssl_pkey_get_details($keyResource);
            
            // Base64Url encode
            $n = $this->base64urlEncode($keyDetails['rsa']['n']);
            $e = $this->base64urlEncode($keyDetails['rsa']['e']);
            
            $kid = config('lti.default.kid', 'moodle-quiz-lti-key');
            
            $jwk = [
                'kty' => 'RSA',
                'alg' => 'RS256',
                'use' => 'sig',
                'kid' => $kid,
                'n' => $n,
                'e' => $e,
            ];
            
            return response()->json(['keys' => [$jwk]]);
            
        } catch (\Exception $e) {
            Log::error('JWKS Error: ' . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }
    
    /**
     * LTI Login - Handle both GET and POST
     * GET: Initiate login (from Moodle link)
     * POST: Receive login request from Moodle with form data
     */
    public function login(Request $request)
    {
        Log::info('LTI Login accessed', [
            'method' => $request->method(),
            'data' => $request->all()
        ]);
        
        // Handle POST request (from Moodle form submission)
        if ($request->isMethod('post')) {
            return $this->handleLoginPost($request);
        }
        
        // Handle GET request (initiate login)
        return $this->handleLoginGet($request);
    }
    public function launch(Request $request)
{
    Log::info('LTI Launch endpoint accessed', [
        'method' => $request->method(),
        'has_id_token' => $request->has('id_token'),
        'state' => $request->input('state')
    ]);
    
    try {
        // Get the JWT from Moodle
        $idToken = $request->input('id_token');
        
        if (!$idToken) {
            Log::error('No id_token received in launch');
            return view('lti.error', [
                'message' => 'No JWT token received from Moodle',
                'request_data' => $request->all()
            ]);
        }
        
        Log::info('Received JWT (first 100 chars): ' . substr($idToken, 0, 100) . '...');
        
        // Decode JWT using proper URL-safe base64 decoding
        $parts = explode('.', $idToken);
        if (count($parts) !== 3) {
            throw new \Exception('Invalid JWT format. Expected 3 parts, got ' . count($parts));
        }
        
        // Use URL-safe base64 decode
        $header = $this->base64urlDecode($parts[0]);
        $payload = $this->base64urlDecode($parts[1]);
        $signature = $parts[2];
        
        if (!$header || !$payload) {
            throw new \Exception('Failed to decode JWT header or payload');
        }
        
        $headerData = json_decode($header, true);
        $payloadData = json_decode($payload, true);
        
        if (!$headerData || !$payloadData) {
            throw new \Exception('Failed to parse JWT JSON. Header: ' . $header . ' Payload: ' . $payload);
        }
        
        Log::info('JWT Header:', $headerData);
        Log::info('JWT Payload decoded:', $payloadData);
        
        // Get issuer and client ID from JWT
        $issuer = $payloadData['iss'] ?? null;
        $clientId = $payloadData['aud'] ?? null;
        $deploymentId = $payloadData['https://purl.imsglobal.org/spec/lti/claim/deployment_id'] ?? null;
        
        // Handle case where aud might be an array
        if (is_array($clientId)) {
            $clientId = $clientId[0] ?? null;
        }
        
        if (!$issuer || !$clientId) {
            throw new \Exception('Missing iss or aud in JWT. Issuer: ' . ($issuer ?? 'null') . ', Audience: ' . (is_array($clientId) ? json_encode($clientId) : ($clientId ?? 'null')));
        }
        
        Log::info('Looking for registration:', [
            'issuer' => $issuer,
            'client_id' => $clientId,
            'deployment_id' => $deploymentId
        ]);
        
        // Find LTI registration
        $registration = LtiRegistration::where('issuer', $issuer)
            ->where('client_id', $clientId)
            ->first();
        
        if (!$registration) {
            // Try without trailing slash
            $issuerAlt = rtrim($issuer, '/');
            if ($issuerAlt !== $issuer) {
                $registration = LtiRegistration::where('issuer', $issuerAlt)
                    ->where('client_id', $clientId)
                    ->first();
            }
            
            if (!$registration) {
                $allRegistrations = LtiRegistration::all()->map(function($reg) {
                    return ['issuer' => $reg->issuer, 'client_id' => $reg->client_id];
                })->toArray();
                
                throw new \Exception('LTI registration not found for issuer: ' . $issuer . ' client: ' . $clientId . 
                    '. Available registrations: ' . json_encode($allRegistrations));
            }
        }
        
        Log::info('Found registration:', [
            'id' => $registration->id,
            'issuer' => $registration->issuer,
            'client_id' => $registration->client_id
        ]);
        
        // For development: Skip JWT validation, just process the user
        // TODO: In production, implement proper JWT validation using Moodle's public key
        
        // Extract user information
        $userData = [
            'id' => $payloadData['sub'] ?? null,
            'name' => $payloadData['name'] ?? ($payloadData['given_name'] ?? 'Moodle User'),
            'email' => $payloadData['email'] ?? null,
        ];
        
        // Extract context/resource information
        $contextData = $payloadData['https://purl.imsglobal.org/spec/lti/claim/context'] ?? [];
        $resourceData = $payloadData['https://purl.imsglobal.org/spec/lti/claim/resource_link'] ?? [];
        $roles = $payloadData['https://purl.imsglobal.org/spec/lti/claim/roles'] ?? [];
        $customParams = $payloadData['https://purl.imsglobal.org/spec/lti/claim/custom'] ?? [];
        
        Log::info('User data extracted:', [
            'user_id' => $userData['id'],
            'name' => $userData['name'],
            'email' => $userData['email'],
            'course_id' => $contextData['id'] ?? null,
            'resource_id' => $resourceData['id'] ?? null,
            'roles' => $roles
        ]);
        
        // Create or update user - FIX: Use email as unique identifier for now
        $userEmail = $userData['email'] ?? ($userData['id'] . '@moodle.user');
        
        $user = User::updateOrCreate(
            ['email' => $userEmail],  // Use email as unique identifier
            [
                'name' => $userData['name'],
                'moodle_user_id' => $userData['id'],
                'password' => bcrypt(uniqid()), // Random password for LTI users
                'lti_context_id' => $contextData['id'] ?? null,
                'lti_roles' => json_encode($roles),
                'last_login_at' => now(),
                'last_activity_at' => now(),
            ]
        );
        
        // Log the user in
        Auth::login($user);
        
        // Store LTI data in session
        session([
            'lti_authenticated' => true,
            'lti_launch_data' => $payloadData,
            'lti_user' => $userData,
            'lti_context' => $contextData,
            'lti_resource' => $resourceData,
            'lti_roles' => $roles,
            'lti_custom' => $customParams,
            'moodle_course_id' => $contextData['id'] ?? $customParams['course_id'] ?? null,
            'moodle_resource_link_id' => $resourceData['id'] ?? $customParams['resource_link_id'] ?? null,
            'moodle_cmid' => $customParams['custom_cmid'] ?? null,
            'moodle_deployment_id' => $deploymentId,
            'moodle_user_id' => $userData['id'],
        ]);
        
        Log::info('✅ User authenticated via LTI:', [
            'user_id' => $user->id,
            'moodle_user_id' => $userData['id'],
            'name' => $userData['name'],
            'course_id' => $contextData['id'] ?? null,
            'resource_link_id' => $resourceData['id'] ?? null
        ]);
        
        // Redirect to dashboard
        return redirect()->route('quiz.dashboard');
        
    } catch (\Exception $e) {
        Log::error('LTI Launch error: ' . $e->getMessage());
        Log::error('Trace: ' . $e->getTraceAsString());
        
        // Log the raw JWT for debugging
        if (isset($idToken)) {
            Log::error('Raw JWT: ' . $idToken);
            Log::error('JWT Parts count: ' . (isset($parts) ? count($parts) : 'N/A'));
        }
        
        return view('lti.error', [
            'message' => 'LTI Launch failed: ' . $e->getMessage(),
            'error_details' => $e->getTraceAsString(),
            'request_data' => $request->all()
        ]);
    }
}

/**
 * Helper: Base64Url decode
 */
private function base64urlDecode($data)
{
    // Add padding if needed
    $padding = strlen($data) % 4;
    if ($padding) {
        $data .= str_repeat('=', 4 - $padding);
    }
    
    // Replace URL-safe characters
    $data = strtr($data, '-_', '+/');
    
    return base64_decode($data);
}

/**
 * Helper: Base64Url encode
 */
private function base64urlEncode($data)
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}
    /**
     * Handle GET request to initiate login
     */
    private function handleLoginGet(Request $request)
    {
        // Required parameters for GET
        $required = ['iss', 'login_hint', 'target_link_uri', 'client_id'];
        
        foreach ($required as $param) {
            if (!$request->has($param)) {
                Log::error('Missing required GET parameter: ' . $param);
                return response()->json([
                    'error' => 'Missing required parameter: ' . $param,
                    'received' => $request->all()
                ], 400);
            }
        }
        
        $issuer = $request->get('iss');
        $loginHint = $request->get('login_hint');
        $targetLinkUri = $request->get('target_link_uri');
        $clientId = $request->get('client_id');
        $deploymentId = $request->get('lti_deployment_id', '1');
        $ltiMessageHint = $request->get('lti_message_hint');
        
        Log::info('LTI Login GET parameters:', [
            'issuer' => $issuer,
            'client_id' => $clientId,
            'deployment_id' => $deploymentId
        ]);
        
        // Generate state and nonce for OIDC
        $state = bin2hex(random_bytes(16));
        $nonce = bin2hex(random_bytes(16));
        
        // Store in session for validation during launch
        session([
            'lti_oidc_state' => $state,
            'lti_oidc_nonce' => $nonce,
            'lti_issuer' => $issuer,
            'lti_client_id' => $clientId,
            'lti_deployment_id' => $deploymentId,
            'lti_target_link_uri' => $targetLinkUri,
            'lti_login_hint' => $loginHint,
            'lti_message_hint' => $ltiMessageHint,
        ]);
        
        // Build the OIDC authentication request
        // This redirects back to Moodle's OIDC endpoint
        $authParams = [
            'scope' => 'openid',
            'response_type' => 'id_token',
            'response_mode' => 'form_post',
            'prompt' => 'none',
            'client_id' => $clientId,
            'redirect_uri' => $targetLinkUri,
            'login_hint' => $loginHint,
            'state' => $state,
            'nonce' => $nonce,
            'lti_message_hint' => $ltiMessageHint ?? ''
        ];
        
        // Moodle's OIDC auth endpoint
        $authEndpoint = rtrim($issuer, '/') . '/mod/lti/auth.php';
        
        Log::info('Redirecting to Moodle OIDC endpoint: ' . $authEndpoint);
        
        // Create a form that auto-submits to Moodle (standard LTI 1.3 flow)
        return view('lti.oidc-redirect', [
            'auth_endpoint' => $authEndpoint,
            'auth_params' => $authParams,
            'issuer' => $issuer,
            'client_id' => $clientId
        ]);
    }
    
    /**
     * Handle POST request from Moodle form
     */
    private function handleLoginPost(Request $request)
    {
        // This handles the POST request that Moodle is sending
        // Moodle is sending form data to initiate the OIDC flow
        
        Log::info('Received POST from Moodle to login endpoint');
        
        // Actually, Moodle should be doing a GET to initiate, then POST to launch
        // But if Moodle is POSTing here, we need to handle it
        
        // For now, treat it like a GET and redirect to OIDC flow
        $required = ['iss', 'login_hint', 'target_link_uri', 'client_id'];
        
        foreach ($required as $param) {
            if (!$request->has($param)) {
                Log::error('Missing required POST parameter: ' . $param);
                return response()->json([
                    'error' => 'Missing required parameter: ' . $param,
                    'received' => $request->all()
                ], 400);
            }
        }
        
        // Redirect to GET handler with the same parameters
        $queryParams = http_build_query($request->only([
            'iss', 'login_hint', 'target_link_uri', 'client_id', 
            'lti_deployment_id', 'lti_message_hint'
        ]));
        
        $redirectUrl = url('/lti/login') . '?' . $queryParams;
        
        Log::info('Redirecting POST to GET: ' . $redirectUrl);
        
        return redirect($redirectUrl);
    }
    
    // /**
    //  * LTI Launch - Receive JWT from Moodle
    //  */
    // public function launch(Request $request)
    // {
    //     Log::info('LTI Launch endpoint accessed', [
    //         'method' => $request->method(),
    //         'has_id_token' => $request->has('id_token')
    //     ]);
        
    //     try {
    //         // Get the JWT from Moodle
    //         $idToken = $request->input('id_token');
            
    //         if (!$idToken) {
    //             Log::error('No id_token received in launch');
    //             return view('lti.error', [
    //                 'message' => 'No JWT token received from Moodle',
    //                 'request_data' => $request->all()
    //             ]);
    //         }
            
    //         Log::info('Received JWT (first 100 chars): ' . substr($idToken, 0, 100) . '...');
            
    //         // Decode JWT without verification first to get issuer
    //         $parts = explode('.', $idToken);
    //         if (count($parts) !== 3) {
    //             throw new \Exception('Invalid JWT format');
    //         }
            
    //         $payload = json_decode(base64_decode($parts[1]), true);
            
    //         if (!$payload) {
    //             throw new \Exception('Failed to decode JWT payload');
    //         }
            
    //         Log::info('JWT Pay decoded:', $payload);
            
    //         // Get issuer and client ID from JWT
    //         $issuer = $payload['iss'] ?? null;
    //         $clientId = $payload['aud'] ?? null;
    //         $deploymentId = $payload['https://purl.imsglobal.org/spec/lti/claim/deployment_id'] ?? null;
            
    //         if (!$issuer || !$clientId) {
    //             throw new \Exception('Missing iss or aud in JWT');
    //         }
            
    //         // Find LTI registration
    //         $registration = LtiRegistration::where('issuer', $issuer)
    //             ->where('client_id', $clientId)
    //             ->first();
            
    //         if (!$registration) {
    //             throw new \Exception('LTI registration not found for issuer: ' . $issuer . ' client: ' . $clientId);
    //         }
            
    //         Log::info('Found registration:', ['id' => $registration->id]);
            
    //         // TODO: In production, validate JWT signature using Moodle's public key
    //         // For now, accept the JWT and process claims
            
    //         // Extract user information
    //         $userData = [
    //             'id' => $payload['sub'] ?? null,
    //             'name' => $payload['name'] ?? $payload['given_name'] ?? 'Moodle User',
    //             'email' => $payload['email'] ?? null,
    //         ];
            
    //         // Extract context/resource information
    //         $contextData = $payload['https://purl.imsglobal.org/spec/lti/claim/context'] ?? [];
    //         $resourceData = $payload['https://purl.imsglobal.org/spec/lti/claim/resource_link'] ?? [];
    //         $roles = $payload['https://purl.imsglobal.org/spec/lti/claim/roles'] ?? [];
            
    //         // Create or update user
    //         $user = User::updateOrCreate(
    //             ['moodle_user_id' => $userData['id']],
    //             [
    //                 'name' => $userData['name'],
    //                 'email' => $userData['email'] ?? $userData['id'] . '@moodle.user',
    //                 'password' => bcrypt(uniqid()), // Random password for LTI users
    //                 'lti_context_id' => $contextData['id'] ?? null,
    //                 'lti_roles' => json_encode($roles),
    //                 'last_login_at' => now(),
    //             ]
    //         );
            
    //         // Log the user in
    //         Auth::login($user);
            
    //         // Store LTI data in session
    //         session([
    //             'lti_authenticated' => true,
    //             'lti_launch_data' => $payload,
    //             'lti_user' => $userData,
    //             'lti_context' => $contextData,
    //             'lti_resource' => $resourceData,
    //             'lti_roles' => $roles,
    //             'moodle_course_id' => $contextData['id'] ?? null,
    //             'moodle_resource_link_id' => $resourceData['id'] ?? null,
    //             'moodle_deployment_id' => $deploymentId,
    //         ]);
            
    //         Log::info('User authenticated via LTI:', [
    //             'user_id' => $user->id,
    //             'moodle_user_id' => $userData['id'],
    //             'course_id' => $contextData['id'] ?? null
    //         ]);
            
    //         // Redirect to dashboard
    //         return redirect()->route('quiz.dashboard');
            
    //     } catch (\Exception $e) {
    //         Log::error('LTI Launch error: ' . $e->getMessage());
            
    //         return view('lti.error', [
    //             'message' => 'LTI Launch failed: ' . $e->getMessage(),
    //             'error_details' => $e->getTraceAsString()
    //         ]);
    //     }
    // }
    
    /**
     * LTI Token endpoint
     */
    public function token(Request $request)
    {
        Log::info('Token endpoint accessed');
        
        // Simple token response for now
        return response()->json([
            'access_token' => 'dummy_token_' . time(),
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ]);
    }
    
    // /**
    //  * Helper: Base64Url encode
    //  */
    // private function base64urlEncode($data)
    // {
    //     return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    // }
}
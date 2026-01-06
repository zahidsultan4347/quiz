<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;

class LtiAuth
{
    /**
     * Handle LTI authentication
     */
    public function handle(Request $request, Closure $next)
    {
        Log::info('LTI Auth Middleware - Checking authentication', [
            'path' => $request->path(),
            'session_id' => Session::getId(),
            'lti_authenticated' => Session::get('lti_authenticated', false),
            'user_authenticated' => Auth::check()
        ]);
        
        // Check if user is authenticated via LTI
        if (!Session::get('lti_authenticated')) {
            Log::warning('LTI Auth - No LTI authentication found');
            
            // If user is logged in via Laravel auth, that's also OK
            if (!Auth::check()) {
                Log::warning('LTI Auth - No authentication at all, redirecting to home');
                return redirect('/')->withErrors([
                    'lti_error' => 'Please launch this tool from Moodle using LTI'
                ]);
            }
        } else {
            Log::info('LTI Auth - User is LTI authenticated');
        }
        
        // If LTI authenticated but not logged in, try to log them in
        if (Session::get('lti_authenticated') && !Auth::check()) {
            $ltiUser = Session::get('lti_user');
            if ($ltiUser && isset($ltiUser['id'])) {
                $user = \App\Models\User::where('moodle_user_id', $ltiUser['id'])->first();
                
                if (!$user && isset($ltiUser['email'])) {
                    $user = \App\Models\User::where('email', $ltiUser['email'])->first();
                }
                
                if ($user) {
                    Auth::login($user);
                    Log::info('LTI Auth - Auto-logged in user', ['user_id' => $user->id]);
                } else {
                    Log::error('LTI Auth - User not found in database', ['lti_user' => $ltiUser]);
                    // Don't redirect, just continue - user will be created in controller
                }
            }
        }
        
        // Update last activity
        Session::put('lti_last_activity', now());
        
        Log::info('LTI Auth - Authentication successful, proceeding to route');
        return $next($request);
    }
}
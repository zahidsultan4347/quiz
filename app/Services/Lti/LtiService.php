<?php

namespace App\Services\Lti;

use Packback\Lti1p3\LtiMessageLaunch;
use Packback\Lti1p3\LtiServiceConnector;
use GuzzleHttp\Client;
use App\Models\User;
use App\Models\MoodleToken;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class LtiService
{
    private $launch;
    private $database;
    private $cache;
    
    public function __construct()
    {
        $this->database = new LtiDatabase();
        $this->cache = app('cache.store');
    }
    
    public function validateLaunch(array $requestData)
    {
        try {
            $launch = LtiMessageLaunch::new($this->database, $this->cache)
                ->validate($requestData);
            
            $this->launch = $launch;
            return true;
        } catch (\Exception $e) {
            Log::error('LTI Launch Validation Failed:', [
                'error' => $e->getMessage(),
                'data' => $requestData
            ]);
            return false;
        }
    }
    
    public function getLaunchData()
    {
        if (!$this->launch) {
            return null;
        }
        
        return [
            'user' => $this->launch->getLaunchUser(),
            'context' => $this->launch->getLaunchContext(),
            'resource' => $this->launch->getLaunchResource(),
            'platform' => $this->launch->getPlatformInstance(),
            'roles' => $this->launch->getRoles(),
            'is_student' => $this->launch->isStudent(),
            'is_instructor' => $this->launch->isInstructor(),
            'custom_parameters' => $this->launch->getCustomParameters()
        ];
    }
    
    public function handleUserLogin()
    {
        $launchData = $this->getLaunchData();
        $userData = $launchData['user'];
        
        // Find or create user
        $user = User::updateOrCreate(
            ['email' => $userData['email'] ?? $userData['sub'].'@moodle.user'],
            [
                'name' => $userData['name'] ?? $userData['given_name'] ?? 'Moodle User',
                'moodle_user_id' => $userData['sub'],
                'lti_context_id' => $launchData['context']['id'] ?? null,
                'lti_roles' => json_encode($launchData['roles'] ?? []),
                'password' => bcrypt(uniqid()) // Random password for LTI users
            ]
        );
        
        Auth::login($user);
        
        return $user;
    }
    
    public function getGradeService()
    {
        return $this->launch->getAgs();
    }
    
    public function getNamesAndRolesService()
    {
        return $this->launch->getNrps();
    }
}
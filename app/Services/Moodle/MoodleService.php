<?php

namespace App\Services\Moodle;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use App\Models\MoodleToken;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MoodleService
{
    private $client;
    private $baseUrl;
    private $apiBaseUrl;
    private $defaultToken;
    
    public function __construct()
    {
        // Make sure this matches your Moodle installation URL
        $this->baseUrl = env('MOODLE_URL', 'http://localhost/moodle89');
        
        // Full API endpoint URL
        $this->apiBaseUrl = $this->baseUrl . '/webservice/rest/server.php';
        
        // Your web service token - consider storing in .env
        $this->defaultToken = env('MOODLE_TOKEN', '7e45a76985160fe074d199ed4f83739f');
        
        $this->client = new Client([
            'timeout' => 30,
            'verify' => false, // Set to true in production with valid SSL
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'LTI-Quiz-App/1.0',
            ]
        ]);
        
        Log::info('MoodleService initialized', [
            'base_url' => $this->baseUrl,
            'api_url' => $this->apiBaseUrl,
            'token_prefix' => substr($this->defaultToken, 0, 10) . '...'
        ]);
    }
    
    /**
     * Get Moodle site information to test connection
     */
    public function getSiteInfo()
    {
        try {
            Log::info('Testing Moodle connection...');
            
            $response = $this->client->post($this->apiBaseUrl, [
                'form_params' => [
                    'wstoken' => $this->defaultToken,
                    'wsfunction' => 'core_webservice_get_site_info',
                    'moodlewsrestformat' => 'json'
                ]
            ]);
            
            $data = json_decode($response->getBody(), true);
            
            if (isset($data['exception'])) {
                Log::error('Moodle site info error:', $data);
                return [
                    'success' => false,
                    'error' => $data['message'] ?? 'Unknown error',
                    'exception' => $data
                ];
            }
            
            Log::info('Moodle connection successful', [
                'site_name' => $data['sitename'] ?? 'Unknown',
                'username' => $data['username'] ?? 'Unknown',
                'userid' => $data['userid'] ?? 0
            ]);
            
            return [
                'success' => true,
                'data' => $data
            ];
            
        } catch (RequestException $e) {
            Log::error('Moodle connection failed (RequestException):', [
                'message' => $e->getMessage(),
                'url' => $this->apiBaseUrl,
                'code' => $e->getCode(),
                'response' => $e->hasResponse() ? (string) $e->getResponse()->getBody() : null
            ]);
            
            return [
                'success' => false,
                'error' => 'Moodle connection failed: ' . $e->getMessage(),
                'url' => $this->apiBaseUrl
            ];
            
        } catch (\Exception $e) {
            Log::error('Moodle connection failed:', [
                'message' => $e->getMessage(),
                'url' => $this->apiBaseUrl
            ]);
            
            return [
                'success' => false,
                'error' => 'Moodle connection failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get token for user (currently using default token for all users)
     */
    public function getUserToken(User $user = null)
    {
        // For now, return default token
        // In production, implement proper user-specific token management
        Log::info('Getting Moodle token', [
            'user_id' => $user ? $user->id : 'anonymous',
            'using_token' => substr($this->defaultToken, 0, 10) . '...'
        ]);
        
        return $this->defaultToken;
    }
    
    /**
     * Fetch available quizzes for a course
     */
    public function getCourseQuizzes($courseId, $userId = null)
    {
        $token = $this->getUserToken(auth()->user());
        
        Log::info('Fetching quizzes from Moodle', [
            'course_id' => $courseId,
            'user_id' => $userId ?? auth()->id(),
            'api_url' => $this->apiBaseUrl,
            'function' => 'mod_quiz_get_quizzes_by_courses'
        ]);
        
        try {
            $response = $this->client->post($this->apiBaseUrl, [
                'form_params' => [
                    'wstoken' => $token,
                    'wsfunction' => 'mod_quiz_get_quizzes_by_courses',
                    'moodlewsrestformat' => 'json',
                    'courseids[0]' => $courseId
                ],
                'debug' => false // Set to true for detailed request logging
            ]);
            
            $responseBody = (string) $response->getBody();
            $data = json_decode($responseBody, true);
            
            Log::info('Moodle API response received', [
                'status_code' => $response->getStatusCode(),
                'has_data' => !empty($data),
                'quizzes_count' => count($data['quizzes'] ?? []),
                'response_keys' => array_keys($data)
            ]);
            
            if (isset($data['exception'])) {
                Log::error('Moodle API returned exception:', [
                    'exception_type' => $data['exception'],
                    'message' => $data['message'] ?? 'No message',
                    'debuginfo' => $data['debuginfo'] ?? 'No debug info',
                    'course_id' => $courseId
                ]);
                
                return [];
            }
            
            if (!isset($data['quizzes'])) {
                Log::warning('Moodle response missing quizzes key', [
                    'response' => $data,
                    'course_id' => $courseId
                ]);
                return [];
            }
            
            Log::info('Successfully retrieved quizzes', [
                'count' => count($data['quizzes']),
                'quiz_names' => array_map(function($quiz) {
                    return $quiz['name'] ?? 'Unnamed';
                }, $data['quizzes'])
            ]);
            
            return $data['quizzes'];
            
        } catch (RequestException $e) {
            Log::error('Moodle API RequestException:', [
                'message' => $e->getMessage(),
                'url' => $this->apiBaseUrl,
                'course_id' => $courseId,
                'request_url' => $e->hasRequest() ? (string) $e->getRequest()->getUri() : 'Unknown',
                'response' => $e->hasResponse() ? (string) $e->getResponse()->getBody() : 'No response',
                'status_code' => $e->hasResponse() ? $e->getResponse()->getStatusCode() : 0
            ]);
            
            return [];
            
        } catch (\Exception $e) {
            Log::error('Failed to fetch quizzes:', [
                'error' => $e->getMessage(),
                'course_id' => $courseId,
                'trace' => $e->getTraceAsString()
            ]);
            
            return [];
        }
    }
    
    /**
     * Get quiz by ID
     */
    public function getQuiz($quizId)
    {
        $token = $this->getUserToken();
        
        try {
            $response = $this->client->post($this->apiBaseUrl, [
                'form_params' => [
                    'wstoken' => $token,
                    'wsfunction' => 'mod_quiz_get_quizzes_by_courses',
                    'moodlewsrestformat' => 'json',
                    'quizids[0]' => $quizId
                ]
            ]);
            
            $data = json_decode($response->getBody(), true);
            
            if (isset($data['exception'])) {
                throw new \Exception($data['message'] ?? 'Failed to get quiz');
            }
            
            return $data['quizzes'][0] ?? null;
            
        } catch (\Exception $e) {
            Log::error('Failed to get quiz:', [
                'quiz_id' => $quizId,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Start a quiz attempt in Moodle
     */
    public function startQuizAttempt($quizId, $userId)
    {
        $token = $this->getUserToken(auth()->user());
        
        try {
            $response = $this->client->post($this->apiBaseUrl, [
                'form_params' => [
                    'wstoken' => $token,
                    'wsfunction' => 'mod_quiz_start_attempt',
                    'moodlewsrestformat' => 'json',
                    'quizid' => $quizId,
                    'forcenew' => 0
                ]
            ]);
            
            $data = json_decode($response->getBody(), true);
            
            if (isset($data['exception'])) {
                throw new \Exception($data['message'] ?? 'Failed to start attempt');
            }
            
            return $data['attempt'];
            
        } catch (\Exception $e) {
            Log::error('Failed to start quiz attempt:', [
                'quiz_id' => $quizId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Get quiz questions for an attempt
     */
    public function getQuizQuestions($attemptId)
    {
        $token = $this->getUserToken();
        
        try {
            $response = $this->client->post($this->apiBaseUrl, [
                'form_params' => [
                    'wstoken' => $token,
                    'wsfunction' => 'mod_quiz_get_attempt_review',
                    'moodlewsrestformat' => 'json',
                    'attemptid' => $attemptId
                ]
            ]);
            
            $data = json_decode($response->getBody(), true);
            
            if (isset($data['exception'])) {
                throw new \Exception($data['message'] ?? 'Failed to get questions');
            }
            
            return $data['questions'] ?? [];
            
        } catch (\Exception $e) {
            Log::error('Failed to get quiz questions:', [
                'attempt_id' => $attemptId,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Submit quiz attempt to Moodle
     */
    public function submitQuizAttempt($attemptId, $data)
    {
        $token = $this->getUserToken();
        
        try {
            $response = $this->client->post($this->apiBaseUrl, [
                'form_params' => [
                    'wstoken' => $token,
                    'wsfunction' => 'mod_quiz_process_attempt',
                    'moodlewsrestformat' => 'json',
                    'attemptid' => $attemptId,
                    'data' => json_encode($data),
                    'finishattempt' => 1,
                    'timeup' => 0
                ]
            ]);
            
            $result = json_decode($response->getBody(), true);
            
            if (isset($result['exception'])) {
                throw new \Exception($result['message'] ?? 'Failed to submit attempt');
            }
            
            return $result;
            
        } catch (\Exception $e) {
            Log::error('Failed to submit quiz attempt:', [
                'attempt_id' => $attemptId,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Get quiz attempt summary
     */
    public function getAttemptSummary($attemptId)
    {
        $token = $this->getUserToken();
        
        try {
            $response = $this->client->post($this->apiBaseUrl, [
                'form_params' => [
                    'wstoken' => $token,
                    'wsfunction' => 'mod_quiz_get_attempt_summary',
                    'moodlewsrestformat' => 'json',
                    'attemptid' => $attemptId
                ]
            ]);
            
            $data = json_decode($response->getBody(), true);
            
            if (isset($data['exception'])) {
                throw new \Exception($data['message'] ?? 'Failed to get attempt summary');
            }
            
            return $data;
            
        } catch (\Exception $e) {
            Log::error('Failed to get attempt summary:', [
                'attempt_id' => $attemptId,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Get course modules
     */
    public function getCourseModules($courseId)
    {
        $token = $this->getUserToken();
        
        try {
            $response = $this->client->post($this->apiBaseUrl, [
                'form_params' => [
                    'wstoken' => $token,
                    'wsfunction' => 'core_course_get_contents',
                    'moodlewsrestformat' => 'json',
                    'courseid' => $courseId
                ]
            ]);
            
            $data = json_decode($response->getBody(), true);
            
            if (isset($data['exception'])) {
                throw new \Exception($data['message'] ?? 'Failed to get course modules');
            }
            
            return $data;
            
        } catch (\Exception $e) {
            Log::error('Failed to get course modules:', [
                'course_id' => $courseId,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Test Moodle web service
     */
    public function testWebService()
    {
        $tests = [];
        
        // Test 1: Site info
        $tests['site_info'] = $this->getSiteInfo();
        
        // Test 2: Check token
        $tests['token_valid'] = !empty($this->defaultToken);
        
        // Test 3: Check if API URL is reachable
        try {
            $response = $this->client->get($this->baseUrl);
            $tests['moodle_url_reachable'] = $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            $tests['moodle_url_reachable'] = false;
            $tests['moodle_url_error'] = $e->getMessage();
        }
        
        return $tests;
    }
    
    /**
     * Send grade to Moodle Gradebook via LTI AGS
     */
    public function sendGradeToGradebook($score, $maxScore, $userId, $resourceLinkId)
    {
        // This would use LTI Advantage Assignment and Grade Services
        // Implementation depends on your LTI library
        
        Log::info('Sending grade to gradebook', [
            'score' => $score,
            'max_score' => $maxScore,
            'user_id' => $userId,
            'resource_link_id' => $resourceLinkId
        ]);
        
        return [
            'success' => true,
            'message' => 'Grade sync would be implemented here'
        ];
    }
}
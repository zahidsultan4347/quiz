<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\LtiRegistration;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

class RegisterMoodleLti extends Command
{
    protected $signature = 'lti:register-moodle 
        {issuer : Moodle URL (e.g., http://localhost/moodle)}
        {client_id : Moodle Client ID}
        {deployment_id : Deployment ID (usually 1)}
        {--key-path= : Path to private key (default: storage/keys/private.key)}
        {--kid= : Key ID (default: from config)}';
    
    protected $description = 'Register Moodle LTI 1.3 with Laravel';

    public function handle()
    {
        $this->info('🚀 Registering Moodle LTI 1.3 with Laravel...');
        
        $issuer = $this->argument('issuer');
        $clientId = $this->argument('client_id');
        $deploymentId = $this->argument('deployment_id');
        $keyPath = $this->option('key-path') ?? storage_path('keys/private.key');
        $kid = $this->option('kid') ?? config('lti.default.kid', 'moodle-quiz-lti-key');
        
        $this->info("Issuer: $issuer");
        $this->info("Client ID: $clientId");
        $this->info("Deployment ID: $deploymentId");
        
        // Check if keys exist
        if (!File::exists($keyPath)) {
            $this->error("❌ Private key not found at: $keyPath");
            $this->info("Generating LTI keys first...");
            
            // Create keys directory
            File::ensureDirectoryExists(dirname($keyPath));
            
            // Generate keys
            $this->generateLtiKeys($keyPath);
        }
        
        // Read private key
        $privateKey = File::get($keyPath);
        
        // Generate public key from private key if not exists
        $publicKeyPath = dirname($keyPath) . '/public.key';
        if (!File::exists($publicKeyPath)) {
            $this->generatePublicKey($keyPath, $publicKeyPath);
        }
        
        // Moodle endpoints (standard Moodle LTI 1.3 endpoints)
        $authEndpoint = rtrim($issuer, '/') . '/mod/lti/auth.php';
        $tokenEndpoint = rtrim($issuer, '/') . '/mod/lti/token.php';
        $keySetEndpoint = rtrim($issuer, '/') . '/mod/lti/certs.php';
        
        $this->info("\n📡 Moodle Endpoints:");
        $this->info("  Auth: $authEndpoint");
        $this->info("  Token: $tokenEndpoint");
        $this->info("  Keyset: $keySetEndpoint");
        
        // Create or update registration
        try {
            $registration = LtiRegistration::updateOrCreate(
                ['issuer' => $issuer],
                [
                    'client_id' => $clientId,
                    'platform_login_auth_endpoint' => $authEndpoint,
                    'platform_service_auth_endpoint' => $tokenEndpoint,
                    'platform_auth_provider' => $authEndpoint,
                    'platform_key_set_url' => $keySetEndpoint,
                    'tool_private_key' => $privateKey,
                    'tool_kid' => $kid,
                    'tool_oauth2_access_token_url' => url('/lti/token'),
                    'tool_oidc_auth_url' => url('/lti/login'),
                    'deployment_ids' => json_encode([$deploymentId]),
                ]
            );
            
            $this->info("\n✅ Moodle LTI registration saved successfully!");
            $this->info("   Database ID: " . $registration->id);
            
            // Display Laravel endpoints for Moodle
            $this->info("\n📋 ADD THESE TO MOODLE LTI CONFIGURATION:");
            $this->info("==========================================");
            $this->info("Tool URL: " . url('/lti/launch'));
            $this->info("Initiate Login URL: " . url('/lti/login'));
            $this->info("Redirection URI(s): " . url('/lti/launch'));
            $this->info("Public Keyset URL: " . url('/lti/.well-known/jwks.json'));
            $this->info("==========================================");
            
            $this->info("\n⚙️  CUSTOM PARAMETERS for Moodle:");
            $this->info("course_id=\$Context.id");
            $this->info("resource_link_id=\$ResourceLink.id");
            $this->info("custom_cmid=\$ResourceLink.id");
            $this->info("user_id=\$User.id");
            $this->info("lis_person_name_full=\$Person.name.full");
            $this->info("lis_person_contact_email_primary=\$Person.email.primary");
            
            $this->info("\n🎯 NEXT STEPS:");
            $this->info("1. Go to Moodle Admin → Plugins → External Tool");
            $this->info("2. Create new external tool configuration");
            $this->info("3. Use the URLs above");
            $this->info("4. Set LTI version: LTI 1.3");
            $this->info("5. Save and test!");
            
        } catch (\Exception $e) {
            $this->error("❌ Failed to save registration: " . $e->getMessage());
            return 1;
        }
        
        return 0;
    }
    
    private function generateLtiKeys($keyPath)
    {
        $this->info("Generating RSA keys for LTI 1.3...");
        
        $config = [
            "digest_alg" => "sha256",
            "private_key_bits" => 2048,
            "private_key_type" => OPENSSL_KEYTYPE_RSA,
        ];
        
        // Generate key pair
        $keyPair = openssl_pkey_new($config);
        
        if (!$keyPair) {
            $this->error("Failed to generate key pair: " . openssl_error_string());
            return false;
        }
        
        // Export private key
        if (!openssl_pkey_export($keyPair, $privateKey)) {
            $this->error("Failed to export private key: " . openssl_error_string());
            return false;
        }
        
        // Save private key
        File::put($keyPath, $privateKey);
        $this->info("✅ Private key generated: $keyPath");
        
        // Get public key
        $keyDetails = openssl_pkey_get_details($keyPair);
        $publicKey = $keyDetails["key"];
        
        // Save public key
        $publicKeyPath = dirname($keyPath) . '/public.key';
        File::put($publicKeyPath, $publicKey);
        $this->info("✅ Public key generated: $publicKeyPath");
        
        // Set permissions (for Unix systems)
        if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
            chmod($keyPath, 0600);
            chmod($publicKeyPath, 0644);
        }
        
        return true;
    }
    
    private function generatePublicKey($privateKeyPath, $publicKeyPath)
    {
        $this->info("Generating public key from private key...");
        
        // Read private key
        $privateKeyContent = File::get($privateKeyPath);
        
        // Create private key resource
        $privateKey = openssl_pkey_get_private($privateKeyContent);
        
        if (!$privateKey) {
            $this->error("Failed to read private key: " . openssl_error_string());
            return false;
        }
        
        // Get public key details
        $keyDetails = openssl_pkey_get_details($privateKey);
        
        if (!$keyDetails) {
            $this->error("Failed to get key details: " . openssl_error_string());
            return false;
        }
        
        // Save public key
        File::put($publicKeyPath, $keyDetails["key"]);
        $this->info("✅ Public key saved: $publicKeyPath");
        
        return true;
    }
}
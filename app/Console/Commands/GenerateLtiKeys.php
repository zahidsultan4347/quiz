<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use phpseclib3\Crypt\RSA;

class GenerateLtiKeys extends Command
{
    protected $signature = 'lti:generate-keys';
    protected $description = 'Generate RSA keys for LTI 1.3 using phpseclib';

    public function handle()
    {
        $privateKeyPath = storage_path('keys/private.key');
        $publicKeyPath  = storage_path('keys/public.key');

        // Create directory if it doesn't exist
        if (!File::exists(dirname($privateKeyPath))) {
            File::makeDirectory(dirname($privateKeyPath), 0755, true);
        }

        try {
            // Generate RSA key pair (works on Windows & Linux)
            $rsa = RSA::createKey(2048);

            // Export keys
            $privateKey = $rsa->toString('PKCS1'); // PRIVATE KEY
            $publicKey  = $rsa->getPublicKey()->toString('PKCS8'); // PUBLIC KEY

            // Save keys
            File::put($privateKeyPath, $privateKey);
            File::put($publicKeyPath, $publicKey);

            // Permissions (Windows ignores chmod)
            @chmod($privateKeyPath, 0600);
            @chmod($publicKeyPath, 0644);

            $this->info('✅ LTI keys generated successfully (phpseclib)');
            $this->info("Private key: $privateKeyPath");
            $this->info("Public key: $publicKeyPath");

            return Command::SUCCESS;

        } catch (\Throwable $e) {
            $this->error('❌ Key generation failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}

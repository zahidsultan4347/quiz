<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LtiRegistration extends Model
{
    use HasFactory;

    protected $fillable = [
        'issuer',
        'client_id',
        'platform_login_auth_endpoint',
        'platform_service_auth_endpoint',
        'platform_auth_provider',
        'platform_key_set_url',
        'platform_public_key',
        'tool_private_key',
        'tool_kid',
        'tool_oauth2_access_token_url',
        'tool_oidc_auth_url',
        'deployment_ids'
    ];

    protected $casts = [
        'deployment_ids' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Get private key
    public function getPrivateKey()
    {
        return $this->tool_private_key;
    }

    // Get key ID
    public function getKid()
    {
        return $this->tool_kid;
    }

    // Get platform's JWKS URL
    public function getJwksUrl()
    {
        return $this->platform_key_set_url;
    }

    // Get OIDC auth URL
    public function getAuthUrl()
    {
        return $this->platform_login_auth_endpoint;
    }

    // Get token endpoint
    public function getTokenUrl()
    {
        return $this->platform_service_auth_endpoint;
    }

    // Check if deployment ID is valid
    public function isValidDeployment($deploymentId)
    {
        $deployments = $this->deployment_ids ?? [];
        return in_array($deploymentId, $deployments);
    }
}
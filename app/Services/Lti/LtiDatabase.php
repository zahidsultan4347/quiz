<?php

namespace App\Services\Lti;

use App\Models\LtiRegistration;
use Packback\Lti1p3\Interfaces\IDatabase;

class LtiDatabase implements IDatabase
{
    public function findRegistrationByIssuer($iss, $clientId = null)
    {
        return LtiRegistration::where('issuer', $iss)
            ->when($clientId, function ($query) use ($clientId) {
                return $query->where('client_id', $clientId);
            })
            ->first();
    }

    public function findDeployment($iss, $deploymentId, $clientId = null)
    {
        $registration = $this->findRegistrationByIssuer($iss, $clientId);
        
        if (!$registration || !$registration->deployment_ids) {
            return null;
        }

        $deployments = json_decode($registration->deployment_ids, true);
        
        if (in_array($deploymentId, $deployments)) {
            return [
                'deployment_id' => $deploymentId,
                'iss' => $iss
            ];
        }

        return null;
    }

    public function getToolPrivateKey(?string $iss = null, ?string $clientId = null)
    {
        $registration = $this->findRegistrationByIssuer($iss, $clientId);
        
        if ($registration) {
            return [
                'private_key' => $registration->tool_private_key,
                'kid' => $registration->tool_kid
            ];
        }

        // Return default key if no registration found
        return [
            'private_key' => config('lti.default_private_key'),
            'kid' => config('lti.default_kid')
        ];
    }
}
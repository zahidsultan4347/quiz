<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lti_registrations', function (Blueprint $table) {
            $table->id();
            $table->string('issuer', 191);
            $table->string('client_id', 191)->nullable();
            $table->string('platform_login_auth_endpoint', 191);
            $table->string('platform_service_auth_endpoint', 191);
            $table->string('platform_auth_provider', 191);
            $table->string('platform_key_set_url', 191);
            $table->text('platform_public_key')->nullable();
            $table->text('tool_private_key');
            $table->string('tool_kid', 191);
            $table->string('tool_oauth2_access_token_url', 191);
            $table->string('tool_oidc_auth_url', 191);
            $table->text('deployment_ids')->nullable(); // JSON -> text for older MySQL
            $table->timestamps();
          
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lti_registrations');
    }
};


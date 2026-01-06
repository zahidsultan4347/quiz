<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Add moodle_user_id if it doesn't exist
            if (!Schema::hasColumn('users', 'moodle_user_id')) {
                $table->string('moodle_user_id')->nullable()->unique()->after('email');
            }
            
            // Add other LTI-related columns
            if (!Schema::hasColumn('users', 'lti_context_id')) {
                $table->string('lti_context_id')->nullable()->after('moodle_user_id');
            }
            
            if (!Schema::hasColumn('users', 'lti_roles')) {
                $table->json('lti_roles')->nullable()->after('lti_context_id');
            }
            
            if (!Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->after('remember_token');
            }
            
            if (!Schema::hasColumn('users', 'last_activity_at')) {
                $table->timestamp('last_activity_at')->nullable()->after('last_login_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['moodle_user_id', 'lti_context_id', 'lti_roles', 'last_login_at', 'last_activity_at']);
        });
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_auto_saves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_attempt_id')->constrained()->onDelete('cascade');
            $table->json('answers_snapshot');
            $table->integer('time_remaining');
            $table->integer('current_question_index');
            $table->string('session_id');
            $table->string('ip_address')->nullable();
            $table->timestamps();
            
            $table->index(['quiz_attempt_id', 'created_at']);
            $table->index(['session_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_auto_saves');
    }
};
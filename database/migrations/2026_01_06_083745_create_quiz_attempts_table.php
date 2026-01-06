<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('quiz_id')->constrained()->onDelete('cascade');
            $table->integer('moodle_attempt_id')->nullable();
            $table->integer('moodle_quiz_id');
            $table->integer('moodle_course_id');
            $table->integer('moodle_cmid');
            $table->enum('status', ['in_progress', 'submitted', 'time_up', 'abandoned', 'needs_grading'])->default('in_progress');
            $table->json('questions')->nullable(); // Store question data
            $table->json('answers')->nullable(); // Store user answers
            $table->float('grade', 5, 2)->nullable();
            $table->float('max_grade', 5, 2);
            $table->datetime('started_at');
            $table->datetime('ended_at')->nullable();
            $table->integer('time_limit_minutes')->default(45);
            $table->integer('time_remaining_seconds')->nullable();
            $table->boolean('is_disconnected')->default(false);
            $table->datetime('last_sync_at')->nullable();
            $table->timestamps();
            
            $table->unique(['user_id', 'moodle_quiz_id', 'moodle_attempt_id']);
            $table->index(['status', 'ended_at']);
            $table->index(['quiz_id', 'user_id']);
            $table->index(['moodle_quiz_id', 'moodle_course_id']);
            $table->index(['last_sync_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_attempts');
    }
};
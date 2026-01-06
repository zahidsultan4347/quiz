<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();
            $table->integer('moodle_quiz_id')->unique();
            $table->integer('moodle_course_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('time_limit_minutes')->default(45);
            $table->float('max_grade', 5, 2)->default(100);
            $table->integer('attempts_allowed')->default(1);
            $table->datetime('available_from')->nullable();
            $table->datetime('available_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('questions_data')->nullable(); // Store quiz questions from Moodle
            $table->timestamps();
            
            $table->index(['moodle_course_id', 'is_active']);
            $table->index(['available_from', 'available_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quizzes');
    }
};
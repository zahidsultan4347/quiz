<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_attempt_id')->constrained()->onDelete('cascade');
            $table->foreignId('quiz_question_id')->constrained()->onDelete('cascade');
            $table->text('answer_text')->nullable();
            $table->json('answer_data')->nullable(); // For complex answers
            $table->float('grade', 5, 2)->nullable();
            $table->text('feedback')->nullable();
            $table->integer('time_spent_seconds')->default(0);
            $table->integer('sequence_number')->default(0);
            $table->boolean('is_correct')->nullable();
            $table->boolean('is_graded')->default(false);
            $table->timestamps();
            
            $table->unique(['quiz_attempt_id', 'quiz_question_id']);
            $table->index(['quiz_attempt_id', 'sequence_number']);
            $table->index(['is_graded', 'grade']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_answers');
    }
};
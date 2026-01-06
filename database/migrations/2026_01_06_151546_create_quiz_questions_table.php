<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->onDelete('cascade');
            $table->integer('moodle_question_id');
            $table->string('type'); // multiplechoice, truefalse, shortanswer, essay, etc.
            $table->text('question_text');
            $table->float('default_grade', 5, 2)->default(1);
            $table->json('answers')->nullable(); // Store answer options for MC/TF
            $table->string('correct_answer')->nullable(); // For auto-grading
            $table->text('feedback')->nullable();
            $table->integer('sequence_number')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['quiz_id', 'moodle_question_id']);
            $table->index(['quiz_id', 'sequence_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_questions');
    }
};
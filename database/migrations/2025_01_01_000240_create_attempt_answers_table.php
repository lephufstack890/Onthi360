<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attempt_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained('attempts')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('questions')->cascadeOnDelete();
            $table->json('answer')->nullable(); // đáp án mcq/fill_blank
            $table->longText('code_source')->nullable(); // nháp độc lập theo câu cho coding (6.3)
            $table->string('language', 30)->nullable();
            $table->string('verdict', 30)->default('pending'); // Enums\VerdictStatus
            $table->decimal('score', 8, 2)->nullable();
            $table->dateTime('graded_at')->nullable();
            $table->unsignedInteger('submission_count')->default(0);
            $table->timestamps();

            $table->unique(['attempt_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attempt_answers');
    }
};

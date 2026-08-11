<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('draft_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uploaded_document_id')->constrained('uploaded_documents')->cascadeOnDelete();
            $table->unsignedInteger('order')->default(0);
            $table->string('type_guess', 20)->nullable(); // Enums\QuestionType — dự đoán, có thể sai
            $table->longText('raw_text')->nullable();
            $table->json('structured_draft')->nullable(); // kết quả phân rã có thể sửa ở màn rà soát
            $table->string('confidence', 20)->default('unknown'); // high|low|unknown — gắn cờ "Cần rà soát" (6.4)
            $table->string('review_status', 20)->default('pending'); // Enums\DraftQuestionReviewStatus
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('promoted_question_id')->nullable()->constrained('questions')->nullOnDelete();
            $table->timestamps();

            $table->index(['uploaded_document_id', 'order']);
            $table->index('review_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('draft_questions');
    }
};

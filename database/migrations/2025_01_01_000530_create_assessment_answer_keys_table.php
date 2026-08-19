<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Đáp án đúng từng câu của đề PDF (Assessment::content_mode = pdf_answer_sheet) — KHÔNG
 * liên quan tới assessment_items (bảng đó gắn Question thật, chỉ dùng cho content_mode =
 * structured). Đánh số question_no theo đúng thứ tự in trên đề (Câu 1, Câu 2...), không
 * theo id. correct_answer là JSON vì hình dạng khác nhau theo question_type — xem comment
 * ở App\Enums\AnswerSheetQuestionType.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_answer_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained('assessments')->cascadeOnDelete();
            $table->unsignedInteger('question_no');
            $table->string('question_type', 20); // Enums\AnswerSheetQuestionType
            $table->json('correct_answer');
            $table->unsignedInteger('points')->default(0);
            $table->timestamps();

            $table->unique(['assessment_id', 'question_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_answer_keys');
    }
};

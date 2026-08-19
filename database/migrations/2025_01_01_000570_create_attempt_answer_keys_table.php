<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 19/8 (Giai đoạn 2 — học sinh làm đề PDF, 16/8 mục 1.2/6): bảng câu trả lời của học
 * sinh cho TỪNG CÂU trong phiếu trả lời của đề PDF (App\Models\AssessmentAnswerKey) — song
 * song với "attempt_answers" (dùng cho content_mode=structured, khoá theo question_id) chứ
 * KHÔNG dùng lại bảng đó, vì đề PDF không có Question nào cả (attempt_answers.question_id
 * bắt buộc NOT NULL + khoá ngoại tới bảng questions, không gắn được với answer_key_id).
 * App\Models\Attempt dùng CHUNG cho cả 2 chế độ — không đổi gì ở bảng attempts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attempt_answer_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained('attempts')->cascadeOnDelete();
            $table->foreignId('answer_key_id')->constrained('assessment_answer_keys')->cascadeOnDelete();
            // Hình dạng khớp AssessmentAnswerKey::isCorrect(): chuỗi (single_choice/short_answer)
            // hoặc object {"a":bool,...} (true_false_group).
            $table->json('submitted_answer')->nullable();
            $table->boolean('is_correct')->nullable();
            $table->decimal('score', 8, 2)->nullable();
            $table->dateTime('graded_at')->nullable();
            $table->timestamps();

            $table->unique(['attempt_id', 'answer_key_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attempt_answer_keys');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 19/8 (Giai đoạn 2 — học sinh làm đề PDF, 16/8 mục 1.2/6): bài nộp code của học sinh
 * cho 1 bài lập trình con trong đề PDF (App\Models\AssessmentCodingItem) — song song với
 * attempt_answers (dùng cho câu Coding kiểu content_mode=structured), vì AssessmentCodingItem
 * không phải Question. Verdict luôn dừng ở "queued" — hệ thống CHƯA có sandbox chấm code
 * thật (đúng giới hạn hiện tại của attempt_answers/OJ, xem App\Services\AttemptService).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attempt_coding_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained('attempts')->cascadeOnDelete();
            $table->foreignId('coding_item_id')->constrained('assessment_coding_items')->cascadeOnDelete();
            $table->longText('code_source')->nullable();
            $table->string('language', 30)->nullable();
            $table->string('verdict', 30)->default('queued'); // Enums\VerdictStatus
            $table->decimal('score', 8, 2)->nullable();
            $table->dateTime('graded_at')->nullable();
            $table->unsignedInteger('submission_count')->default(0);
            $table->timestamps();

            $table->unique(['attempt_id', 'coding_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attempt_coding_items');
    }
};

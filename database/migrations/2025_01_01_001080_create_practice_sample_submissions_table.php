<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 7/10 (khách: "admin có thể chỉ định bài mẫu — chỉ admin mới có quyền này; bài nào được chỉ
 * định thì đổ vào tab Bài mẫu của bài đó") — bảng lưu BÀI MẪU do admin chỉ định cho một bài tập,
 * dựng theo education-main (designateSample trong submissionHistory.js, nhưng lưu ở máy chủ).
 *
 * Mỗi bài tập có tối đa MỘT bài mẫu (unique question_id): chỉ định lại là thay bài mẫu cũ.
 *
 * LƯU BẢN CHỤP nội dung (code, ngôn ngữ, tên người nộp) chứ không chỉ trỏ tới lượt nộp: học sinh
 * nộp lại thì dòng attempt_answers bị ghi đè, hoặc tài khoản bị xoá — bài mẫu không được đổi/mất
 * theo. attempt_answer_id chỉ để đánh dấu "lượt nộp nào" trên trang Nhật ký, không có khoá ngoại.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('practice_sample_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('questions')->cascadeOnDelete();
            $table->unsignedBigInteger('attempt_answer_id')->nullable();
            $table->string('submitter_name', 150)->nullable();
            $table->string('language', 40)->nullable();
            $table->longText('code');
            $table->foreignId('designated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('question_id', 'practice_sample_submissions_question_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('practice_sample_submissions');
    }
};

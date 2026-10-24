<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 24/10 (khách: "giáo viên / admin thiết lập đề sinh ngẫu nhiên; 1 kỳ thi có thể gồm nhiều đề khác nhau") —
 * ĐỀ SINH TỪ MA TRẬN của một kỳ thi (source mới: SurveyPaperBuilder / surveyMatrix / surveyPapers).
 *
 * Mỗi dòng = 1 đề của kỳ thi (Đề 1, Đề 2 …). Chốt đề thì hệ thống tạo / cập nhật một Assessment thật (loại Đề thi, câu
 * hỏi rời, 100 điểm) nên làm bài, chấm, bảng xếp hạng chạy NGUYÊN như đề thường. Học sinh được giao ngẫu nhiên 1 đề
 * đã chốt, giữ cố định cho mọi lượt làm lại (competition_registrations.exam_forms["paper:<id kỳ>"]).
 *
 *   matrix            ma trận: chuyên đề / độ khó / số câu / điểm + nguồn, tag, cách chọn câu, khoảng điểm gốc
 *   exclude_used      không lấy câu đã từng chốt ở kỳ khảo sát khác
 *   draft             bản nháp {questions: [{id, points, row}]} — chỉ giáo viên thấy
 *   used_question_ids mọi câu từng chốt ở đề này (kể cả bản bị thay) — để đếm "đã dùng ở kỳ khác"
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('competition_exam_papers')) {
            return;
        }

        Schema::create('competition_exam_papers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_exam_id')->constrained('competition_exams')->cascadeOnDelete();
            $table->string('label', 120);
            $table->foreignId('assessment_id')->nullable()->constrained('assessments')->nullOnDelete();
            $table->json('matrix')->nullable();
            $table->boolean('exclude_used')->default(true);
            $table->json('draft')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->default(45);
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('version')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('used_question_ids')->nullable();
            $table->timestamps();

            $table->index(['competition_exam_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_exam_papers');
    }
};

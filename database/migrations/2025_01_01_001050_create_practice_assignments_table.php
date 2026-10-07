<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 7/10 (khách: "giao diện học sinh có thêm phần bài được giao, giáo viên giao bài và xem nhật
 * ký nộp bài") — bảng lưu LƯỢT GIAO RIÊNG từng học sinh ở trang Luyện tập.
 *
 * Khác bảng `assignments` (giao theo LỚP, kèm khung giờ thi, ca thi, luật...): lượt giao ở đây
 * là "thầy/cô chọn một bài hoặc một đề luyện tập, giao cho đúng một học sinh, kèm hạn nộp" —
 * đúng như nút "Giao bài"/"Giao đề" của bản mẫu (education-main/QuickAssignButton). Hai thứ có
 * vòng đời khác nhau nên tách bảng, không nhồi chung vào `assignments`.
 *
 * subject_id trỏ tới questions.id (type='problem') hoặc assessments.id (type='exam'). Không đặt
 * khoá ngoại vì một cột trỏ được hai bảng; toàn vẹn do PracticeAssignmentService giữ khi tạo.
 *
 * Giao lại cùng một (người giao, học sinh, loại, nội dung) thì CẬP NHẬT hạn nộp chứ không sinh
 * thêm dòng — ràng buộc unique bên dưới bảo đảm điều đó kể cả khi bấm đúp.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('practice_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assigned_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 10); // 'problem' | 'exam'
            $table->unsignedBigInteger('subject_id');
            $table->dateTime('deadline_at');
            $table->timestamps();

            $table->unique(['assigned_by', 'student_id', 'type', 'subject_id'], 'practice_assignments_unique_slot');
            $table->index(['student_id', 'type'], 'practice_assignments_student_idx');
            $table->index(['assigned_by', 'type'], 'practice_assignments_teacher_idx');
            $table->index(['type', 'subject_id'], 'practice_assignments_subject_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('practice_assignments');
    }
};

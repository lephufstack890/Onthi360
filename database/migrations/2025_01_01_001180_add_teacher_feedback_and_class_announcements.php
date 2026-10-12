<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 12/10 — khu Giáo viên dựng lại theo source mới (education-main: TeacherWorkPanel,
 * TeacherNoticePanel):
 *  - practice_assignments.feedback / feedback_at: nhận xét của giáo viên cho bài nộp (mục "Kết quả & nhận xét").
 *  - class_announcements: thông báo lớp học có bản nháp / đã đăng (mục "Thông báo").
 * Cả hai đều có kiểm tra tồn tại để chạy lại không lỗi.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('practice_assignments') && ! Schema::hasColumn('practice_assignments', 'feedback')) {
            Schema::table('practice_assignments', function (Blueprint $table) {
                $table->text('feedback')->nullable();
                $table->timestamp('feedback_at')->nullable();
            });
        }

        if (! Schema::hasTable('class_announcements')) {
            Schema::create('class_announcements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('class_room_id')->constrained('class_rooms')->cascadeOnDelete();
                $table->string('audience', 20)->default('students'); // students | families
                $table->string('title', 120)->nullable();
                $table->text('body')->nullable();
                $table->string('state', 20)->default('draft');       // draft | published
                $table->unsignedInteger('student_count')->default(0);
                $table->unsignedInteger('parent_count')->default(0);
                $table->timestamp('published_at')->nullable();
                $table->timestamps();

                $table->index(['teacher_id', 'state']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('class_announcements');

        if (Schema::hasTable('practice_assignments') && Schema::hasColumn('practice_assignments', 'feedback')) {
            Schema::table('practice_assignments', function (Blueprint $table) {
                $table->dropColumn(['feedback', 'feedback_at']);
            });
        }
    }
};

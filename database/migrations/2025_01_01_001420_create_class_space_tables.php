<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 6/11 (khách: "lớp phải là một giao diện riêng, 8 tab") — 2 bảng mới cho không gian lớp:
 *  · class_progress_steps — tab "Tiến trình dạy học": các bước theo thứ tự, mỗi bước gắn tài liệu / bài tập của lớp,
 *    giáo viên mở khoá từng bước (hoặc hẹn ngày tự mở); học sinh chỉ thấy bước đã mở.
 *  · class_grades — tab "Kết quả học tập": điểm giáo viên chấm / sửa (thang 10) + nhận xét cho từng bài; dòng
 *    assignment_id = NULL là NHẬN XÉT CHUNG của học sinh trong lớp.
 * Giáo viên đứng lớp cùng dùng lại bảng class_teachers có sẵn (role: main | co_teacher | assistant) — không cần bảng mới.
 * Chạy lại nhiều lần không lỗi.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('class_progress_steps')) {
            Schema::create('class_progress_steps', function (Blueprint $table) {
                $table->id();
                $table->foreignId('class_room_id')->constrained('class_rooms')->cascadeOnDelete();
                $table->unsignedInteger('position')->default(1);
                $table->string('title', 200);
                $table->text('description')->nullable();
                $table->json('items')->nullable();            // [{"type":"material","id":12},{"type":"assignment","id":5}]
                $table->boolean('is_unlocked')->default(false);
                $table->timestamp('unlocked_at')->nullable();
                $table->date('unlock_on')->nullable();        // hẹn ngày tự mở khoá
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['class_room_id', 'position']);
            });
        }

        if (! Schema::hasTable('class_grades')) {
            Schema::create('class_grades', function (Blueprint $table) {
                $table->id();
                $table->foreignId('class_room_id')->constrained('class_rooms')->cascadeOnDelete();
                $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('assignment_id')->nullable()->constrained('assignments')->cascadeOnDelete();
                $table->decimal('score', 4, 2)->nullable();   // thang 10, NULL = dùng điểm tự động của bài làm
                $table->text('comment')->nullable();
                $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['class_room_id', 'student_id', 'assignment_id'], 'class_grades_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('class_grades');
        Schema::dropIfExists('class_progress_steps');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 2/11 (khách: "ai tổ chức kỳ thi / kỳ khảo sát thì chỉ người đó (và admin) được sửa; giáo viên khác muốn can thiệp
 * phải được người tạo add vào. Kỳ kết thúc mà có học sinh quên làm thì người tổ chức / admin add học sinh vào làm rồi xếp
 * hạng như bình thường").
 *
 *  · competition_exam_managers — giáo viên CÙNG TỔ CHỨC một kỳ (do người chủ trì kỳ hoặc admin thêm vào).
 *  · competition_exam_makeups  — học sinh được LÀM BÙ một kỳ đã kết thúc, đến hạn deadline_at. Bài làm bù ghi vào đúng kỳ
 *    (competition_exam_id) nên được chấm, xếp hạng, in bảng điểm như mọi bài khác.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('competition_exam_managers')) {
            Schema::create('competition_exam_managers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('competition_exam_id')->constrained('competition_exams')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['competition_exam_id', 'user_id'], 'cem_exam_user_unique');
            });
        }

        if (! Schema::hasTable('competition_exam_makeups')) {
            Schema::create('competition_exam_makeups', function (Blueprint $table) {
                $table->id();
                $table->foreignId('competition_exam_id')->constrained('competition_exams')->cascadeOnDelete();
                $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('deadline_at');
                $table->timestamps();
                $table->unique(['competition_exam_id', 'student_id'], 'cemk_exam_student_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_exam_makeups');
        Schema::dropIfExists('competition_exam_managers');
    }
};

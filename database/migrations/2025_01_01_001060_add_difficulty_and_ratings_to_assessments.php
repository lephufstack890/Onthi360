<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 7/10 (khách: "thiếu Độ khó, số sao đánh giá, tỉnh thành khu vực — check kỹ source mới") —
 * hai thứ thẻ đề / màn chi tiết đề của bản mẫu cần mà đề thi chưa có chỗ lưu:
 *
 *   · assessments.difficulty_level  độ khó 1-5 (cùng thang với câu hỏi: Cơ bản · Dễ · Khá · Khó ·
 *                                  Rất khó, xem App\Support\QuestionDifficulty). Nullable: đề cũ
 *                                  chưa xếp thì hiện "Chưa xếp độ khó" đúng như bản mẫu.
 *   · assessment_ratings           điểm sao 1-5 do HỌC SINH ĐÃ NỘP ĐỀ chấm. Mỗi người một dòng cho
 *                                  mỗi đề (đổi ý thì ghi đè). Điểm trung bình + số lượt được TÍNH
 *                                  từ bảng này — không có ô nhập tay, vì sao đánh giá do admin gõ
 *                                  vào thì là số bịa. Chưa ai chấm thì hiện "Chưa có đánh giá".
 *
 * Khu vực (Miền Bắc/Trung/Nam) KHÔNG thêm cột: suy ra từ assessments.province, xem
 * ProvinceCatalog::region().
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('assessments', 'difficulty_level')) {
            Schema::table('assessments', function (Blueprint $table) {
                $table->unsignedTinyInteger('difficulty_level')->nullable()->after('exam_category');
            });
        }

        if (! Schema::hasTable('assessment_ratings')) {
            Schema::create('assessment_ratings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('assessment_id')->constrained('assessments')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedTinyInteger('rating'); // 1-5
                $table->timestamps();

                $table->unique(['assessment_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_ratings');

        if (Schema::hasColumn('assessments', 'difficulty_level')) {
            Schema::table('assessments', function (Blueprint $table) {
                $table->dropColumn('difficulty_level');
            });
        }
    }
};

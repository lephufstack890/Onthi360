<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 25/10 (khách: "UI phòng thi theo source mới — Các kỳ tôi tổ chức, mỗi kỳ có người chủ trì, nguồn đề có sẵn /
 * nguồn câu hỏi") — 2 cột cho mỗi kỳ thi (competition_exams):
 *
 *   organizer_id : giáo viên / admin CHỦ TRÌ kỳ này (người tạo kỳ). Giáo viên chỉ thấy & quản lý các kỳ mình chủ trì.
 *                  Kỳ cũ để trống = do ban tổ chức của cuộc thi quản lý như trước.
 *   source_type  : nguồn đề — 'exam' (lấy đề có sẵn trong ngân hàng) | 'questions' (thiết lập ma trận từ nguồn câu hỏi,
 *                  chỉ khảo sát). Để trống = suy ra từ dữ liệu cũ (xem CompetitionExam::sourceType()).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('competition_exams', 'organizer_id')) {
            Schema::table('competition_exams', function (Blueprint $table) {
                $table->foreignId('organizer_id')->nullable()->constrained('users')->nullOnDelete();
            });
        }
        if (! Schema::hasColumn('competition_exams', 'source_type')) {
            Schema::table('competition_exams', function (Blueprint $table) {
                $table->string('source_type', 20)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('competition_exams', 'source_type')) {
            Schema::table('competition_exams', function (Blueprint $table) {
                $table->dropColumn('source_type');
            });
        }
        if (Schema::hasColumn('competition_exams', 'organizer_id')) {
            Schema::table('competition_exams', function (Blueprint $table) {
                $table->dropConstrainedForeignId('organizer_id');
            });
        }
    }
};

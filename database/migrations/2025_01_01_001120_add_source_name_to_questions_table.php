<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 10/10 (khách: "thêm 1 field Nguồn cho nhập vào ở chỗ thêm/cập nhật câu hỏi của admin và giáo
 * viên; ngoài trang luyện tập public chỗ nguồn thì đổ field này ra") — ô văn bản tự do ghi nguồn của
 * câu hỏi (vd "Đề thi HSG tỉnh Nghệ An 2024", "Sách Chuyên đề DP — trang 45").
 *
 * Nullable: câu cũ chưa khai báo thì trang Luyện tập vẫn hiện nhãn mặc định như trước. Không backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->string('source_name', 255)->nullable()->after('exam_year');
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn('source_name');
        });
    }
};

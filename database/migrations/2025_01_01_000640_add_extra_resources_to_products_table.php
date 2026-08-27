<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // SỬA 27/8 ("4 file đính kèm sản phẩm" — khách chốt: mỗi sản phẩm đúng 1 file/loại,
            // upload mới thì thay file cũ). File PDF nội dung chính ("sách") giữ NGUYÊN cơ chế
            // Material hiện có (cây chương/mục) — 3 cột dưới đây là 3 tài nguyên MỚI, gắn
            // thẳng vào Product vì không cần chia chương/mục, đọc tuần tự như Material.
            // Lưu ở disk 'local' (riêng tư) giống Material::pdf_path — quyền tải/xem qua
            // AccessGateService::canAccessProduct(), không có URL công khai trỏ thẳng vào file.
            $table->string('guide_pdf_path')->nullable()->after('has_print_option');
            $table->string('guide_pdf_original_name')->nullable()->after('guide_pdf_path');
            $table->string('exercise_zip_path')->nullable()->after('guide_pdf_original_name');
            $table->string('exercise_zip_original_name')->nullable()->after('exercise_zip_path');
            $table->string('media_path')->nullable()->after('exercise_zip_original_name');
            $table->string('media_original_name')->nullable()->after('media_path');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'guide_pdf_path', 'guide_pdf_original_name',
                'exercise_zip_path', 'exercise_zip_original_name',
                'media_path', 'media_original_name',
            ]);
        });
    }
};

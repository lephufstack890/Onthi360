<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Thêm "mã bài" (code) + file PDF nội dung cho từng Material (chương/bài trong Sách, Chuyên
 * đề, Đề thi) — trước đây Material chỉ có tên chương làm mục lục, chưa có chỗ lưu nội dung
 * thật để đọc. Khách yêu cầu (25/8): tải bài theo 2 cơ chế (từng bài / hàng loạt qua ZIP), mã
 * bài có thể tự gõ hoặc lấy từ tên tệp trong ZIP.
 *
 * code: cho phép NULL (dữ liệu cũ chưa có mã vẫn hợp lệ) — duy nhất TRONG PHẠM VI 1 sản phẩm
 * (product_id, code), KHÔNG duy nhất toàn hệ thống — 2 quyển sách khác nhau được phép dùng
 * trùng mã bài (khác Question.code vốn duy nhất toàn hệ thống). Nhiều bản ghi cùng NULL vẫn
 * hợp lệ với unique index (chuẩn chung của MySQL/Postgres/SQLite: NULL không so trùng NULL).
 *
 * pdf_path: lưu ở disk 'local' (riêng tư, giống assessments.pdf_path) — KHÔNG dùng disk
 * 'public' như cover_image_path, vì đây là nội dung phải trả phí/kích hoạt mới xem được, không
 * phải ảnh bìa công khai. Trang "đọc bài" (nếu làm sau này) phải tự kiểm tra quyền sở hữu rồi
 * mới stream file, không được lộ URL public thẳng ra ngoài.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->string('code', 60)->nullable()->after('title');
            $table->string('pdf_path')->nullable()->after('code');
            $table->string('pdf_original_name')->nullable()->after('pdf_path');

            $table->unique(['product_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropUnique(['product_id', 'code']);
            $table->dropColumn(['code', 'pdf_path', 'pdf_original_name']);
        });
    }
};

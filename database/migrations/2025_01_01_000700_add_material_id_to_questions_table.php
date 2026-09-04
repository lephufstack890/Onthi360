<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 4/9 (khách yêu cầu "Chương/Phần/Đề" — 1 bài tập biết thuộc chương nào nếu là Sách,
 * thuộc phần nào nếu là Chuyên đề, thuộc đề nào nếu là Bộ đề): tái dùng NGUYÊN hệ Học liệu
 * (materials) có sẵn — record materials.type='chapter' đã có nhãn "Chương" làm mục lục cho
 * 1 sản phẩm (xem ContentService::MATERIAL_TYPE_LABELS), KHÔNG tạo bảng mới. Cột này chỉ là
 * 1 liên kết optional từ Question -> đúng 1 materials.id (loại chapter) CÙNG product_id với
 * câu hỏi đó — validate sự khớp product_id ở tầng ứng dụng (ContentService), không ràng buộc
 * được bằng FK đơn thuần.
 *
 * Nullable vì: (1) câu hỏi dùng chung (product_id=null, Kho câu hỏi) không có khái niệm
 * chương/phần/đề; (2) sản phẩm loại "Khóa học" (course) không dùng Chương/Phần/Đề theo yêu
 * cầu khách (chỉ Sách/Chuyên đề/Bộ đề mới có); (3) dữ liệu bài tập cũ đã có trước migration
 * này vẫn hợp lệ, không bắt buộc gán lại.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->foreignId('material_id')
                ->nullable()
                ->after('product_id')
                ->constrained('materials')
                ->nullOnDelete();

            $table->index('material_id');
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('material_id');
        });
    }
};

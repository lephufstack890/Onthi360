<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * SỬA 31/8 (khách yêu cầu — "chỗ Học liệu trong lớp nên gắn CẢ sản phẩm: sách/chuyên
     * đề/bộ đề, không chỉ 1 chương lẻ"): material_id giờ CÓ THỂ null — 1 dòng
     * class_materials với material_id=null + product_id=X nghĩa là "gắn NGUYÊN sản phẩm X
     * vào lớp" (khác dòng cũ material_id=Y nghĩa là chỉ gắn 1 chương/mục Y trong sản phẩm).
     *
     * Không đụng vào unique(class_room_id, material_id) đã có sẵn từ migration
     * create_class_materials_table — MySQL coi 2 giá trị NULL là KHÁC NHAU trong unique
     * index nên nhiều dòng material_id=null cùng 1 class_room_id không vi phạm gì; chống
     * gắn trùng 1 sản phẩm 2 lần xử lý ở tầng ứng dụng
     * (Teacher\ClassRoomService::attachProduct() tự kiểm tra tồn tại trước khi insert),
     * giống hệt cách attachMaterial() cũ đã làm — không cần đổi cấu trúc index.
     *
     * doctrine/dbal chưa cài trong dự án này nên không dùng được ->nullable()->change() của
     * Schema Builder — ALTER TABLE thẳng bằng SQL thô (an toàn cho MySQL: chỉ đổi
     * nullability, không đụng khoá ngoại đã khai báo).
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE class_materials MODIFY material_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        // Xoá trước các dòng "gắn nguyên sản phẩm" (material_id=null) để MODIFY về NOT NULL
        // không lỗi giữa chừng — chấp nhận mất các gắn kết loại này khi rollback (đúng bản
        // chất rollback: quay lại đúng ràng buộc DB trước khi có migration này).
        DB::table('class_materials')->whereNull('material_id')->delete();

        DB::statement('ALTER TABLE class_materials MODIFY material_id BIGINT UNSIGNED NOT NULL');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 1/10 (khách: "thêm 1 cái field nữa cho chọn tỉnh thành và năm nha rồi ngoài trang luyện
 * tập public thêm 2 cột tỉnh thành và năm luôn nha. Chỗ lọc danh sách trong admin cũng cho lọc
 * theo tỉnh thành và năm luôn nha") — 2 cột phân loại mới cho Kho câu hỏi.
 *
 * Vì sao là CỘT RIÊNG chứ không nhét vào questions.metadata (JSON): y nguyên lý do đã viết ở
 * add_subject_grade_to_questions_table — metadata là JSON tự do và CHỈ câu nhập từ ZIP mới có,
 * lọc theo JSON vừa chậm (MySQL cũ không index được) vừa bỏ sót toàn bộ câu tạo tay. Hai cột này
 * có index để bộ lọc ở admin/giáo viên và 2 cột mới ngoài trang Luyện tập chạy nhanh.
 *
 * - province: MÃ tỉnh thành (khoá của App\Support\ProvinceCatalog, vd "HANOI", "HAIDUONG"),
 *   KHÔNG phải nhãn tiếng Việt. Danh mục gồm 34 đơn vị hiện hành + 29 tên cũ trước sáp nhập
 *   2025 (kho này đầy đề thi các năm trước mang tên tỉnh đã sáp nhập).
 * - exam_year: năm của đề/câu hỏi. smallint đủ (2005..~2030), không cần int 4 byte.
 *
 * Cả 2 đều nullable: câu hỏi cũ chưa gán và câu nhập từ nguồn không khai báo vẫn hợp lệ — chúng
 * rơi vào nhóm "Chưa gán" của bộ lọc để gán dần. KHÔNG có lệnh backfill: không suy ra được
 * tỉnh/năm từ dữ liệu sẵn có mà không đoán bừa (mã câu hỏi hiện không mã hoá tỉnh/năm).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->string('province', 20)->nullable()->after('grade');
            $table->unsignedSmallInteger('exam_year')->nullable()->after('province');

            $table->index(['province', 'exam_year']);
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropIndex(['province', 'exam_year']);
            $table->dropColumn(['province', 'exam_year']);
        });
    }
};

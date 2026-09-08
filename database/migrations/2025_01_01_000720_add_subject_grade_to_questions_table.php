<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 8/9 (3) (khách: "kho câu hỏi giờ làm sao để phân loại được các môn... để một đống câu hỏi
 * như này không ổn") — thêm 2 cột phân loại cho Kho câu hỏi.
 *
 * Vì sao là CỘT RIÊNG chứ không đọc thẳng questions.metadata (đã có taxonomy của gói ZIP):
 * metadata là JSON tự do và CHỈ câu nhập từ ZIP mới có — lọc/đếm theo JSON vừa chậm (không
 * index được ở MySQL cũ) vừa bỏ sót toàn bộ câu tạo tay. 2 cột này có index để lọc nhanh, và
 * là chỗ DUY NHẤT nơi hiển thị/bộ lọc đọc tới.
 *
 * - subject: MÃ môn (khoá của App\Support\SubjectCatalog::SUBJECTS, vd "TOAN"), không phải nhãn
 *   tiếng Việt — đổi nhãn hiển thị sau này không phải sửa dữ liệu.
 * - grade: khối lớp 6-12.
 *
 * Cả 2 đều nullable: câu hỏi cũ chưa gán, và câu nhập từ nguồn không khai báo môn/khối, vẫn hợp
 * lệ — chúng rơi vào nhóm "Chưa phân loại" của bộ lọc để gán lại dần (chạy
 * `php artisan questions:backfill-subject --all` để tự điền phần đoán được).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->string('subject', 20)->nullable()->after('title');
            $table->unsignedTinyInteger('grade')->nullable()->after('subject');

            $table->index(['subject', 'grade']);
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropIndex(['subject', 'grade']);
            $table->dropColumn(['subject', 'grade']);
        });
    }
};

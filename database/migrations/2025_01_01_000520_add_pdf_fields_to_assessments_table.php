<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 18/8 (đề PDF + phiếu đáp án, theo file khách chốt "chốt chức năng đề luyện tập tài
 * liệu"): thêm cột cho Assessment ở chế độ content_mode=pdf_answer_sheet — không đụng gì
 * tới các cột cũ, đề kiểu Structured (Luyện tập theo câu) vẫn dùng y nguyên cột total_points/
 * duration_minutes/status có sẵn, chỉ bỏ trống các cột mới này.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->string('content_mode', 20)->default('structured')->after('type'); // Enums\AssessmentContentMode

            // "Mã đề" (16/8 mục 2/4/5) — chỉ đề PDF mới cần, để nhận diện/mở đúng đề khi đề
            // nằm trong Bộ đề hoặc được chọn vào Kỳ thi/Cuộc thi. Nullable vì đề Structured
            // không cần.
            $table->string('exam_code', 40)->nullable()->unique()->after('content_mode');

            $table->string('pdf_path')->nullable()->after('exam_code');
            $table->string('pdf_original_name')->nullable()->after('pdf_path');
            $table->string('solution_pdf_path')->nullable()->after('pdf_original_name'); // PDF hướng dẫn giải

            // Xem thử trước khi mua (16/8 mục 3/4): Admin đánh dấu 1 khoảng trang được xem
            // dù đề đang khoá. Null cả 2 = không cho xem thử trang nào.
            $table->unsignedInteger('preview_page_from')->nullable()->after('solution_pdf_path');
            $table->unsignedInteger('preview_page_to')->nullable()->after('preview_page_from');

            $table->index('content_mode');
        });
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->dropIndex(['content_mode']);
            $table->dropColumn([
                'content_mode', 'exam_code', 'pdf_path', 'pdf_original_name',
                'solution_pdf_path', 'preview_page_from', 'preview_page_to',
            ]);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 11/10 (khách: "trong admin và giáo viên phần tạo đề và cập nhật thêm field để chọn file pdf
 * hướng dẫn và field để chọn file pdf bài mẫu") — hai tệp PDF do người ra đề tải lên, hiện ở tab
 * "Hướng dẫn" và "Bài mẫu" khi học sinh làm đề. Cùng nếp với tệp PDF xem trước
 * (add_preview_pdf_to_assessments_table): disk 'local' (riêng tư), ra ngoài qua route
 * student.assessment.pdf.file chỉ cho người đã có lượt làm đề.
 *
 * Không đụng solution_pdf_path (PDF lời giải của đề PDF, chỉ mở sau khi công bố đáp án).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->string('guide_pdf_path')->nullable();
            $table->string('guide_pdf_original_name')->nullable();
            $table->string('sample_pdf_path')->nullable();
            $table->string('sample_pdf_original_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->dropColumn(['guide_pdf_path', 'guide_pdf_original_name', 'sample_pdf_path', 'sample_pdf_original_name']);
        });
    }
};

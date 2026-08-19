<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 18/8 (Bộ đề, 16/8 mục 5 "Tạo và nhập Bộ đề"): khi Admin tải 1 PDF tổng của cả Bộ đề
 * và khai phạm vi trang từng đề, hệ thống tự cắt thành từng đề lẻ (Assessment content_mode=
 * pdf_answer_sheet) và tự tạo 1 Material (type=assessment_ref) trỏ tới đề đó để hiện trong
 * mục lục Bộ đề. 2 cột này chỉ để LƯU VẾT phạm vi trang gốc lúc cắt (phục vụ tra soát/sửa
 * lại phạm vi nếu cắt sai) — không dùng để tính toán lúc runtime, không bắt buộc phải có.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->unsignedInteger('page_from')->nullable()->after('assessment_id');
            $table->unsignedInteger('page_to')->nullable()->after('page_from');
        });
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropColumn(['page_from', 'page_to']);
        });
    }
};

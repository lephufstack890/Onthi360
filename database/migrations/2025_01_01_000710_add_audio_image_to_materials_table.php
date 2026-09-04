<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 4/9 (khách yêu cầu: "file học liệu có thể là audio, pdf, ảnh động... đính nhiều loại
 * cùng lúc") — trước đây Material chỉ lưu được 1 file PDF duy nhất (pdf_path/
 * pdf_original_name, xem migration add_code_and_pdf_to_materials_table). Thêm 2 cặp cột mới
 * song song, ĐỘC LẬP với PDF (KHÔNG thay thế) — 1 Material giờ có thể có đủ cả 3: PDF +
 * audio + ảnh (ảnh tĩnh hoặc GIF động) cùng lúc, mỗi loại tùy chọn (nullable) riêng, xem
 * ContentService::materialStore()/materialUpdate().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->string('audio_path')->nullable()->after('pdf_original_name');
            $table->string('audio_original_name')->nullable()->after('audio_path');
            $table->string('image_path')->nullable()->after('audio_original_name');
            $table->string('image_original_name')->nullable()->after('image_path');
        });
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropColumn(['audio_path', 'audio_original_name', 'image_path', 'image_original_name']);
        });
    }
};

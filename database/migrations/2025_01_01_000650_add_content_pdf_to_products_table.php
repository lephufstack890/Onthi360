<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // SỬA 27/8 (3 — "thiếu 1 cái upload file pdf nữa có 4 lần upload á"): sau khi bỏ
            // khối "Học liệu thuộc sản phẩm" (cây chương/mục Material cũ), file PDF nội dung
            // chính (sách/chuyên đề/đề thi) giờ cũng là 1 ô upload phẳng như 3 ô kia — đủ 4
            // ô: PDF chính, PDF hướng dẫn, ZIP bài tập, học liệu media.
            $table->string('content_pdf_path')->nullable()->after('cover_image_path');
            $table->string('content_pdf_original_name')->nullable()->after('content_pdf_path');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['content_pdf_path', 'content_pdf_original_name']);
        });
    }
};

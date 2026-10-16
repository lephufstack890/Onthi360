<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 16/10 (khách: "nhập đề bằng file ZIP" — đề HSG chấm 0,25 điểm/câu) — điểm từng câu trên phiếu đáp án và điểm từng
 * bài lập trình của đề PDF trước đây là SỐ NGUYÊN (unsignedInteger) nên 0,25 bị cắt thành 0. Đổi sang decimal(8,2),
 * cùng kiểu với attempt_answer_keys.score / assessments.total_points (đã decimal từ migration 001130).
 * Dữ liệu cũ (số nguyên) giữ nguyên giá trị.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_answer_keys', function (Blueprint $table) {
            $table->decimal('points', 8, 2)->default(0)->change();
        });

        Schema::table('assessment_coding_items', function (Blueprint $table) {
            $table->decimal('points', 8, 2)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('assessment_answer_keys', function (Blueprint $table) {
            $table->unsignedInteger('points')->default(0)->change();
        });

        Schema::table('assessment_coding_items', function (Blueprint $table) {
            $table->unsignedInteger('points')->default(0)->change();
        });
    }
};

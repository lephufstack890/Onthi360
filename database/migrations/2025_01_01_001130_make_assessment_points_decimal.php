<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 11/10 (khách: "Điểm có thể là số thập phân cũng có thể là số nguyên") — điểm từng câu
 * trong đề do người ra đề NHẬP TAY, nên cho phép số lẻ (0.5, 1.25…) chứ không chỉ số nguyên.
 *
 *  - assessment_items.points_override : unsigned int  -> decimal(8,2) NULL
 *  - assessments.total_points         : unsigned int  -> decimal(10,2) DEFAULT 0
 *
 * Điểm bài làm (attempts.total_score, attempt_answers.score) vốn đã là decimal(8,2) nên không
 * phải đổi. Dữ liệu cũ là số nguyên nên đổi kiểu không làm mất/lệch giá trị nào.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_items', function (Blueprint $table) {
            $table->decimal('points_override', 8, 2)->nullable()->change();
        });

        Schema::table('assessments', function (Blueprint $table) {
            $table->decimal('total_points', 10, 2)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('assessment_items', function (Blueprint $table) {
            $table->unsignedInteger('points_override')->nullable()->change();
        });

        Schema::table('assessments', function (Blueprint $table) {
            $table->unsignedInteger('total_points')->default(0)->change();
        });
    }
};

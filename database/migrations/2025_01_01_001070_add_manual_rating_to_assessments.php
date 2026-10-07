<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 7/10 (khách: "số sao đánh giá cho nhập tay đi") — điểm sao + số lượt đánh giá do admin/giáo
 * viên nhập tay ở form đề. Khi hiển thị được GỘP (trung bình có trọng số theo số lượt) với các lượt
 * chấm thật của học sinh trong bảng assessment_ratings, xem PracticeService::combinedRating().
 *
 *   · rating_score  điểm trung bình 0-5, 1 chữ số lẻ (decimal(2,1)); null = chưa nhập.
 *   · rating_count  số lượt đánh giá đi kèm điểm đó; 0 = chưa nhập.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            if (! Schema::hasColumn('assessments', 'rating_score')) {
                $table->decimal('rating_score', 2, 1)->nullable()->after('difficulty_level');
            }
            if (! Schema::hasColumn('assessments', 'rating_count')) {
                $table->unsignedInteger('rating_count')->default(0)->after('rating_score');
            }
        });
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->dropColumn(['rating_score', 'rating_count']);
        });
    }
};

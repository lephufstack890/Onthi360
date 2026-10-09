<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 9/10 (khách: "check UI source mới trang tài liệu public, thiếu field thì bổ sung") — bản mẫu
 * mới của trang Tài liệu (education-main/MaterialsPage + MaterialQuality + MaterialFilters) hiển thị
 * và LỌC theo những thứ mà bảng products chưa có chỗ lưu:
 *
 *   · difficulty_level  độ khó 1-5 sao (Cơ bản · Dễ · Trung bình · Khó · Nâng cao). Nullable: tài liệu
 *                       cũ chưa xếp thì hiện "Chưa xếp độ khó", không bịa số.
 *   · author_name       tên tác giả hiện trên thẻ ("Thầy Nguyễn Tiến Thành & Ban Chuyên môn"). Trước đây
 *                       lấy tên người sở hữu sản phẩm nên luôn ra "Tổ chuyên môn Ôn Thi 360".
 *   · rating_score / rating_count  điểm sao + số lượt đánh giá do admin nhập tay, cùng cách làm với đề
 *                       thi (xem migration add_manual_rating_to_assessments). Khi hiển thị được GỘP
 *                       (trung bình có trọng số) với đánh giá thật của người đọc.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'difficulty_level')) {
                $table->unsignedTinyInteger('difficulty_level')->nullable();
            }
            if (! Schema::hasColumn('products', 'author_name')) {
                $table->string('author_name', 150)->nullable();
            }
            if (! Schema::hasColumn('products', 'rating_score')) {
                $table->decimal('rating_score', 2, 1)->nullable();
            }
            if (! Schema::hasColumn('products', 'rating_count')) {
                $table->unsignedInteger('rating_count')->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            foreach (['difficulty_level', 'author_name', 'rating_score', 'rating_count'] as $column) {
                if (Schema::hasColumn('products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

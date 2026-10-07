<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 7/10 (khách: "cột Hoạt động ở trang Nhật ký nộp bài — chỉ admin xem được; dữ liệu lấy ở tab
 * Nhật ký lúc người ta làm bài") — lưu nhật ký làm bài (mở tab Hướng dẫn/Bài mẫu, rời tab, phím
 * chụp màn hình…) theo TỪNG LƯỢT NỘP.
 *
 * Trước đây tab "Nhật ký" ở màn làm bài chỉ nằm trong sessionStorage của trình duyệt. Giờ trình
 * duyệt gửi kèm nhật ký lúc bấm Nộp bài, máy chủ lưu vào cột JSON này; chỉ admin đọc ra.
 *
 * Cột nullable: lượt nộp cũ (trước khi có tính năng) không có nhật ký — trang hiện "Chưa có dấu hiệu".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('attempt_answers', 'activity_log')) {
            Schema::table('attempt_answers', function (Blueprint $table) {
                $table->json('activity_log')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('attempt_answers', 'activity_log')) {
            Schema::table('attempt_answers', function (Blueprint $table) {
                $table->dropColumn('activity_log');
            });
        }
    }
};

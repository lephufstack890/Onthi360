<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 7/11 (khách: "bước tiến trình mở phải theo giờ phút, không chỉ chọn ngày — tới giờ dạy thì mở ra") — thêm unlock_at
 * (ngày + giờ phút tự mở khoá). Cột cũ unlock_on (chỉ ngày) giữ nguyên cho dữ liệu cũ. Chạy lại nhiều lần không lỗi.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('class_progress_steps') && ! Schema::hasColumn('class_progress_steps', 'unlock_at')) {
            Schema::table('class_progress_steps', function (Blueprint $table) {
                $table->dateTime('unlock_at')->nullable()->after('unlock_on');
                $table->index(['is_unlocked', 'unlock_at']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('class_progress_steps') && Schema::hasColumn('class_progress_steps', 'unlock_at')) {
            Schema::table('class_progress_steps', function (Blueprint $table) {
                $table->dropIndex(['is_unlocked', 'unlock_at']);
                $table->dropColumn('unlock_at');
            });
        }
    }
};

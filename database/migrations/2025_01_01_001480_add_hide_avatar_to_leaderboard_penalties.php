<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** SỬA 12/11 (khách: "thêm 1 cái để chọn ẩn avatar") — ẩn ảnh đại diện ở tab công khai "Tài khoản vi phạm". Chạy lại không lỗi. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('leaderboard_penalties') || Schema::hasColumn('leaderboard_penalties', 'hide_avatar')) {
            return;
        }
        Schema::table('leaderboard_penalties', function (Blueprint $table) {
            $table->boolean('hide_avatar')->default(false)->after('hide_name');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('leaderboard_penalties') && Schema::hasColumn('leaderboard_penalties', 'hide_avatar')) {
            Schema::table('leaderboard_penalties', fn (Blueprint $table) => $table->dropColumn('hide_avatar'));
        }
    }
};

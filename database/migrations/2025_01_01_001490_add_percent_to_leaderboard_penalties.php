<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 12/11 (khách: "trừ điểm và trừ token là trừ theo %") — lưu % admin chọn; số điểm / token thực trừ vẫn ở points_deducted /
 * tokens_deducted (tính lúc khóa: % × điểm xếp hạng hiện tại, % × số dư ví). Chạy lại không lỗi.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('leaderboard_penalties') || Schema::hasColumn('leaderboard_penalties', 'points_percent')) {
            return;
        }
        Schema::table('leaderboard_penalties', function (Blueprint $table) {
            $table->decimal('points_percent', 5, 2)->nullable()->after('points_deducted');
            $table->decimal('tokens_percent', 5, 2)->nullable()->after('tokens_deducted');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('leaderboard_penalties') && Schema::hasColumn('leaderboard_penalties', 'points_percent')) {
            Schema::table('leaderboard_penalties', fn (Blueprint $table) => $table->dropColumn(['points_percent', 'tokens_percent']));
        }
    }
};

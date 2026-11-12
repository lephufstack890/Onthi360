<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 12/11 (khách: "bỏ 2 tab Cuộc thi gần nhất / Lớp của tôi, thay bằng tab Tài khoản vi phạm; admin cấu hình trừng phạt,
 * trừng phạt xong thì hiện ra danh sách của tab đó") — mỗi dòng là 1 lần xử phạt một tài khoản trên bảng xếp hạng.
 *
 *  · lock_days     : số ngày khóa khỏi bảng xếp hạng (null = khóa vĩnh viễn, tới khi admin mở khóa).
 *  · points_deducted / tokens_deducted : mức trừ. Điểm trừ thẳng vào tổng điểm xếp hạng; token trừ vào ví (token_balance).
 *  · score_at_lock : tổng điểm xếp hạng TRƯỚC khi áp mức trừ (cột "Điểm khi khóa").
 *  · unlocked_at   : admin mở khóa sớm. revoked_at: admin HỦY hình phạt (hoàn token, bỏ trừ điểm, gỡ khỏi danh sách).
 * Chạy lại nhiều lần không lỗi.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('leaderboard_penalties')) {
            return;
        }
        Schema::create('leaderboard_penalties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reason', 255);
            $table->text('note')->nullable();
            $table->unsignedSmallInteger('lock_days')->nullable();
            $table->decimal('points_deducted', 10, 2)->default(0);
            $table->unsignedInteger('tokens_deducted')->default(0);
            $table->decimal('score_at_lock', 12, 2)->nullable();
            $table->string('badge_at_lock', 40)->nullable();
            $table->boolean('hide_name')->default(false);
            $table->timestamp('locked_at');
            $table->timestamp('unlock_at')->nullable();
            $table->timestamp('unlocked_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoke_reason', 500)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'revoked_at']);
            $table->index('locked_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leaderboard_penalties');
    }
};

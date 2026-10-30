<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 30/10 (khách: "thêm tab Nhật ký cho khảo sát / cuộc thi — biết ai xem hướng dẫn, ai mở tab khác, ai xem bài mẫu…") —
 * lưu NGUYÊN nhật ký làm đề của cả lượt (attempts.activity_log) lúc nộp bài.
 *
 * Trước đây nhật ký chỉ được chia theo câu và lưu vào attempt_answers.activity_log (để trừ điểm đúng câu) — dòng nào gắn với
 * câu học sinh chưa từng trả lời (không có dòng attempt_answers) là rơi mất. Cột này giữ đủ cả lượt cho tab Nhật ký; luật trừ
 * điểm theo câu không đổi. Chạy lại an toàn (đã có cột thì bỏ qua).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('attempts', 'activity_log')) {
            return;
        }

        Schema::table('attempts', function (Blueprint $table) {
            $table->json('activity_log')->nullable();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('attempts', 'activity_log')) {
            Schema::table('attempts', function (Blueprint $table) {
                $table->dropColumn('activity_log');
            });
        }
    }
};

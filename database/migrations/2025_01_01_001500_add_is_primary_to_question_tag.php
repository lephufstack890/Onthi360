<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 16/11 (khách: "chọn tag xong thì chỉ định tag chính — tag chính hiện to ở trên, tag phụ nhỏ bên dưới") —
 * đánh dấu tag chính của mỗi câu hỏi trên bảng nối question_tag. Câu chưa đánh dấu thì vẫn lấy tag đầu tiên như cũ.
 * Chạy lại nhiều lần không lỗi.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('question_tag') || Schema::hasColumn('question_tag', 'is_primary')) {
            return;
        }
        Schema::table('question_tag', function (Blueprint $table) {
            $table->boolean('is_primary')->default(false);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('question_tag') && Schema::hasColumn('question_tag', 'is_primary')) {
            Schema::table('question_tag', fn (Blueprint $table) => $table->dropColumn('is_primary'));
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 30/9 (khách: "chưa có thứ tự ưu tiên hiển thị" — file "Chỉnh sửa" mục Bài/Câu hỏi) —
 * ĐỘ ƯU TIÊN HIỂN THỊ của 1 câu hỏi.
 *
 * Quy ước: SỐ CÀNG LỚN CÀNG HIỆN TRƯỚC, mặc định 0 = bình thường. Cố ý KHÔNG dùng kiểu "số
 * nhỏ hiện trước" như cột materials.order: toàn bộ câu hỏi cũ sẽ mang giá trị mặc định 0, mà
 * 0 là số nhỏ nhất — câu chưa ai đặt sẽ nhảy lên đầu, ngược hẳn ý "ưu tiên". Với quy ước này,
 * kho cũ giữ nguyên thứ tự (mới nhất trước) cho tới khi admin tự đặt ưu tiên cho câu nào đó.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->unsignedInteger('display_order')->default(0)->after('points');
            // Mọi danh sách câu hỏi đều sắp xếp "ưu tiên giảm dần, rồi mới nhất trước".
            $table->index(['display_order', 'id']);
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropIndex(['display_order', 'id']);
            $table->dropColumn('display_order');
        });
    }
};

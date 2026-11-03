<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 3/11 (khách: "thêm dạng PHỔ THÔNG — 1 bài nhiều câu trắc nghiệm / điền từ… nhập bằng gói ZIP như mẫu MAU-CAU-HOI") —
 * assessments.practice_kind đánh dấu "Bài phổ thông" (giá trị 'general', xem App\Services\GeneralSetImportService).
 *   · null (mọi đề đang có)  → giữ NGUYÊN mọi hành vi cũ, không đổi gì;
 *   · 'general'              → hiện ở Luyện tập > Bài tập > Phổ thông (không hiện ở tab Đề thi luyện tập).
 * Chạy lại an toàn (đã có cột thì bỏ qua).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('assessments', 'practice_kind')) {
            return;
        }

        Schema::table('assessments', function (Blueprint $table) {
            $table->string('practice_kind', 20)->nullable()->index();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('assessments', 'practice_kind')) {
            Schema::table('assessments', function (Blueprint $table) {
                $table->dropIndex(['practice_kind']);
                $table->dropColumn('practice_kind');
            });
        }
    }
};

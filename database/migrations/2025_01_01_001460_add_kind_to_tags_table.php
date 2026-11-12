<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 12/11 (khách: "tag chuyên đề thêm loại — 2 loại: lập trình và thủ công; thêm / sửa đều cho chọn loại, danh sách
 * có cột loại") — tags.kind: 'coding' (Lập trình) | 'manual' (Thủ công). Tag cũ để trống (= "Chưa phân loại") để admin
 * tự gán lại cho đúng. Chạy lại nhiều lần không lỗi.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tags') || Schema::hasColumn('tags', 'kind')) {
            return;
        }
        Schema::table('tags', function (Blueprint $table) {
            $table->string('kind', 20)->nullable()->after('name')->index();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('tags') && Schema::hasColumn('tags', 'kind')) {
            Schema::table('tags', function (Blueprint $table) {
                $table->dropIndex(['kind']);
                $table->dropColumn('kind');
            });
        }
    }
};

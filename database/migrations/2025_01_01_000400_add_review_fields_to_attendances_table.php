<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Nhận xét đánh giá" mỗi buổi (note họp 13/8): nhận xét mặc định là một câu nào đó
     * (giáo viên có thể sửa lại), thêm cột "Em cần học thêm" để giáo viên đánh dấu nhanh.
     */
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->text('note')->nullable()->after('source');
            $table->boolean('needs_more_practice')->default(false)->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn(['note', 'needs_more_practice']);
        });
    }
};

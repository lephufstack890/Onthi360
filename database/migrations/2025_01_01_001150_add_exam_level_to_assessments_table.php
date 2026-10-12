<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 12/10 (khách: "thêm lọc theo khối nữa — THCS · THPT · Khác") — assessments.exam_level: khối học mà đề dành
 * cho (xem App\Support\ExamLevel). Nullable: đề chưa chọn khối được tính vào "Khác" khi lọc.
 *
 * Điền sẵn một lần cho đề CŨ khi tên đề nói rõ khối ("ĐỀ THCS BÌNH ĐỊNH…", "ĐỀ THPT BÌNH PHƯỚC…") hoặc loại đề
 * cũ là "Thi thử THPT" — chỉ những trường hợp chắc chắn, còn lại để trống cho người ra đề tự chọn.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('assessments', 'exam_level')) {
            Schema::table('assessments', function (Blueprint $table) {
                $table->string('exam_level', 10)->nullable()->after('exam_category');
            });
        }

        DB::table('assessments')->whereNull('exam_level')->where('title', 'like', '%THCS%')->update(['exam_level' => 'thcs']);
        DB::table('assessments')->whereNull('exam_level')
            ->where(fn ($q) => $q->where('title', 'like', '%THPT%')->orWhere('exam_category', 'thpt'))
            ->update(['exam_level' => 'thpt']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('assessments', 'exam_level')) {
            Schema::table('assessments', function (Blueprint $table) {
                $table->dropColumn('exam_level');
            });
        }
    }
};

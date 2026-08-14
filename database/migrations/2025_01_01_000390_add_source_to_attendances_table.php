<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Điểm danh 2 chiều" — học sinh tự vào làm bài trong giờ học thì được điểm danh tự
     * động (source=auto), khác với giáo viên điểm danh tay (source=manual, mặc định — giữ
     * đúng hành vi cũ cho toàn bộ dữ liệu điểm danh đã có). Note họp 13/8: "Điểm danh từ
     * hai phía là giáo viên và học sinh".
     */
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->string('source', 10)->default('manual')->after('status'); // Enums\AttendanceSource
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};

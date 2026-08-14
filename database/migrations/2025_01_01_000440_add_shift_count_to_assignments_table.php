<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Các kỳ thi nếu đông quá thì chia thành các ca thi để chống tấn công ddos"
 * (note họp 13/8, mục 7). shift_count > 1 nghĩa là khung [opens_at, closes_at]
 * của Assignment được chia đều thành shift_count ca — mỗi học sinh được gán cố
 * định (xác định, không lưu bảng riêng) vào đúng 1 ca theo App\Models\Assignment
 * ::shiftWindowFor(). shift_count = null/1 = hành vi cũ, không chia ca.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->unsignedSmallInteger('shift_count')->nullable()->after('due_at');
        });
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropColumn('shift_count');
        });
    }
};

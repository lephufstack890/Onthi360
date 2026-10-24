<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 24/10 (khách: "cuộc thi / khảo sát thuộc khoá học nào — lộ trình học = khoá học, UI cứ để chữ Lộ trình học") —
 * gắn cuộc thi với KHOÁ HỌC (courses). Ô "Lộ trình học" ở trang Cuộc thi, bộ lọc "Lộ trình" và link trong phòng thi
 * đều đọc cột này. Cột learning_path_id (migration 001260) không dùng nữa, giữ lại để không mất gì.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('competitions', 'course_id')) {
            Schema::table('competitions', function (Blueprint $table) {
                $table->foreignId('course_id')->nullable()->after('title')->constrained('courses')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('competitions', 'course_id')) {
            Schema::table('competitions', function (Blueprint $table) {
                $table->dropConstrainedForeignId('course_id');
            });
        }
    }
};

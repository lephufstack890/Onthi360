<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Cuộc thi ngoài đơn vị tổ chức thì cần có chuyên gia cố vấn giáo viên đồng hành để tăng
 * uy tín" (note họp 13/8, mục 1). organizer_type phân biệt cuộc thi do chính nền tảng tổ
 * chức (internal — mặc định, không bắt buộc cố vấn) hay do bên ngoài tổ chức (external —
 * bắt buộc có organizer_name + ít nhất 1 giáo viên cố vấn, xem
 * database/migrations/..._create_competition_advisors_table.php).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            $table->string('organizer_type', 20)->default('internal')->after('type'); // Enums\CompetitionOrganizerType
            $table->string('organizer_name')->nullable()->after('organizer_type');
        });
    }

    public function down(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            $table->dropColumn(['organizer_type', 'organizer_name']);
        });
    }
};

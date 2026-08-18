<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cho phép 1 LeaderboardEntry gắn với 1 kỳ thi cụ thể (scope='competition_exam') thay vì
 * chỉ gắn với cả Competition (scope='competition', bảng tổng) — xem
 * database/migrations/..._create_competition_exams_table.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leaderboard_entries', function (Blueprint $table) {
            $table->foreignId('competition_exam_id')->nullable()->after('competition_id')
                ->constrained('competition_exams')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('leaderboard_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('competition_exam_id');
        });
    }
};

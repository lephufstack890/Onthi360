<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Spec 12.2/PUB-10: trang "Giáo viên tiêu biểu" là trang vinh danh do Admin chọn công bố. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_profiles', function (Blueprint $table) {
            $table->boolean('is_featured')->default(false)->after('approval_status');
            $table->text('achievement_note')->nullable()->after('is_featured');
        });
    }

    public function down(): void
    {
        Schema::table('teacher_profiles', function (Blueprint $table) {
            $table->dropColumn(['is_featured', 'achievement_note']);
        });
    }
};

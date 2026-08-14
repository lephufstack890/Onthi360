<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** "Sau một buổi học thì có tổng kết nhận xét" — note họp 13/8. */
    public function up(): void
    {
        Schema::table('class_sessions', function (Blueprint $table) {
            $table->text('summary')->nullable()->after('location');
        });
    }

    public function down(): void
    {
        Schema::table('class_sessions', function (Blueprint $table) {
            $table->dropColumn('summary');
        });
    }
};

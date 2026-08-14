<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Giáo viên cố vấn/đồng hành cho cuộc thi tổ chức bởi bên ngoài (note họp 13/8, mục 1) —
 * xem App\Models\Competition::advisors().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competition_advisors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained('competitions')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['competition_id', 'teacher_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_advisors');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->string('code', 40)->unique(); // ví dụ 10CT-2026
            $table->string('name');
            $table->json('schedule')->nullable(); // ngày/giờ định kỳ, hiển thị ở Lịch học
            $table->string('status', 20)->default('active'); // active|archived
            $table->timestamps();
            $table->softDeletes();

            $table->index(['course_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_rooms');
    }
};

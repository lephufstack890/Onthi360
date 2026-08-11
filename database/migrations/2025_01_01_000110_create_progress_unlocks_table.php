<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "Tiến độ chung" — giáo viên mở theo chương/mục/mã bài cho toàn lớp (8.2, cửa #3 ở 7.3).
        Schema::create('progress_unlocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_room_id')->constrained('class_rooms')->cascadeOnDelete();
            $table->string('unit_type', 20); // Enums\ProgressUnitType: chapter|section|question
            $table->unsignedBigInteger('unit_id'); // material_id hoặc question_id tùy unit_type
            $table->foreignId('opened_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['class_room_id', 'unit_type', 'unit_id'], 'progress_unlocks_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('progress_unlocks');
    }
};

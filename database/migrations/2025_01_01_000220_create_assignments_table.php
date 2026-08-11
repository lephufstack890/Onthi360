<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "Giao đề đánh giá" — luồng phụ 8.4, khác với class_materials (dùng thường nhật).
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_room_id')->constrained('class_rooms')->cascadeOnDelete();
            $table->foreignId('assessment_id')->constrained('assessments')->cascadeOnDelete();
            $table->dateTime('opens_at')->nullable();
            $table->dateTime('closes_at')->nullable();
            $table->dateTime('due_at')->nullable();
            $table->json('rules')->nullable(); // quy tắc làm lại, công bố kết quả riêng
            $table->text('instructions')->nullable();
            $table->string('status', 20)->default('draft'); // Enums\AssignmentStatus
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['class_room_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};

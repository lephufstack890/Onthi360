<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assessment_id')->constrained('assessments')->cascadeOnDelete();
            $table->foreignId('assignment_id')->nullable()->constrained('assignments')->nullOnDelete();
            $table->foreignId('class_room_id')->nullable()->constrained('class_rooms')->nullOnDelete();
            $table->string('source', 20); // Enums\AttemptSource
            $table->dateTime('started_at')->useCurrent();
            $table->dateTime('submitted_at')->nullable();
            $table->string('status', 20)->default('in_progress'); // Enums\AttemptStatus
            $table->decimal('total_score', 8, 2)->nullable();
            $table->boolean('is_provisional')->default(true); // "tạm tính" tới khi mọi câu chấm xong (6.3)
            $table->timestamps();

            $table->index(['user_id', 'assessment_id']);
            $table->index(['class_room_id', 'assignment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attempts');
    }
};

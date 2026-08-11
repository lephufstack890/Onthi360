<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->string('reviewer_role', 20); // Enums\ReviewerRole
            $table->string('target_type', 20); // Enums\ReviewTargetType (material|class_room)
            $table->unsignedBigInteger('target_id');
            $table->unsignedInteger('target_version')->default(1); // gắn version học liệu/đợt lớp (9.5)
            $table->unsignedTinyInteger('overall_rating'); // 1-5
            $table->json('criteria_scores')->nullable();
            $table->text('comment')->nullable();
            $table->boolean('disclosure_ack')->default(false);
            $table->string('status', 20)->default('draft'); // Enums\ReviewStatus
            $table->text('moderation_reason')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->dateTime('editable_until')->nullable(); // sửa được trong 7 ngày (9.2)
            $table->timestamps();

            // Mỗi người 1 review đang hoạt động cho mỗi đối tượng + phiên bản/đợt (9.2).
            $table->unique(
                ['reviewer_id', 'target_type', 'target_id', 'target_version'],
                'reviews_one_active_per_target_version'
            );
            $table->index(['target_type', 'target_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};

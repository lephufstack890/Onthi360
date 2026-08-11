<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('type', 20); // Enums\AssessmentType
            $table->unsignedInteger('total_points')->default(0);
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->json('resubmission_policy')->nullable(); // {"max_attempts": n, ...}
            $table->string('publish_answer_rule', 20)->default('never'); // Enums\PublishAnswerRule
            $table->string('status', 20)->default('draft'); // Enums\ContentStatus
            $table->unsignedInteger('version')->default(1);
            $table->string('owner_type', 20)->default('shared'); // Enums\OwnerType
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'status']);
        });

        // Bổ sung FK materials.assessment_id -> assessments (cột đã tạo ở migration materials
        // trước đó, chưa có constraint vì assessments chưa tồn tại tại thời điểm đó).
        Schema::table('materials', function (Blueprint $table) {
            $table->foreign('assessment_id')->references('id')->on('assessments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropForeign(['assessment_id']);
        });
        Schema::dropIfExists('assessments');
    }
};

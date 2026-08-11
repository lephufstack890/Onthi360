<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_id')->constrained('question_banks')->cascadeOnDelete();
            $table->string('code', 40)->unique(); // mã bài hiển thị cho học sinh
            $table->string('type', 20); // Enums\QuestionType
            $table->string('title');
            $table->longText('body')->nullable(); // đề bài / mô tả
            $table->unsignedInteger('points')->default(0);
            // Cấu hình chấm — cấu trúc khác nhau theo type (test cases/time-memory-limit cho coding;
            // đáp án đúng/cách tính điểm cho mcq; đáp án/tolerance cho fill_blank). Dùng JSON để không
            // phải đổi schema mỗi khi thêm luật chấm mới (6.1).
            $table->json('grading_config')->nullable();
            $table->json('metadata')->nullable(); // môn/khối/chuyên đề/độ khó
            $table->string('owner_type', 20)->default('shared'); // Enums\OwnerType
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('visibility', 20)->default('public'); // Enums\Visibility
            $table->string('status', 20)->default('draft'); // Enums\ContentStatus
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('parent_version_id')->nullable()->constrained('questions')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'status', 'visibility']);
            $table->index(['owner_type', 'owner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};

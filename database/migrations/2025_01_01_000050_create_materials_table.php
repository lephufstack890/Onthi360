<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('materials')->cascadeOnDelete();
            $table->string('type', 20); // chapter|section|assessment_ref
            $table->string('title');
            $table->unsignedInteger('order')->default(0);
            // FK sang assessments được thêm ở migration sau (assessments được tạo sau materials
            // vì assessment lại tham chiếu question — tránh vòng lặp thứ tự bảng).
            $table->foreignId('assessment_id')->nullable();
            $table->string('status', 20)->default('draft'); // Enums\ContentStatus
            $table->timestamps();

            $table->index(['product_id', 'parent_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materials');
    }
};

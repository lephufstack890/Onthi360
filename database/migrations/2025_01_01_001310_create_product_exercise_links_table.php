<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 27/10 (khách: "dùng bài có sẵn mà lưu thêm thì nặng — không lưu thêm, DÙNG CHUNG") — bài tập DÙNG KÉ: một bài của
 * tài liệu A (questions.product_id = A) hiện thêm ở chương của tài liệu B mà KHÔNG chép bài / tệp. Mỗi dòng = "tài liệu B
 * dùng bài Q, đặt ở chương M". Sửa bài gốc là mọi tài liệu dùng ké thấy ngay; xoá bài gốc thì dòng tự mất (cascade);
 * xoá chương của B thì bài chuyển về "chưa gắn chương" (nullOnDelete). Xem App\Support\ProductExerciseLinks.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('product_exercise_links')) {
            return;
        }

        Schema::create('product_exercise_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('questions')->cascadeOnDelete();
            $table->foreignId('material_id')->nullable()->constrained('materials')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['product_id', 'question_id'], 'pel_product_question_unique');
            $table->index('question_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_exercise_links');
    }
};

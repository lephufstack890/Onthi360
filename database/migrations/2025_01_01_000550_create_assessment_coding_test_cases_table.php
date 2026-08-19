<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Test case của 1 bài lập trình con trong đề PDF (App\Models\AssessmentCodingItem) — Admin
 * tải lên bằng gói ZIP rồi hệ thống tách thành từng cặp input/output lưu vào storage, ghi
 * đường dẫn ở đây (16/8 mục 1.2: "Test case/tệp kèm theo có thể tải bằng gói ZIP; đây không
 * phải nhập đáp án bằng Excel/CSV"). is_sample = test case mẫu cho học sinh xem trước khi
 * nộp (không tính là test ẩn để chấm điểm).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_coding_test_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coding_item_id')->constrained('assessment_coding_items')->cascadeOnDelete();
            $table->unsignedInteger('order')->default(0);
            $table->string('input_path');
            $table->string('expected_output_path');
            $table->boolean('is_sample')->default(false);
            $table->timestamps();

            $table->index(['coding_item_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_coding_test_cases');
    }
};

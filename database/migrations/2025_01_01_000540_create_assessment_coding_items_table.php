<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 1 bài lập trình con nằm TRONG 1 đề PDF (16/8 mục 6: "Trong khi đề PDF trắc nghiệm hiện
 * phiếu trả lời, bài lập trình con thay bằng trình soạn mã") — 1 Assessment content_mode=
 * pdf_answer_sheet có thể vừa có câu ở assessment_answer_keys (trắc nghiệm/đúng-sai/trả lời
 * ngắn) vừa có bài ở đây (lập trình), không loại trừ nhau. allowed_languages là JSON vì học
 * sinh tự CHỌN ngôn ngữ lúc làm (C++ hoặc Python), Admin không ép cứng 1 ngôn ngữ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_coding_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained('assessments')->cascadeOnDelete();
            $table->string('code', 40); // mã bài trong đề, VD "Câu 5" — Admin tự đặt
            $table->string('title');
            $table->unsignedInteger('pdf_page')->nullable(); // vị trí bài trong PDF, để học sinh biết mở trang nào
            $table->json('allowed_languages')->default('["cpp","python"]');
            $table->unsignedInteger('time_limit_ms')->default(1000);
            $table->unsignedInteger('memory_limit_kb')->default(262144);
            $table->unsignedInteger('points')->default(0);
            $table->timestamps();

            $table->unique(['assessment_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_coding_items');
    }
};

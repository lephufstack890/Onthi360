<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 19/8 (Giai đoạn 6 — "Gắn tag/chủ đề cho câu hỏi"): trước đây hệ thống CHƯA có bảng
 * Tag/Chuyên đề riêng — Student\PracticeService phải tạm dùng QuestionBank::name làm chiều
 * lọc "chuyên đề" gần đúng (xem doc comment ở đó), vì ngân hàng câu hỏi vốn tạo ra để NHÓM
 * câu hỏi theo nguồn/người sở hữu (6.5), không phải để phân loại chủ đề — 1 ngân hàng có thể
 * chứa nhiều chủ đề, và 1 chủ đề có thể trải khắp nhiều ngân hàng. Bảng này là chuyên đề/tag
 * THẬT: phẳng (không phân cấp cha/con — chưa có yêu cầu phân cấp), dùng CHUNG cho mọi câu hỏi
 * bất kể thuộc Kho chung hay kho riêng giáo viên nào (tag không thuộc về ai — "Đại số",
 * "Hình học" dùng chung được cho câu hỏi của Admin lẫn của bất kỳ giáo viên nào).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tags');
    }
};

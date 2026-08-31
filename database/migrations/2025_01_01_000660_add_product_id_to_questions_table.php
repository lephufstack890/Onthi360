<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// SỬA 31/8 ("ZIP bài tập" gắn vào sản phẩm): thêm product_id (nullable) vào
// questions để phân biệt câu hỏi THUỘC 1 sản phẩm (bài tập đính kèm, chỉ admin
// quản lý, không chia sẻ ra Kho câu hỏi/luyện tập công khai) với câu hỏi dùng
// chung như trước giờ (product_id = null). Mọi query lấy câu hỏi CHUNG
// (allLatestWithOwner, countShared, idsForPractice) đều phải thêm
// whereNull('product_id') — xem app/Repositories/Eloquent/QuestionRepository.php.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->foreignId('product_id')
                ->nullable()
                ->after('bank_id')
                ->constrained('products')
                ->nullOnDelete();

            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_id');
        });
    }
};

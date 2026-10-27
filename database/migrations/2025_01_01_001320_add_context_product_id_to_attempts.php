<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 27/10 (khách: "dùng chung bài nhưng làm bên chương tài liệu kia rồi thì bên tài liệu này cũng đánh dấu đã làm — bên
 * nào ra bên đó, rõ ràng chỗ nào ra chỗ đó") — ghi lượt luyện bài tập được mở TỪ TÀI LIỆU NÀO. Bài dùng chung (bảng
 * product_exercise_links) làm ở tài liệu B chỉ tính "đã làm" cho B. Lượt cũ (null) tính cho tài liệu gốc của bài như trước.
 * Xem App\Support\ProductExerciseLinks::answersInProduct().
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('attempts', 'context_product_id')) {
            return;
        }

        Schema::table('attempts', function (Blueprint $table) {
            $table->foreignId('context_product_id')->nullable()->after('class_room_id')->constrained('products')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('attempts', 'context_product_id')) {
            return;
        }

        Schema::table('attempts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('context_product_id');
        });
    }
};

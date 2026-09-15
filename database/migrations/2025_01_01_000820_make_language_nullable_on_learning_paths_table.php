<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cho phép lộ trình KHÔNG có ngôn ngữ lập trình.
 *
 * SỬA 15/9 (khách yêu cầu) — ban đầu lộ trình chỉ dành cho Tin học nên ngôn ngữ (Python/C++)
 * là bắt buộc. Nay khách cho biết sau này còn làm lộ trình Toán, Văn và các môn khác, lúc đó
 * "ngôn ngữ lập trình" không còn nghĩa gì. Vì vậy ô này được ẨN khỏi giao diện và cột thành
 * không bắt buộc.
 *
 * CỘT VẪN GIỮ, KHÔNG XOÁ: năm lộ trình Tin học hiện có đã ghi Python/C++, xoá cột là mất dữ
 * liệu đó. Khi nào cần hiện lại chỉ việc bật cờ trong App\Services\Admin\LearningPathService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learning_paths', function (Blueprint $table) {
            $table->string('language', 20)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('learning_paths', function (Blueprint $table) {
            $table->string('language', 20)->nullable(false)->change();
        });
    }
};

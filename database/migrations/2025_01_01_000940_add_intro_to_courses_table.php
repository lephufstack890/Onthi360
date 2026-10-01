<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 1/10 (khách: "trong admin thêm hộ tôi 1 field giới thiệu nữa nhé dạng ckeditor như mô tả
 * nhé. Còn ngoài trang khi bấm xem lộ trình thì mô tả đổ dữ liệu vô chỗ tôi khoanh đỏ đó còn
 * giới thiệu đổ chỗ mục giới thiệu") — tách làm HAI trường cho trang khoá học công khai:
 *
 *   · courses.description (đã có) — MÔ TẢ NGẮN, in ở dòng tóm tắt ngay dưới tên khoá (khối
 *     khách khoanh đỏ trong ảnh gửi kèm) và dùng làm thẻ meta description.
 *   · courses.intro (cột này) — GIỚI THIỆU ĐẦY ĐỦ, in ở mục "Giới thiệu khoá học" giữa trang.
 *
 * Trước đây cả 2 chỗ cùng đổ từ description: chỗ tóm tắt bị cắt cụt giữa chừng, còn mục giới
 * thiệu thì chỉ lặp lại đúng câu đó.
 *
 * longText vì nội dung do CKEditor soạn (có thẻ HTML, ảnh, danh sách) — text thường (64KB) đủ
 * cho hôm nay nhưng chật khi bài giới thiệu dài có chèn ảnh base64.
 *
 * Nullable và KHÔNG có lệnh backfill: khoá cũ để trống cột này, trang công khai tự rơi về
 * description như trước (xem public/courses/show.blade.php) nên không khoá nào bị trống mục
 * giới thiệu trong lúc chờ admin soạn lại.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->longText('intro')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('intro');
        });
    }
};

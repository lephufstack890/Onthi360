<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * testimonials — khối "Câu chuyện đồng hành" ở trang chủ công khai ([HOME-10]).
 *
 * SỬA 12/9 (khách yêu cầu: "đăng ở trang quản trị xong hiển thị ở trang chủ") — trước đây 3
 * câu chuyện này nằm CỨNG trong resources/views/welcome.blade.php, muốn đổi phải sửa mã nguồn
 * rồi triển khai lại. Giờ là dữ liệu thật, Admin tự thêm/sửa/ẩn được.
 *
 * Ghi chú về SEO và tính trung thực — ĐỌC TRƯỚC KHI SỬA:
 * cột `verified_at` KHÔNG phải để trang trí. Chỉ những câu chuyện đã được xác minh là CÓ THẬT
 * (có người thật đồng ý cho đăng) mới được gắn dữ liệu có cấu trúc schema.org/Review gửi cho
 * Google — xem resources/views/partials/seo-testimonials.blade.php. Khai báo Review cho nội
 * dung bịa là vi phạm chính sách dữ liệu có cấu trúc của Google và có thể bị phạt thủ công cả
 * tên miền. Câu chuyện chưa xác minh vẫn hiển thị bình thường trên trang, chỉ không gắn markup.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->text('quote');
            $table->string('author_name');
            $table->string('author_role')->nullable();   // "Học sinh lớp 12", "Phụ huynh"...
            $table->string('author_org')->nullable();    // trường/đơn vị — cũng là từ khoá tốt cho SEO
            $table->string('avatar_path')->nullable();   // ảnh đại diện (storage/app/public)
            $table->string('banner_path')->nullable();   // ảnh bìa của thẻ
            $table->unsignedTinyInteger('rating')->nullable(); // 1–5, dùng cho schema.org/Review
            $table->string('status', 20)->default('draft');    // Enums\ContentStatus (draft|published|archived)
            $table->unsignedInteger('sort_order')->default(0);
            $table->dateTime('published_at')->nullable();
            $table->dateTime('verified_at')->nullable();       // xem ghi chú ở đầu file
            $table->boolean('is_sample')->default(false);      // dòng do seeder tạo, cần thay bằng nội dung thật
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('testimonials');
    }
};

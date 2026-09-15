<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LỘ TRÌNH HỌC — cấp trên của Khoá học.
 *
 * Cấu trúc ba cấp: Lộ trình → nhiều Khoá học (mỗi khoá là một "bậc") → nhiều Lớp học.
 * Ví dụ thật của khách: lộ trình "Python Thi đấu THCS" gồm 6 bậc PRE-CODE → FOUNDATION A →
 * FOUNDATION B → INTERMEDIATE → INTENSIVE → ADVANCED, tổng 120 buổi.
 *
 * ── Vì sao lưu KHỐI LỚP thành hai cột số ──
 * Lộ trình của khách ghi "Khối 6–8", tức là một KHOẢNG chứ không phải một lớp. Để một ô chữ
 * thì học sinh lớp 7 không bao giờ khớp được. Hai cột số cho phép hỏi thẳng "lộ trình nào
 * hợp với lớp 7" bằng một điều kiện BETWEEN, và vẫn in ra "Khối 6–8" bình thường.
 *
 * ── Vì sao KHÔNG có cột màu ──
 * Nhìn ảnh thiết kế thì 6 bậc đi theo một dải cam → vàng → lục → lam → chàm → tím, tức màu
 * suy ra từ THỨ TỰ chứ không phải thuộc tính của từng bậc. Sinh màu bằng mã (App\Support\
 * LearningPathPalette) thì thêm bậc thứ 7 màu tự giãn, khỏi ai phải chọn tay.
 *
 * ── Vì sao KHÔNG có cột tổng số buổi ──
 * Tổng buổi = cộng số buổi của các bậc. Lưu thêm một cột nữa là tự tạo ra hai nguồn sự thật,
 * sửa một bậc mà quên cập nhật tổng là sai số liệu ngay. Tính khi cần, xem LearningPath.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_paths', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            // Tên dòng sản phẩm in trên ảnh lộ trình, ví dụ "SASH". Không bắt buộc.
            $table->string('brand', 60)->nullable();
            // Nhãn nhỏ phía trên tiêu đề: "LỘ TRÌNH TIẾP CẬN LẬP TRÌNH".
            $table->string('eyebrow', 120)->nullable();
            $table->string('subtitle')->nullable();
            $table->text('description')->nullable();

            // Khoảng khối lớp phục vụ — xem ghi chú ở đầu tệp.
            $table->unsignedTinyInteger('grade_from');
            $table->unsignedTinyInteger('grade_to');

            $table->string('language', 20); // Enums\PathLanguage
            // Mục tiêu đích in trên ảnh: "HSG lớp 9 · Thi tuyển sinh 10 Chuyên Tin".
            $table->string('goal_label');

            // Nhịp học in ở chân ảnh: "2 buổi/tuần · 2 giờ/buổi" — dùng để quy ra số tuần.
            $table->unsignedTinyInteger('sessions_per_week')->default(2);
            $table->decimal('hours_per_session', 3, 1)->default(2);

            // 3 dòng "kết quả đầu ra". Danh sách chữ thuần, không cần bảng riêng.
            $table->json('outcomes')->nullable();

            $table->string('cover_image_path')->nullable();
            // Ảnh SVG/PNG đẹp của lộ trình — dùng làm ảnh chia sẻ mạng xã hội, KHÔNG phải
            // nội dung chính của trang (trang công khai dựng lại bằng HTML theo dữ liệu).
            $table->string('share_image_path')->nullable();

            $table->string('status', 20)->default('draft'); // Enums\ContentStatus
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'sort_order']);
            // Bộ ba lọc của khối "Chọn mục tiêu hoặc lộ trình" ở trang chủ.
            $table->index(['grade_from', 'grade_to', 'language']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_paths');
    }
};

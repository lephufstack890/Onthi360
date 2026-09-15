<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * C1 — Nối khoá học với sản phẩm bán nó.
 *
 * ── Vì sao không thêm cột giá thẳng vào courses ──
 * Hệ thống đã có sẵn nguyên bộ Sản phẩm → Đơn hàng → Mã kích hoạt → Quyền truy cập, và
 * products đã có loại 'course' từ đầu nhưng chưa ai dùng. Thêm cột giá riêng cho courses là
 * dựng thêm một đường bán thứ hai chạy song song: hai chỗ tính tiền, hai chỗ cấp quyền, hai
 * chỗ phải sửa khi đổi luật. Nối sang products thì mua khoá học đi đúng con đường đã chạy ổn
 * cho sách và chuyên đề — không phải viết lại gì cả.
 *
 * ── Vì sao nullable ──
 * Khoá học có trước phần bán. Khoá chưa gắn sản phẩm vẫn chạy y như trước (vào bằng mã lớp),
 * chỉ là chưa mua trực tuyến được. Luật "bậc chưa có giá thì không cho đăng lộ trình" nằm ở
 * App\Support\LearningPathReadiness, không nằm ở tầng cơ sở dữ liệu.
 *
 * ── Vì sao nullOnDelete ──
 * Xoá sản phẩm thì khoá học vẫn còn nguyên cùng toàn bộ lớp và học sinh trong đó; chỉ mất
 * đường mua. Không bao giờ được để xoá sản phẩm kéo theo mất lớp học.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('cover_image_path')
                ->constrained('products')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_id');
        });
    }
};

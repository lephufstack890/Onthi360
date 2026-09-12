<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hoàn thiện "Gửi yêu cầu hỗ trợ" ở trang Thông tin công khai (INFO, mục Liên hệ).
 *
 * Bản đầu chỉ lưu được tên / email / nội dung rồi cho admin bấm "đã xử lý". Thực tế vận hành
 * cần thêm:
 *   · ticket_code — mã phiếu đưa cho người gửi để hai bên nói chuyện cùng một mã.
 *   · topic       — loại yêu cầu, để lọc và định tuyến (Enums\SupportTopic).
 *   · phone       — nhiều phụ huynh chỉ tiện nghe điện thoại, không dùng email.
 *   · user_id     — người gửi lúc đó đang đăng nhập thì biết ngay là tài khoản nào.
 *   · ip_hash     — CHỈ lưu bản băm, KHÔNG lưu địa chỉ IP thô. Đủ để nhận ra một nguồn spam
 *                   gửi hàng loạt, nhưng không biến bảng này thành nơi lưu dấu vết truy cập
 *                   của học sinh. Băm kèm APP_KEY nên rời khỏi hệ thống là vô nghĩa.
 *   · admin_note  — ghi chú nội bộ giữa các quản trị viên, KHÔNG hiện ra ngoài.
 *
 * Cột status vẫn là chuỗi cũ, chỉ thêm giá trị mới ('in_progress', 'spam') ở tầng Enum nên
 * dữ liệu đang có ('new' / 'resolved') giữ nguyên, không cần chuyển đổi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->string('ticket_code', 24)->nullable()->unique()->after('id');
            $table->string('topic', 30)->nullable()->after('message'); // Enums\SupportTopic
            $table->string('phone', 30)->nullable()->after('email');
            $table->foreignId('user_id')->nullable()->after('phone')->constrained('users')->nullOnDelete();
            $table->char('ip_hash', 64)->nullable()->after('topic');
            $table->text('admin_note')->nullable()->after('handled_at');

            $table->index(['status', 'topic']);
        });

        // Phiếu cũ chưa có mã thì cấp mã theo đúng công thức của ContactService để màn quản
        // trị không có ô trống. Dùng ngày tạo của chính phiếu đó, không dùng ngày chạy lệnh.
        DB::table('contact_messages')->whereNull('ticket_code')->orderBy('id')
            ->each(function ($row) {
                DB::table('contact_messages')->where('id', $row->id)->update([
                    'ticket_code' => 'HT'.date('ym', strtotime($row->created_at ?? 'now')).'-'.str_pad((string) $row->id, 5, '0', STR_PAD_LEFT),
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropIndex(['status', 'topic']);
            $table->dropColumn(['ticket_code', 'topic', 'phone', 'user_id', 'ip_hash', 'admin_note']);
        });
    }
};

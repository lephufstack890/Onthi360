<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 11/9 (dựng lại màn Đăng nhập/Đăng ký theo source giao diện khách gửi —
 * education-main/src/components/AccessCenterModal.jsx, bước 2 "Xác minh tài khoản").
 *
 * Bản mẫu có bước nhập mã 6 số nhưng chỉ là mô phỏng ở trình duyệt. Ở đây làm THẬT:
 * mã được sinh ngẫu nhiên, băm rồi lưu tại bảng này kèm hạn dùng, gửi tới email người
 * đăng ký. Chỉ khi nhập đúng mã còn hạn mới sang được bước chọn vai trò.
 *
 * Vì sao KHÔNG tạo sẵn bản ghi users rồi mới xác minh: người bỏ dở giữa chừng sẽ để lại
 * tài khoản "mồ côi" chưa có vai trò, chiếm luôn email đó khiến họ không đăng ký lại được.
 * Bảng tạm này giữ toàn bộ thông tin đăng ký (mật khẩu đã băm sẵn) và chỉ tới bước cuối
 * mới tạo User thật — xem App\Services\Auth\RegistrationService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_verifications', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->index();
            $table->string('phone')->nullable();
            // Mật khẩu đã băm ngay từ bước 1 — không bao giờ lưu mật khẩu thô ở bảng tạm.
            $table->string('password');
            // Mã 6 số cũng băm, không lưu mã thô: log/bản sao lưu lộ ra cũng không dùng lại được.
            $table->string('code_hash');
            $table->dateTime('expires_at');
            // Chặn dò mã: quá số lần nhập sai cho phép thì phải yêu cầu gửi lại mã mới.
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->dateTime('verified_at')->nullable();
            // Token trả về sau khi xác minh đúng; bước cuối phải gửi kèm token này để
            // chứng minh email đã qua xác minh, không thể bỏ qua bước 2 bằng cách POST thẳng.
            $table->string('claim_token', 64)->nullable()->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_verifications');
    }
};

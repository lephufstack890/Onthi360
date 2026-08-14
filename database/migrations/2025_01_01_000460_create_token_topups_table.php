<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Yêu cầu nạp token qua chuyển khoản ngân hàng (note họp 13/8, mục 7-8: "Nộp tiền thành
 * token" + "thông tin ngân hàng – QR người dùng chỉ cần chuyển khoản là xong"). P0 chưa có
 * cổng thanh toán tự động — mỗi yêu cầu có transfer_code duy nhất để đối soát thủ công,
 * Admin duyệt tay mới thật sự cộng token (App\Services\WalletService::approveTopup()) —
 * cùng triết lý "tạo yêu cầu ≠ đã chuyển khoản ≠ đã cộng token" với Order (7.4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('token_topups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('amount');
            $table->string('transfer_code', 30)->unique();
            $table->string('status', 20)->default('pending'); // Enums\TokenTopupStatus
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('reject_reason')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('token_topups');
    }
};

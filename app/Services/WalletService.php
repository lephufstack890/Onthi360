<?php

namespace App\Services;

use App\Enums\TokenTopupStatus;
use App\Models\TokenTopup;
use App\Models\User;
use App\Repositories\Contracts\TokenTopupRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * "Nộp tiền thành token" + "Khi đăng ký thi thì thông tin ngân hàng – QR người dùng chỉ
 * cần chuyển khoản là xong" (note họp 13/8, mục 7-8): học sinh nạp tiền MỘT LẦN thành số
 * dư token dùng chung cho nhiều lần thanh toán sau này (đăng ký thi, mua học liệu, ...),
 * thay vì phải chuyển khoản riêng lẻ theo từng đơn.
 *
 * P0 chưa nối cổng thanh toán tự động (VNPAY, v.v. — xem App\Enums\PaymentMethod, cùng ghi
 * chú "sắp mở" ở access.checkout) — mỗi yêu cầu nạp có transfer_code DUY NHẤT để Admin đối
 * soát sao kê ngân hàng thủ công rồi mới cộng token (approveTopup()), đúng triết lý "tạo
 * yêu cầu ≠ đã chuyển khoản ≠ đã cộng token" — cùng bất biến với Order/AccessRight (7.4,
 * xem App\Services\OrderActivationService).
 */
class WalletService
{
    private const MIN_TOPUP_AMOUNT = 10000;

    public function __construct(
        private readonly TokenTopupRepositoryInterface $topups,
        private readonly SystemSettingService $systemSettings,
    ) {}

    public function balanceFor(User $user): int
    {
        return (int) $user->token_balance;
    }

    /** access.wallet.index — lịch sử yêu cầu nạp của user này, mới nhất trước. */
    public function historyFor(User $user, int $limit = 50): array
    {
        return $this->topups->forUser($user->id, $limit)->map(fn (TokenTopup $t) => [
            'id' => $t->id,
            'amount' => $t->amount,
            'transferCode' => $t->transfer_code,
            'status' => $this->statusLabel($t->status),
            'tone' => $this->statusTone($t->status),
            'rejectReason' => $t->reject_reason,
            'createdAt' => $t->created_at,
        ])->all();
    }

    /** Yêu cầu đang "Chờ duyệt" gần nhất của user này (nếu có) — dùng để hiện lại QR/mã chuyển khoản khi quay lại trang. */
    public function latestPendingFor(User $user): ?TokenTopup
    {
        return $this->topups->query()
            ->where('user_id', $user->id)
            ->where('status', TokenTopupStatus::Pending->value)
            ->latest()
            ->first();
    }

    /**
     * @throws ValidationException nếu số tiền dưới mức tối thiểu, hoặc user còn 1 yêu cầu
     *                              đang chờ duyệt (tránh sinh nhiều mã chuyển khoản cùng lúc
     *                              gây khó đối soát).
     */
    public function requestTopup(User $user, int $amount): TokenTopup
    {
        if ($amount < self::MIN_TOPUP_AMOUNT) {
            throw ValidationException::withMessages([
                'amount' => 'Số tiền nạp tối thiểu là '.number_format(self::MIN_TOPUP_AMOUNT).'đ.',
            ]);
        }

        if ($this->latestPendingFor($user) !== null) {
            throw ValidationException::withMessages([
                'amount' => 'Bạn còn 1 yêu cầu nạp token đang chờ duyệt — vui lòng chờ xử lý xong hoặc liên hệ admin nếu đã chuyển khoản lâu mà chưa thấy cộng.',
            ]);
        }

        do {
            $code = 'NAP'.strtoupper(Str::random(6));
        } while ($this->topups->query()->where('transfer_code', $code)->exists());

        return $this->topups->create([
            'user_id' => $user->id,
            'amount' => $amount,
            'transfer_code' => $code,
            'status' => TokenTopupStatus::Pending->value,
        ]);
    }

    /**
     * Thông tin ngân hàng nhận chuyển khoản — cấu hình qua System Settings (18.8: Super
     * Admin chỉnh ở Cấu hình hệ thống, không hard-code số tài khoản thật trong code).
     *
     * @return array{bankName: string, accountNo: string, accountName: string, bin: string}
     */
    public function bankInfo(): array
    {
        return [
            'bankName' => $this->systemSettings->getString('wallet.bank_name', ''),
            'accountNo' => $this->systemSettings->getString('wallet.bank_account_no', ''),
            'accountName' => $this->systemSettings->getString('wallet.bank_account_name', ''),
            'bin' => $this->systemSettings->getString('wallet.bank_bin', ''),
        ];
    }

    /**
     * Ảnh QR chuyển khoản (VietQR — dịch vụ ảnh công khai theo chuẩn NAPAS, không cần thêm
     * thư viện tạo QR mới, đúng docs/ARCHITECTURE.md mục 1: ưu tiên không thêm dependency).
     * Trả null nếu Super Admin chưa cấu hình đủ BIN + số tài khoản ở Cấu hình hệ thống.
     */
    public function qrUrl(TokenTopup $topup): ?string
    {
        $bank = $this->bankInfo();

        if (blank($bank['bin']) || blank($bank['accountNo'])) {
            return null;
        }

        return sprintf(
            'https://img.vietqr.io/image/%s-%s-compact2.png?amount=%d&addInfo=%s&accountName=%s',
            rawurlencode($bank['bin']),
            rawurlencode($bank['accountNo']),
            $topup->amount,
            rawurlencode($topup->transfer_code),
            rawurlencode($bank['accountName'])
        );
    }

    /** @throws ValidationException nếu yêu cầu không còn ở trạng thái chờ duyệt (đã xử lý trước đó). */
    public function approveTopup(User $admin, TokenTopup $topup): TokenTopup
    {
        if ($topup->status !== TokenTopupStatus::Pending) {
            throw ValidationException::withMessages(['topup' => 'Yêu cầu này đã được xử lý trước đó.']);
        }

        DB::transaction(function () use ($admin, $topup) {
            $topup->update([
                'status' => TokenTopupStatus::Approved->value,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);

            $topup->user()->increment('token_balance', $topup->amount);
        });

        return $topup->fresh();
    }

    /** @throws ValidationException nếu yêu cầu không còn ở trạng thái chờ duyệt (đã xử lý trước đó). */
    public function rejectTopup(User $admin, TokenTopup $topup, string $reason): TokenTopup
    {
        if ($topup->status !== TokenTopupStatus::Pending) {
            throw ValidationException::withMessages(['topup' => 'Yêu cầu này đã được xử lý trước đó.']);
        }

        $topup->update([
            'status' => TokenTopupStatus::Rejected->value,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
            'reject_reason' => $reason,
        ]);

        return $topup->fresh();
    }

    /** admin.orders.index — "Chờ duyệt" lên trước, rồi tới các yêu cầu đã xử lý gần đây. */
    public function pendingAndRecentForAdmin(int $limit = 20): array
    {
        return $this->topups->pendingAndRecent($limit)->map(fn (TokenTopup $t) => [
            'id' => $t->id,
            'user' => $t->user->name ?? '',
            'amount' => number_format($t->amount).'đ',
            'transferCode' => $t->transfer_code,
            'status' => $this->statusLabel($t->status),
            'tone' => $this->statusTone($t->status),
            'isPending' => $t->status === TokenTopupStatus::Pending,
            'createdAt' => $t->created_at,
        ])->all();
    }

    private function statusLabel(TokenTopupStatus $status): string
    {
        return match ($status) {
            TokenTopupStatus::Pending => 'Chờ duyệt',
            TokenTopupStatus::Approved => 'Đã cộng token',
            TokenTopupStatus::Rejected => 'Từ chối',
        };
    }

    private function statusTone(TokenTopupStatus $status): string
    {
        return match ($status) {
            TokenTopupStatus::Pending => 'warning',
            TokenTopupStatus::Approved => 'success',
            TokenTopupStatus::Rejected => 'danger',
        };
    }
}

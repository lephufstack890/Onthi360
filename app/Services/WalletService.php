<?php

namespace App\Services;

use App\Enums\TokenTopupStatus;
use App\Models\TokenTopup;
use App\Models\User;
use App\Repositories\Contracts\TokenTopupRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

    /** SỬA 2/11 (khách: "mỗi lần nạp tối đa chỉ 500k thôi") — số tiền tối đa của MỘT yêu cầu nạp. */
    public const MAX_TOPUP_AMOUNT = 500000;

    /** SỬA 21/10 — khoá cấu hình chứa đường dẫn ảnh QR ngân hàng (đĩa local), admin tải ở Cấu hình hệ thống. */
    public const QR_SETTING = 'wallet.bank_qr_image';

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
            'status' => $this->statusLabel($t),
            'tone' => $this->statusTone($t),
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

        if ($amount > self::MAX_TOPUP_AMOUNT) {
            throw ValidationException::withMessages([
                'amount' => 'Mỗi lần nạp tối đa '.number_format(self::MAX_TOPUP_AMOUNT, 0, ',', '.').'đ. Cần nhiều hơn thì nạp thêm lần sau khi lần này được duyệt.',
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
     * @return array{bankName: string, accountNo: string, accountName: string, qrUrl: ?string}
     */
    public function bankInfo(): array
    {
        return [
            'bankName' => $this->systemSettings->getString('wallet.bank_name', ''),
            'accountNo' => $this->systemSettings->getString('wallet.bank_account_no', ''),
            'accountName' => $this->systemSettings->getString('wallet.bank_account_name', ''),
            // SỬA 21/10 — thay Mã BIN bằng ảnh QR admin tải lên (null = chưa có ảnh).
            'qrUrl' => $this->qrImageUrl(),
        ];
    }

    /** SỬA 21/10 — đường dẫn ảnh QR trên đĩa local; null nếu chưa tải hoặc tệp đã mất. */
    public function qrImagePath(): ?string
    {
        $path = trim($this->systemSettings->getString(self::QR_SETTING, ''));

        return $path !== '' && Storage::disk('local')->exists($path) ? $path : null;
    }

    /** SỬA 21/10 — link ảnh QR (route wallet.qr); ?v= đổi theo tệp để trình duyệt không giữ ảnh cũ sau khi admin thay. */
    public function qrImageUrl(): ?string
    {
        $path = $this->qrImagePath();

        // SỬA 3/11 — thêm hậu tố "c1" (ảnh đã cắt lấy thẻ QR) để trình duyệt tải bản mới thay vì ảnh cũ đã nhớ.
        return $path !== null ? route('wallet.qr', ['v' => substr(md5($path.'|c1'), 0, 10)]) : null;
    }

    /**
     * SỬA 3/11 (khách: "ảnh QR nhỏ quá, bỏ khung xung quanh, chỉ lấy thẻ ở giữa") — ảnh đem HIỂN THỊ: thẻ trắng chứa mã QR
     * cắt tự động từ ảnh admin tải lên (App\Support\QrCardCropper), lưu sẵn 1 lần cạnh ảnh gốc. Không cắt được thì dùng ảnh
     * gốc. Ảnh gốc vẫn giữ nguyên, admin thay ảnh là tự cắt lại.
     */
    public function qrDisplayPath(): ?string
    {
        $path = $this->qrImagePath();
        if ($path === null) {
            return null;
        }
        $disk = Storage::disk('local');
        try {
            $key = 'wallet/qr-card-'.md5($path.'|'.$disk->size($path).'|'.$disk->lastModified($path).'|c1');
        } catch (\Throwable) {
            return $path;
        }
        if ($disk->exists($key.'.png')) {
            return $key.'.png';
        }
        if ($disk->exists($key.'.none')) {
            return $path; // đã thử, ảnh này không cắt được
        }
        try {
            $png = \App\Support\QrCardCropper::crop((string) $disk->get($path));
        } catch (\Throwable) {
            $png = null;
        }
        if ($png === null) {
            $disk->put($key.'.none', '');

            return $path;
        }
        $disk->put($key.'.png', $png);

        return $key.'.png';
    }

    /**
     * Ảnh QR chuyển khoản cho 1 yêu cầu nạp.
     *
     * SỬA 21/10 (khách: "thay Mã BIN bằng chọn hình ảnh QR ngân hàng") — TRƯỚC ĐÂY tự tạo ảnh VietQR từ Mã BIN + số tài
     * khoản (kèm sẵn số tiền + nội dung CK). GIỜ dùng ẢNH QR admin tải lên ở Cấu hình hệ thống (QR tĩnh của tài khoản, lấy
     * từ app ngân hàng) — ảnh này KHÔNG chứa số tiền / nội dung, nên trang Ví token nhắc người nạp tự nhập đúng 2 thứ đó.
     * Trả null nếu chưa có ảnh -> Ví token chỉ hiện thông tin chuyển khoản tay như trước.
     */
    public function qrUrl(TokenTopup $topup): ?string
    {
        return $this->qrImageUrl();
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
    /*
     * SỬA 15/9 (khách: "để biết được ai nạp tiền còn vô mà duyệt") — số yêu cầu nạp token đang
     * chờ duyệt, cho viên số đỏ cạnh mục menu "Đơn hàng". Đây mới ĐÚNG là "nạp tiền": người học
     * chuyển khoản để đổi lấy token, quản trị viên phải đối chiếu sao kê rồi bấm duyệt thì token
     * mới được cộng (xem approveTopup). Khác với đơn mua hàng — 2 hàng chờ riêng nhưng cùng nằm
     * trên màn admin.orders.index nên viên số cộng cả hai.
     */
    public function pendingTopupCount(): int
    {
        // SỬA 3/11 — chỉ đếm yêu cầu người nạp ĐÃ BÁO chuyển khoản (việc admin cần làm ngay); yêu cầu mới tạo mà
        // chưa chuyển vẫn hiện trong danh sách với nhãn "Chờ chuyển khoản". Chưa chạy migrate thì đếm như cũ.
        if (! self::hasConfirmColumn()) {
            return $this->topups->countPending();
        }

        return $this->topups->query()
            ->where('status', TokenTopupStatus::Pending->value)
            ->whereNotNull('transfer_confirmed_at')
            ->count();
    }

    /**
     * SỬA 3/11 (khách: "chuyển khoản xong bấm nút thì báo admin, lúc đó mới chuyển sang Chờ duyệt") — người nạp bấm
     * "Tôi đã chuyển khoản": ghi lúc báo + gửi thông báo cho quản trị viên có quyền duyệt. Chỉ chủ yêu cầu, chỉ khi còn
     * chờ, chỉ MỘT lần (bấm lại không gửi thông báo lặp).
     *
     * @throws ValidationException
     */
    public function confirmTransfer(User $user, TokenTopup $topup): TokenTopup
    {
        if ((int) $topup->user_id !== (int) $user->id) {
            abort(404);
        }
        if ($topup->status !== TokenTopupStatus::Pending) {
            throw ValidationException::withMessages(['topup' => 'Yêu cầu nạp này đã được xử lý.']);
        }
        if (! self::hasConfirmColumn()) {
            throw ValidationException::withMessages(['topup' => 'Hệ thống đang cập nhật — vui lòng thử lại sau ít phút.']);
        }
        if ($topup->transfer_confirmed_at !== null) {
            return $topup;
        }

        $topup->forceFill(['transfer_confirmed_at' => now()])->save();

        try {
            $topup->loadMissing('user');
            $recipients = $this->topupReviewers();
            if ($recipients->isNotEmpty()) {
                \Illuminate\Support\Facades\Notification::send($recipients, new \App\Notifications\TokenTopupTransferConfirmed($topup));
            }
        } catch (\Throwable $e) {
            // Gửi thông báo lỗi không được làm hỏng việc báo chuyển khoản — admin vẫn thấy ở danh sách + số đỏ.
            \Illuminate\Support\Facades\Log::warning('Không gửi được thông báo nạp token: '.$e->getMessage(), ['topup' => $topup->id]);
        }

        return $topup;
    }

    /** Người nhận thông báo "đã chuyển khoản": admin, super admin và biên tập viên được cấp quyền duyệt đơn hàng. */
    private function topupReviewers(): \Illuminate\Support\Collection
    {
        return User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', [\App\Models\Role::ADMIN, \App\Models\Role::SUPER_ADMIN, \App\Models\Role::EDITOR]))
            ->with('roles')
            ->get()
            ->filter(fn (User $u) => $u->roles->contains(fn ($r) => in_array($r->name, [\App\Models\Role::ADMIN, \App\Models\Role::SUPER_ADMIN], true))
                || \App\Support\AdminPermissions::can($u, 'orders', 'approve'))
            ->values();
    }

    /** Cột token_topups.transfer_confirmed_at đã có chưa (migration 2025_01_01_001360) — chưa có thì mọi thứ chạy như cũ. */
    public static function hasConfirmColumn(): bool
    {
        static $has = null;

        return $has ??= \Illuminate\Support\Facades\Schema::hasColumn('token_topups', 'transfer_confirmed_at');
    }

    /** Lúc người nạp báo đã chuyển khoản (null = chưa báo). Trước khi có cột: coi như đã báo, giữ nhãn "Chờ duyệt" như cũ. */
    public function transferConfirmedAt(TokenTopup $topup): ?\DateTimeInterface
    {
        if (! self::hasConfirmColumn()) {
            return $topup->created_at;
        }
        $v = $topup->transfer_confirmed_at;

        return $v === null ? null : \Illuminate\Support\Carbon::parse($v);
    }

    public function pendingAndRecentForAdmin(int $limit = 20): array
    {
        return $this->topups->pendingAndRecent($limit)->map(fn (TokenTopup $t) => [
            'id' => $t->id,
            'user' => $t->user->name ?? '',
            'amount' => \App\Support\TokenPrice::vnd($t->amount).' → '.\App\Support\TokenPrice::label($t->amount), // SỬA 18/10
            'transferCode' => $t->transfer_code,
            'status' => $this->statusLabel($t),
            'tone' => $this->statusTone($t),
            'isPending' => $t->status === TokenTopupStatus::Pending,
            'transferConfirmedAt' => $this->transferConfirmedAt($t),
            'createdAt' => $t->created_at,
        ])->all();
    }

    private function statusLabel(TokenTopup $topup): string
    {
        return match ($topup->status) {
            TokenTopupStatus::Pending => $this->transferConfirmedAt($topup) === null ? 'Chờ chuyển khoản' : 'Chờ duyệt',
            TokenTopupStatus::Approved => 'Đã cộng token',
            TokenTopupStatus::Rejected => 'Từ chối',
        };
    }

    private function statusTone(TokenTopup $topup): string
    {
        return match ($topup->status) {
            TokenTopupStatus::Pending => $this->transferConfirmedAt($topup) === null ? 'neutral' : 'warning',
            TokenTopupStatus::Approved => 'success',
            TokenTopupStatus::Rejected => 'danger',
        };
    }
}

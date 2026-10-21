<?php

namespace App\Services\Admin;

use App\Models\RatingSummary;
use App\Models\User;
use App\Repositories\Contracts\SystemSettingRepositoryInterface;
use App\Services\WalletService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Cấu hình hệ thống (3.1, 18.8) — CHỈ Super Admin (route bị chặn bởi
 * role:super_admin, xem routes/web.php). "Chính sách đánh giá" và "Tích hợp
 * thanh toán" (ngân hàng nhận chuyển khoản token — note họp 13/8, mục 7-8:
 * "Nộp tiền thành token" + QR) đã nối logic lưu thật; 2 khối còn lại (vai
 * trò & quyền, OCR) chưa nối logic lưu thật — giữ nguyên khung UI "Sắp mở"
 * để không hứa nhầm chức năng chưa có (2.2).
 */
class SettingsService
{
    private const RATING_THRESHOLD_KEY = 'rating.min_reviews_to_rank';

    private const WALLET_BANK_KEYS = [
        'bankName' => 'wallet.bank_name',
        'accountNo' => 'wallet.bank_account_no',
        'accountName' => 'wallet.bank_account_name',
        // SỬA 21/10 — thay Mã BIN (wallet.bank_bin, không còn dùng) bằng ẢNH QR ngân hàng do admin tải lên.
        'qr' => WalletService::QR_SETTING,
    ];

    public function __construct(private SystemSettingRepositoryInterface $settings) {}

    public function indexData(): array
    {
        $setting = $this->settings->findByKey(self::RATING_THRESHOLD_KEY);

        return [
            'ratingThreshold' => $setting?->intValue(RatingSummary::MIN_REVIEWS_TO_RANK) ?? RatingSummary::MIN_REVIEWS_TO_RANK,
            'ratingThresholdUpdatedBy' => $setting?->updatedBy?->name,
            'ratingThresholdUpdatedAt' => $setting?->updated_at,
            'walletBankInfo' => $this->walletBankInfoData(),
        ];
    }

    public function updateRatingThreshold(User $admin, int $value): void
    {
        if ($value < 1) {
            throw ValidationException::withMessages([
                'min_reviews_to_rank' => 'Ngưỡng phải là số nguyên từ 1 trở lên.',
            ]);
        }

        $setting = $this->settings->findByKey(self::RATING_THRESHOLD_KEY);

        if ($setting === null) {
            $this->settings->create([
                'key' => self::RATING_THRESHOLD_KEY,
                'value' => (string) $value,
                'type' => 'integer',
                'label' => 'Ngưỡng số review tối thiểu để công bố xếp hạng',
                'updated_by' => $admin->id,
            ]);

            return;
        }

        $this->settings->update($setting, [
            'value' => (string) $value,
            'updated_by' => $admin->id,
        ]);
    }

    /**
     * "Tích hợp thanh toán" — thông tin ngân hàng nhận chuyển khoản nạp token, đọc bởi
     * App\Services\WalletService::bankInfo() để hiện ở access.wallet (QR + số tài khoản).
     *
     * @return array{bankName: ?string, accountNo: ?string, accountName: ?string, qrUrl: ?string, updatedBy: ?string, updatedAt: mixed}
     */
    public function walletBankInfoData(): array
    {
        $accountNoSetting = $this->settings->findByKey(self::WALLET_BANK_KEYS['accountNo']);

        return [
            'bankName' => $this->settings->findByKey(self::WALLET_BANK_KEYS['bankName'])?->stringValue(),
            'accountNo' => $accountNoSetting?->stringValue(),
            'accountName' => $this->settings->findByKey(self::WALLET_BANK_KEYS['accountName'])?->stringValue(),
            // SỬA 21/10 — xem trước ảnh QR đang dùng (cùng đường ảnh học sinh thấy ở Ví token).
            'qrUrl' => app(WalletService::class)->qrImageUrl(),
            'updatedBy' => $accountNoSetting?->updatedBy?->name,
            'updatedAt' => $accountNoSetting?->updated_at,
        ];
    }

    /**
     * SỬA 21/10 (khách: "thay Mã BIN bằng chọn hình ảnh QR ngân hàng") — lưu 3 ô chữ như cũ + ảnh QR:
     *   · có tệp mới  -> lưu vào đĩa local (storage/app/wallet/…), xoá ảnh cũ;
     *   · tích "Xoá ảnh QR" (không chọn tệp mới) -> xoá ảnh, Ví token quay về chỉ hiện thông tin chuyển khoản tay;
     *   · không chọn gì -> giữ ảnh đang có.
     * Ảnh hiện cho người dùng qua route wallet.qr (WalletController::qrImage), không cần storage:link.
     *
     * @param  array{bank_name: string, bank_account_no: string, bank_account_name: string}  $data
     */
    public function updateWalletBankInfo(User $admin, array $data, ?UploadedFile $qr = null, bool $removeQr = false): void
    {
        $pairs = [
            self::WALLET_BANK_KEYS['bankName'] => ['value' => trim($data['bank_name']), 'label' => 'Tên ngân hàng nhận chuyển khoản nạp token'],
            self::WALLET_BANK_KEYS['accountNo'] => ['value' => trim($data['bank_account_no']), 'label' => 'Số tài khoản nhận chuyển khoản nạp token'],
            self::WALLET_BANK_KEYS['accountName'] => ['value' => trim($data['bank_account_name']), 'label' => 'Tên chủ tài khoản nhận chuyển khoản nạp token'],
        ];

        $oldQr = trim((string) $this->settings->findByKey(self::WALLET_BANK_KEYS['qr'])?->stringValue());
        if ($qr !== null) {
            $pairs[self::WALLET_BANK_KEYS['qr']] = ['value' => $qr->store('wallet', 'local'), 'label' => 'Ảnh QR ngân hàng nhận chuyển khoản nạp token'];
        } elseif ($removeQr) {
            $pairs[self::WALLET_BANK_KEYS['qr']] = ['value' => '', 'label' => 'Ảnh QR ngân hàng nhận chuyển khoản nạp token'];
        }

        foreach ($pairs as $key => $meta) {
            $setting = $this->settings->findByKey($key);

            if ($setting === null) {
                $this->settings->create([
                    'key' => $key,
                    'value' => $meta['value'],
                    'type' => 'string',
                    'label' => $meta['label'],
                    'updated_by' => $admin->id,
                ]);

                continue;
            }

            $this->settings->update($setting, [
                'value' => $meta['value'],
                'updated_by' => $admin->id,
            ]);
        }

        // Ảnh cũ không còn dùng (đã thay hoặc đã xoá) -> dọn khỏi đĩa.
        if (($qr !== null || $removeQr) && $oldQr !== '' && Storage::disk('local')->exists($oldQr)) {
            Storage::disk('local')->delete($oldQr);
        }
    }
}

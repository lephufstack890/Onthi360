<?php

namespace App\Services\Admin;

use App\Models\RatingSummary;
use App\Models\User;
use App\Repositories\Contracts\SystemSettingRepositoryInterface;
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
        'bin' => 'wallet.bank_bin',
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
     * @return array{bankName: ?string, accountNo: ?string, accountName: ?string, bin: ?string, updatedBy: ?string, updatedAt: mixed}
     */
    public function walletBankInfoData(): array
    {
        $accountNoSetting = $this->settings->findByKey(self::WALLET_BANK_KEYS['accountNo']);

        return [
            'bankName' => $this->settings->findByKey(self::WALLET_BANK_KEYS['bankName'])?->stringValue(),
            'accountNo' => $accountNoSetting?->stringValue(),
            'accountName' => $this->settings->findByKey(self::WALLET_BANK_KEYS['accountName'])?->stringValue(),
            'bin' => $this->settings->findByKey(self::WALLET_BANK_KEYS['bin'])?->stringValue(),
            'updatedBy' => $accountNoSetting?->updatedBy?->name,
            'updatedAt' => $accountNoSetting?->updated_at,
        ];
    }

    /** @param array{bank_name: string, bank_account_no: string, bank_account_name: string, bank_bin: string} $data */
    public function updateWalletBankInfo(User $admin, array $data): void
    {
        $pairs = [
            self::WALLET_BANK_KEYS['bankName'] => ['value' => trim($data['bank_name']), 'label' => 'Tên ngân hàng nhận chuyển khoản nạp token'],
            self::WALLET_BANK_KEYS['accountNo'] => ['value' => trim($data['bank_account_no']), 'label' => 'Số tài khoản nhận chuyển khoản nạp token'],
            self::WALLET_BANK_KEYS['accountName'] => ['value' => trim($data['bank_account_name']), 'label' => 'Tên chủ tài khoản nhận chuyển khoản nạp token'],
            self::WALLET_BANK_KEYS['bin'] => ['value' => trim($data['bank_bin']), 'label' => 'Mã BIN ngân hàng (theo chuẩn NAPAS, dùng để tạo QR VietQR)'],
        ];

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
    }
}

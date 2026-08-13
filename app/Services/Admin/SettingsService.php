<?php

namespace App\Services\Admin;

use App\Models\RatingSummary;
use App\Models\User;
use App\Repositories\Contracts\SystemSettingRepositoryInterface;
use Illuminate\Validation\ValidationException;

/**
 * Cấu hình hệ thống (3.1, 18.8) — CHỈ Super Admin (route bị chặn bởi
 * role:super_admin, xem routes/web.php). Hiện chỉ có 1 cấu hình thật (ngưỡng
 * số review tối thiểu để xếp hạng, 18.8); 3 khối còn lại (vai trò & quyền,
 * tích hợp thanh toán, OCR) chưa nối logic lưu thật — giữ nguyên khung UI
 * "Sắp mở" để không hứa nhầm chức năng chưa có (2.2).
 */
class SettingsService
{
    private const RATING_THRESHOLD_KEY = 'rating.min_reviews_to_rank';

    public function __construct(private SystemSettingRepositoryInterface $settings) {}

    public function indexData(): array
    {
        $setting = $this->settings->findByKey(self::RATING_THRESHOLD_KEY);

        return [
            'ratingThreshold' => $setting?->intValue(RatingSummary::MIN_REVIEWS_TO_RANK) ?? RatingSummary::MIN_REVIEWS_TO_RANK,
            'ratingThresholdUpdatedBy' => $setting?->updatedBy?->name,
            'ratingThresholdUpdatedAt' => $setting?->updated_at,
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
}

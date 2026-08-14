<?php

namespace App\Services;

use App\Repositories\Contracts\SystemSettingRepositoryInterface;

/**
 * Truy cập cấu hình hệ thống (system_settings) — thay cho hằng số hard-code,
 * cho phép Super Admin chỉnh ngưỡng nghiệp vụ không cần release code (18.8).
 * Nếu chưa có bản ghi cấu hình (chưa migrate, hoặc key không tồn tại), trả
 * về giá trị mặc định do nơi gọi truyền vào — không bao giờ throw.
 */
class SystemSettingService
{
    public function __construct(private SystemSettingRepositoryInterface $settings) {}

    public function getInt(string $key, int $default): int
    {
        $setting = $this->settings->findByKey($key);

        return $setting?->intValue($default) ?? $default;
    }

    public function getString(string $key, string $default): string
    {
        $setting = $this->settings->findByKey($key);

        return $setting?->stringValue($default) ?? $default;
    }
}

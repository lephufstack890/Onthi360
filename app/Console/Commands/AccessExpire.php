<?php

namespace App\Console\Commands;

use App\Enums\AccessRightStatus;
use App\Models\AccessRight;
use Illuminate\Console\Command;

/**
 * SỬA 20/10 (khách: "hết hạn là huỷ quyền, không cho đọc nữa") — chốt TRẠNG THÁI của quyền đã quá hạn.
 *
 * Việc CHẶN ĐỌC không phụ thuộc lệnh này: mọi cửa mở tài liệu (AccessGateService::hasActivePersonalAccess(),
 * AccessRight::isCurrentlyActive()) đều so expires_at với giờ máy chủ ở MỖI lần mở, nên vừa quá hạn là bị chặn ngay.
 * Lệnh này chỉ đổi cột status 'active' -> 'expired' cho các quyền đã quá hạn để dữ liệu / màn quản trị / báo cáo nói
 * đúng là "Đã hết hạn". Chạy theo lịch mỗi giờ (routes/console.php, cần cron `php artisan schedule:run` mỗi phút);
 * chạy tay: php artisan access:expire [--dry-run]. Mua / kích hoạt lại luôn tạo quyền MỚI (OrderActivationService::
 * activate()), không đụng dòng đã hết hạn.
 */
class AccessExpire extends Command
{
    protected $signature = 'access:expire {--dry-run : Chỉ đếm, không sửa}';

    protected $description = 'Đánh dấu "Đã hết hạn" cho các quyền tài liệu đã quá thời hạn.';

    public function handle(): int
    {
        $query = AccessRight::query()
            ->where('status', AccessRightStatus::Active->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());

        if ($this->option('dry-run')) {
            $this->info('[DRY RUN] Sẽ đánh dấu hết hạn: '.$query->count().' quyền.');

            return self::SUCCESS;
        }

        $count = $query->update(['status' => AccessRightStatus::Expired->value, 'updated_at' => now()]);
        $this->info('Đã đánh dấu hết hạn: '.$count.' quyền.');

        return self::SUCCESS;
    }
}

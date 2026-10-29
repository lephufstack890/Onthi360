<?php

namespace App\Console\Commands;

use App\Enums\AccessRightStatus;
use App\Models\AccessRight;
use Illuminate\Console\Command;


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

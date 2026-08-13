<?php

namespace App\Repositories\Contracts;

use App\Models\SystemSetting;

interface SystemSettingRepositoryInterface extends BaseRepositoryInterface
{
    public function findByKey(string $key): ?SystemSetting;
}

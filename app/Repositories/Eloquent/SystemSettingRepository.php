<?php

namespace App\Repositories\Eloquent;

use App\Models\SystemSetting;
use App\Repositories\Contracts\SystemSettingRepositoryInterface;

class SystemSettingRepository extends EloquentRepository implements SystemSettingRepositoryInterface
{
    protected string $modelClass = SystemSetting::class;

    public function findByKey(string $key): ?SystemSetting
    {
        return $this->query()->where('key', $key)->first();
    }
}

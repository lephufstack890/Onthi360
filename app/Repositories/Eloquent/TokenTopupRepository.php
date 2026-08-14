<?php

namespace App\Repositories\Eloquent;

use App\Models\TokenTopup;
use App\Repositories\Contracts\TokenTopupRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class TokenTopupRepository extends EloquentRepository implements TokenTopupRepositoryInterface
{
    protected string $modelClass = TokenTopup::class;

    public function forUser(int $userId, int $limit = 50): Collection
    {
        return $this->query()
            ->where('user_id', $userId)
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function pendingAndRecent(int $limit = 20): Collection
    {
        return $this->query()
            ->with('user')
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->latest()
            ->limit($limit)
            ->get();
    }
}

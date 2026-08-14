<?php

namespace App\Repositories\Eloquent;

use App\Models\SessionResource;
use App\Repositories\Contracts\SessionResourceRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class SessionResourceRepository extends EloquentRepository implements SessionResourceRepositoryInterface
{
    protected string $modelClass = SessionResource::class;

    public function forClassSession(int $classSessionId): Collection
    {
        return $this->query()
            ->where('class_session_id', $classSessionId)
            ->with(['material', 'question', 'assessment', 'addedBy'])
            ->latest()
            ->get();
    }
}

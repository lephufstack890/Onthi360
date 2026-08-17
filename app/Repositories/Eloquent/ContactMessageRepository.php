<?php

namespace App\Repositories\Eloquent;

use App\Models\ContactMessage;
use App\Repositories\Contracts\ContactMessageRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ContactMessageRepository extends EloquentRepository implements ContactMessageRepositoryInterface
{
    protected string $modelClass = ContactMessage::class;

    public function recent(int $limit = 100): Collection
    {
        return $this->query()->with('handledBy')->latest()->limit($limit)->get();
    }

    public function countNew(): int
    {
        return $this->query()->where('status', 'new')->count();
    }
}

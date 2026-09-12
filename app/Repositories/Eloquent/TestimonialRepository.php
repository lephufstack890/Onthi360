<?php

namespace App\Repositories\Eloquent;

use App\Enums\ContentStatus;
use App\Models\Testimonial;
use App\Repositories\Contracts\TestimonialRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class TestimonialRepository extends EloquentRepository implements TestimonialRepositoryInterface
{
    protected string $modelClass = Testimonial::class;

    public function publishedForHome(int $limit = 3): Collection
    {
        return $this->query()
            ->where('status', ContentStatus::Published->value)
            // Có hẹn giờ đăng thì chưa tới giờ chưa hiện; không đặt giờ thì hiện ngay.
            ->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()))
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function allForAdmin(): Collection
    {
        return $this->query()
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get();
    }

    public function maxSortOrder(): int
    {
        return (int) $this->query()->max('sort_order');
    }
}

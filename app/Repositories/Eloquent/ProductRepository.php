<?php

namespace App\Repositories\Eloquent;

use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ProductRepository extends EloquentRepository implements ProductRepositoryInterface
{
    protected string $modelClass = Product::class;

    public function latest(int $limit = 50): Collection
    {
        return $this->query()->latest()->limit($limit)->get();
    }
}

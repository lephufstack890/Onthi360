<?php

namespace App\Repositories\Eloquent;

use App\Models\Material;
use App\Repositories\Contracts\MaterialRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class MaterialRepository extends EloquentRepository implements MaterialRepositoryInterface
{
    protected string $modelClass = Material::class;

    public function latestWithProduct(int $limit = 50): Collection
    {
        return $this->query()->with('product')->latest()->limit($limit)->get();
    }

    public function findWithProduct(int $id): ?Material
    {
        return $this->query()->with('product')->find($id);
    }
}

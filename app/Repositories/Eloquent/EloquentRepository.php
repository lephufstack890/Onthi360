<?php

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\BaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

abstract class EloquentRepository implements BaseRepositoryInterface
{
    /** @var class-string<Model> */
    protected string $modelClass;

    public function query(): Builder
    {
        return ($this->modelClass)::query();
    }

    public function find(int $id): ?Model
    {
        return $this->query()->find($id);
    }

    public function findOrFail(int $id): Model
    {
        return $this->query()->findOrFail($id);
    }

    public function all(): Collection
    {
        return $this->query()->get();
    }

    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        return $this->query()->paginate($perPage);
    }

    public function count(): int
    {
        return $this->query()->count();
    }

    public function create(array $attributes): Model
    {
        return ($this->modelClass)::create($attributes);
    }

    public function update(Model $model, array $attributes): Model
    {
        $model->update($attributes);

        return $model;
    }

    public function delete(Model $model): bool
    {
        return (bool) $model->delete();
    }
}

<?php

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Contract chung cho mọi Repository.
 *
 * query() được để lộ ra có chủ đích: nó là "van an toàn" cho tầng Service
 * khi cần lắp thêm điều kiện/eager-load động mà không phải định nghĩa
 * một phương thức riêng cho từng biến thể truy vấn. Các phương thức còn lại
 * là CRUD tối giản dùng chung cho tất cả Model.
 */
interface BaseRepositoryInterface
{
    public function query(): Builder;

    public function find(int $id): ?Model;

    public function findOrFail(int $id): Model;

    public function all(): Collection;

    public function paginate(int $perPage = 20): LengthAwarePaginator;

    public function count(): int;

    public function create(array $attributes): Model;

    public function update(Model $model, array $attributes): Model;

    public function delete(Model $model): bool;
}

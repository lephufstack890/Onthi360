<?php

namespace App\Repositories\Eloquent;

use App\Models\Tag;
use App\Repositories\Contracts\TagRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class TagRepository extends EloquentRepository implements TagRepositoryInterface
{
    protected string $modelClass = Tag::class;

    public function allOrderedByName(): Collection
    {
        return $this->query()->orderBy('name')->get();
    }

    /**
     * firstOrCreate() dựa vào collation mặc định của cột 'name' (utf8mb4_unicode_ci — không
     * phân biệt hoa/thường) để tự khớp "đại số" với "Đại Số" đã có sẵn, tránh tự sinh thêm
     * tag gần trùng chỉ vì khác cách viết hoa — KHÔNG tự viết thêm điều kiện LOWER() ở đây.
     */
    public function findOrCreateByName(string $name): Tag
    {
        return Tag::firstOrCreate(['name' => trim($name)]);
    }
}

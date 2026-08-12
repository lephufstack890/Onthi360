<?php

namespace App\Repositories\Eloquent;

use App\Models\ClassMaterial;
use App\Repositories\Contracts\ClassMaterialRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ClassMaterialRepository extends EloquentRepository implements ClassMaterialRepositoryInterface
{
    protected string $modelClass = ClassMaterial::class;

    public function activeForClassRoom(int $classRoomId): Collection
    {
        return $this->query()
            ->where('class_room_id', $classRoomId)
            ->where('status', 'active')
            ->with('material')
            ->get();
    }

    public function activeForClassRoomWithProduct(int $classRoomId): Collection
    {
        return $this->query()
            ->where('class_room_id', $classRoomId)
            ->where('status', 'active')
            ->with(['material', 'product'])
            ->get();
    }
}

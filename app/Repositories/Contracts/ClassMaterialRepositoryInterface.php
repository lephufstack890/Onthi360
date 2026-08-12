<?php

namespace App\Repositories\Contracts;

use App\Models\ClassMaterial;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

interface ClassMaterialRepositoryInterface extends BaseRepositoryInterface
{

    public function activeForClassRoom(int $classRoomId): Collection;

    public function activeForClassRoomWithProduct(int $classRoomId): Collection;
}

<?php

namespace App\Repositories\Eloquent;

use App\Models\QuestionBank;
use App\Repositories\Contracts\QuestionBankRepositoryInterface;

class QuestionBankRepository extends EloquentRepository implements QuestionBankRepositoryInterface
{
    protected string $modelClass = QuestionBank::class;

    public function findPersonalBank(int $teacherId): ?QuestionBank
    {
        return $this->query()->where('owner_type', 'teacher')->where('owner_id', $teacherId)->first();
    }
}

<?php

namespace App\Repositories\Contracts;

use App\Models\QuestionBank;

interface QuestionBankRepositoryInterface extends BaseRepositoryInterface
{
    public function findPersonalBank(int $teacherId): ?QuestionBank;
}

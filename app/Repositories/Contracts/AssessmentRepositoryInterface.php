<?php

namespace App\Repositories\Contracts;

use App\Models\Assessment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

interface AssessmentRepositoryInterface extends BaseRepositoryInterface
{

    /**
     * SỬA 18/8 (luồng Luyện tập — bộ lọc thật): $questionType (App\Enums\QuestionType, vd
     * 'mcq'/'fill_blank'/'coding') và $bankName (App\Models\QuestionBank::name — dùng tạm
     * làm "chuyên đề" vì hệ thống CHƯA có bảng Tag/Chuyên đề riêng) đều optional, null = không
     * lọc thêm (giữ đúng hành vi cũ cho mọi lời gọi hiện có không truyền 2 tham số này).
     */
    public function publishedPractice(int $limit = 30, ?string $questionType = null, ?string $bankName = null): Collection;

    public function countPublishedPractice(?string $questionType = null, ?string $bankName = null): int;

    public function withItemsAndQuestions(int $id): ?Assessment;

    public function latestWithCreator(int $limit = 50): Collection;

    public function byOwner(int $ownerId, int $limit = 50): Collection;
}

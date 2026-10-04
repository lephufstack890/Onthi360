<?php

namespace App\Repositories\Eloquent;

use App\Models\TeacherProfile;
use App\Repositories\Contracts\TeacherProfileRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class TeacherProfileRepository extends EloquentRepository implements TeacherProfileRepositoryInterface
{
    protected string $modelClass = TeacherProfile::class;

    public function pendingWithUser(): Collection
    {
        return $this->query()->where('approval_status', 'pending')->with('user')->latest()->get();
    }

    public function countPending(): int
    {
        return $this->query()->where('approval_status', 'pending')->count();
    }

    /**
     * Giáo viên đã duyệt CÓ TÀI KHOẢN — dùng cho mọi chỗ cần chọn ra một NGƯỜI DÙNG: gán giáo
     * viên cho lớp (Admin\CourseService), chọn cố vấn cuộc thi (Admin\CompetitionService).
     *
     * SỬA 4/10 — thêm whereNotNull('user_id'). Từ nay teacher_profiles cho phép hồ sơ "chỉ để
     * trưng bày" không gắn tài khoản nào (xem migration allow_standalone_teacher_profiles).
     * Không chặn ở đây thì CompetitionService đọc $tp->user->id trên một hồ sơ không có tài
     * khoản là vỡ trang ngay, còn CourseService đẻ ra lựa chọn giáo viên có id rỗng.
     */
    public function approvedWithUser(int $limit = 50): Collection
    {
        return $this->query()->where('approval_status', 'approved')->whereNotNull('user_id')
            ->with('user')->latest()->limit($limit)->get();
    }

    /**
     * SỬA 4/10 — danh sách cho MÀN VINH DANH: gồm CẢ hồ sơ trưng bày không có tài khoản.
     * Chuyên gia xếp trước, rồi tới hồ sơ sửa gần nhất — đúng thứ tự trang công khai đang dùng.
     */
    public function showcaseList(int $limit = 200): Collection
    {
        return $this->query()->where('approval_status', 'approved')
            ->with('user')->orderByDesc('is_expert')->latest('updated_at')->limit($limit)->get();
    }

    public function countApproved(): int
    {
        return $this->query()->where('approval_status', 'approved')->count();
    }

    public function findByUserId(int $userId): ?TeacherProfile
    {
        return $this->query()->where('user_id', $userId)->first();
    }
}

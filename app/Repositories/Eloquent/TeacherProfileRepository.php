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
     * SỬA 4/10 — danh sách cho MÀN VINH DANH: gồm CẢ hồ sơ trưng bày không có tài khoản, xếp
     * ĐÚNG thứ tự trang công khai (xem showcaseOrder()) để admin nhìn màn này là biết ngoài
     * kia đang hiện ra sao.
     */
    public function showcaseList(int $limit = 200): Collection
    {
        return self::showcaseOrder($this->query()->where('approval_status', 'approved')->with('user'))
            ->limit($limit)->get();
    }

    /**
     * THỨ TỰ DUY NHẤT của trang vinh danh, khai một chỗ cho cả màn admin lẫn trang công khai
     * (Public\TeacherService dùng lại hàm này). Hai nơi tự xếp riêng thì admin kéo thứ tự ở màn
     * này mà ngoài kia ra khác là chuyện sớm muộn.
     *
     * Ba nấc, theo đúng 2 yêu cầu của khách:
     *   1. is_expert giảm dần — "chuyên gia luôn được lên đầu danh sách" (yêu cầu 4/10 sáng).
     *   2. sort_order GIẢM DẦN — "thứ tự hiển thị số lớn đứng trước số nhỏ đứng sau, mặc định
     *      là 0" (khách chốt chiều 4/10). Mặc định 0 nên hồ sơ chưa ai đặt số sẽ nằm sau mọi
     *      hồ sơ đã được đẩy lên — đúng ý "muốn ai lên trước thì cho số cao".
     *   3. updated_at giảm dần — hai hồ sơ cùng số thì hồ sơ sửa gần nhất đứng trước, giữ đúng
     *      nếp cũ thay vì để thứ tự tuỳ hứng theo id.
     *
     * Lưu ý: sort_order xếp TRONG TỪNG NHÓM chuyên gia / không chuyên gia, không vượt qua được
     * nấc 1. Đặt số 1 cho một giáo viên thường thì họ đứng đầu nhóm giáo viên thường, vẫn sau
     * mọi chuyên gia.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function showcaseOrder(Builder $query): Builder
    {
        return $query->orderByDesc('is_expert')->orderByDesc('sort_order')->latest('updated_at');
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

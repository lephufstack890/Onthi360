<?php

namespace App\Services\Admin;

use App\Models\TeacherProfile;
use App\Repositories\Contracts\CompetitionRepositoryInterface;
use App\Repositories\Contracts\TeacherProfileRepositoryInterface;

/**
 * Gom truy vấn + hành động cho admin.featured-teachers.index — PUB-10 (trang vinh danh, 12.2).
 * Chỉ giáo viên Đã được duyệt mới cho vinh danh (App\Models\TeacherProfile::isFeatured()).
 */
class FeaturedTeacherService
{
    public function __construct(
        private CompetitionRepositoryInterface $competitions,
        private TeacherProfileRepositoryInterface $teacherProfiles,
    ) {}

    /** @return array{tabs: array, teachers: array} */
    public function indexData(): array
    {
        $tabs = [
            ['label' => 'Cuộc thi', 'href' => route('admin.competitions.index'), 'active' => false, 'count' => $this->competitions->count()],
            ['label' => 'Giáo viên và chuyên gia', 'href' => route('admin.featured-teachers.index'), 'active' => true, 'count' => $this->teacherProfiles->countApproved()],
        ];

        $teachers = $this->teacherProfiles->approvedWithUser(50)->map(fn (TeacherProfile $p) => [
            'profile_id' => $p->id,
            'name' => $p->user->name ?? '',
            'subject' => is_array($p->subjects) && count($p->subjects) > 0 ? $p->subjects[0] : '',
            'featured' => $p->is_featured,
            'expert' => (bool) $p->is_expert,
            'achievement' => $p->achievement_note ?? '',
        ])->all();

        return ['tabs' => $tabs, 'teachers' => $teachers];
    }

    /**
     * THÊM vào danh sách vinh danh — kèm ghi chú thành tích công khai (12.1 mục 8) và cờ
     * chuyên gia.
     *
     * SỬA 4/10 — ghi chú thành tích ở đây CÓ THỂ xoá trắng được (truyền chuỗi rỗng là xoá),
     * khác bản cũ. Bản cũ coi chuỗi rỗng là "không đổi" nên một khi đã nhập thành tích thì
     * không còn đường nào gỡ xuống — admin xoá trắng ô rồi bấm lưu mà nội dung vẫn nguyên.
     */
    public function feature(TeacherProfile $profile, ?string $achievementNote, bool $isExpert = false): TeacherProfile
    {
        $profile->update([
            'is_featured' => true,
            'is_expert' => $isExpert,
            'achievement_note' => $achievementNote !== null && trim($achievementNote) !== ''
                ? trim($achievementNote)
                : null,
        ]);

        return $profile;
    }

    /**
     * SỬA 4/10 (khách: "admin có thể thêm sửa xoá") — SỬA một người đang vinh danh.
     *
     * Trước đây chỉ có thêm và gỡ: muốn đổi câu thành tích đã công bố thì phải gỡ xuống rồi
     * vinh danh lại, trong khoảng đó người ấy biến mất khỏi trang công khai.
     */
    public function update(TeacherProfile $profile, ?string $achievementNote, bool $isExpert): TeacherProfile
    {
        $profile->update([
            'is_expert' => $isExpert,
            'achievement_note' => $achievementNote !== null && trim($achievementNote) !== ''
                ? trim($achievementNote)
                : null,
        ]);

        return $profile;
    }

    /**
     * XOÁ KHỎI DANH SÁCH VINH DANH — KHÔNG xoá hồ sơ giáo viên, KHÔNG xoá tài khoản.
     *
     * Màn này là trang vinh danh, "xoá" ở đây nghĩa là rút tên khỏi trang công khai. Xoá thật
     * hồ sơ là việc của màn duyệt giáo viên và kéo theo cả lớp, đánh giá, bài giao của người
     * đó — không đặt sau một nút ở trang vinh danh được.
     *
     * Giữ lại achievement_note và is_expert: vinh danh lại thì không phải gõ lại từ đầu.
     */
    public function unfeature(TeacherProfile $profile): TeacherProfile
    {
        $profile->update(['is_featured' => false]);

        return $profile;
    }
}

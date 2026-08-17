<?php

namespace App\Services\Public;

use App\Models\TeacherProfile;
use App\Repositories\Contracts\TeacherProfileRepositoryInterface;

/**
 * teachers.index (PUB-10, 12.2 "trang vinh danh, không phải danh bạ cá nhân") — CHỈ giáo
 * viên đã được Admin bấm "Vinh danh" (TeacherProfile::is_featured, xem
 * App\Services\Admin\FeaturedTeacherService::feature()) VÀ đã duyệt hồ sơ mới hiển thị công
 * khai. Trước đây route này là closure trả thẳng 4 dòng dữ liệu mẫu cứng
 * (Route::get('/giao-vien-tieu-bieu', fn () => view('public.teachers.index'))) — không có
 * Controller/Service nào đứng sau, không phải dữ liệu thật.
 */
class TeacherService
{
    public function __construct(private readonly TeacherProfileRepositoryInterface $teacherProfiles) {}

    /**
     * teachers.index — TOÀN BỘ giáo viên đang được vinh danh. Không phân trang: số lượng
     * vinh danh do chính Admin chủ động chọn (qua admin.featured-teachers.*) nên thực tế
     * luôn là một danh sách nhỏ, không cần giới hạn/pagination như danh mục khóa học/tài
     * liệu (vốn có thể có hàng chục/trăm bản ghi).
     */
    public function indexData(): array
    {
        return ['teachers' => $this->featuredData(200)];
    }

    /**
     * Trang chủ (App\Services\Public\HomeService, 12.1) dùng lại ĐÚNG truy vấn này, chỉ
     * giới hạn số lượng hiển thị ($limit) — tránh định nghĩa 2 nơi cùng lọc
     * is_featured+approved rồi có thể lệch nhau sau này.
     *
     * @return array<int, array{id:int, name:string, subject:string, achievement:string}>
     */
    public function featuredData(int $limit = 4): array
    {
        return $this->teacherProfiles->query()
            ->where('is_featured', true)
            ->where('approval_status', 'approved')
            ->with('user')
            ->latest('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (TeacherProfile $p) => [
                'id' => $p->id,
                'name' => $p->user->name ?? '',
                'subject' => is_array($p->subjects) && count($p->subjects) > 0 ? $p->subjects[0] : '',
                'achievement' => $p->achievement_note ?? '',
            ])
            ->all();
    }
}

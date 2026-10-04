<?php

namespace App\Services\Public;

use App\Enums\ReviewTargetType;
use App\Models\ClassRoom;
use App\Models\TeacherProfile;
use App\Repositories\Contracts\ClassEnrollmentRepositoryInterface;
use App\Repositories\Contracts\RatingSummaryRepositoryInterface;
use App\Repositories\Contracts\TeacherProfileRepositoryInterface;
use App\Support\SubjectCatalog;
use Illuminate\Support\Collection;

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
    public function __construct(
        private readonly TeacherProfileRepositoryInterface $teacherProfiles,
        private readonly RatingSummaryRepositoryInterface $ratingSummaries,
        private readonly ClassEnrollmentRepositoryInterface $classEnrollments,
    ) {}

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
        $profiles = $this->teacherProfiles->query()
            ->where('is_featured', true)
            ->where('approval_status', 'approved')
            ->with('user')
            /*
             * SỬA 4/10 (khách: "nếu là chuyên gia... luôn được lên đầu danh sách trang giáo viên
             * và chuyên gia public") — chuyên gia trước, rồi mới tới thứ tự cũ (sửa gần nhất
             * lên trước).
             *
             * Xếp Ở TRONG TRUY VẤN chứ không xếp lại sau khi lấy: còn có $limit cắt bớt, xếp
             * sau thì chuyên gia thứ 5 có thể bị cắt mất ngay từ câu truy vấn rồi mới xếp —
             * trang chủ chỉ lấy 4 người là dính ngay.
             *
             * is_expert DESC: MySQL xếp 0 trước 1, mà ta cần 1 (chuyên gia) lên trước.
             */
            ->orderByDesc('is_expert')
            ->latest('updated_at')
            ->limit($limit)
            ->get();

        if ($profiles->isEmpty()) {
            return [];
        }

        // SỬA 11/9 — giao diện mới (education-main/src/components/TeachersPage.jsx) cần thêm:
        // đánh giá trung bình, số lớp đang phụ trách, số học viên và danh sách lớp. Tất cả gom
        // bằng 3 truy vấn cho CẢ trang (không phải mỗi giáo viên vài câu).
        $userIds = $profiles->pluck('user_id')->filter()->values()->all();
        $ratings = $this->ratingsByTeacherId($profiles->pluck('id')->all());
        $classRoomsByTeacher = $this->classRoomsByTeacher($userIds);
        $studentCounts = $this->studentCountsByTeacher($classRoomsByTeacher);

        return $profiles->map(function (TeacherProfile $p) use ($ratings, $classRoomsByTeacher, $studentCounts) {
            $summary = $ratings->get($p->id);
            $classRooms = $classRoomsByTeacher->get($p->user_id, collect());

            $subjects = is_array($p->subjects) ? $p->subjects : [];
            $subjectLabels = array_values(array_filter(array_map(
                fn ($code) => SubjectCatalog::label($code) ?? $code,
                $subjects,
            )));

            /*
             * SỬA 4/10 — SỐ SAO: ưu tiên số ban quản trị công bố (display_rating), không có thì
             * mới dùng điểm trung bình tính từ đánh giá đã kiểm duyệt.
             *
             * 'ratingIsCurated' đi kèm để nơi hiển thị ĐỔI NHÃN theo nguồn số. Trang công khai
             * đang ghi "Đánh giá trung bình đã xác thực" — đổ một con số admin tự gõ vào dưới
             * dòng chữ ấy là nói sai với người đọc. Có cờ này thì chỗ nào cũng biết mình đang
             * bày số nào mà ghi cho đúng.
             */
            $curated = $p->display_rating !== null;
            $realAverage = $summary?->avg_rating !== null ? (float) $summary->avg_rating : null;

            return [
                'id' => $p->id,
                'name' => $p->showcaseName(),
                // Giữ nguyên 2 khoá cũ để trang chủ và mọi chỗ đang dùng không phải sửa theo.
                'subject' => $subjectLabels[0] ?? '',
                'achievement' => $p->achievement_note ?? '',
                // ── các trường bổ sung cho thẻ giáo viên của giao diện mới ──
                'bio' => $p->bio,
                // Huy hiệu "Chuyên gia" chỉ bật khi hồ sơ đã duyệt + đang vinh danh, xem
                // TeacherProfile::isExpert().
                'isExpert' => $p->isExpert(),
                /*
                 * SỬA 4/10 — 2 cột thật thay cho dữ liệu chữa cháy. Ghi chú cũ trong
                 * public/teachers/index.blade.php nói đúng: "hệ thống chưa có 2 cột đó, nên
                 * hiển thị môn dạy thay vì bịa thông tin". Nay có cột thật; chưa nhập thì vẫn
                 * rơi về cách cũ chứ không bày ô trống.
                 */
                // SỬA 4/10 — ảnh đại diện thật (admin tải lên, hoặc ảnh người dùng tự đặt).
                // null thì nơi hiển thị dùng ảnh mặc định như trước, xem TeacherProfile::showcaseAvatarPath().
                'avatarPath' => $p->showcaseAvatarPath(),
                'workplace' => $p->workplace,
                'roleTitle' => $p->role_title,
                'subjects' => $subjectLabels,
                // achievement_note là 1 ô văn bản tự do do Admin nhập; tách theo xuống dòng
                // hoặc dấu ";" để hiện thành danh sách gạch đầu dòng như bản mẫu.
                'achievements' => \App\Services\Admin\FeaturedTeacherService::splitAchievements($p->achievement_note),
                'average' => $curated ? (float) $p->display_rating : $realAverage,
                'ratingIsCurated' => $curated,
                'reviewCount' => (int) ($summary->review_count ?? 0),
                'classCount' => $classRooms->count(),
                'studentCount' => (int) ($studentCounts[$p->user_id] ?? 0),
                'classRooms' => $classRooms->map(fn (ClassRoom $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'code' => $c->code,
                    'students' => (int) $c->students_count,
                    'courseId' => $c->course_id,
                ])->values()->all(),
                'approvedAt' => $p->approved_at,
            ];
        })->all();
    }

    /**
     * Đánh giá của giáo viên — ReviewTargetType::Teacher, target_id là teacher_profiles.id
     * (cùng cách App\Services\Public\CourseService đọc rating của lớp).
     *
     * @param  array<int, int>  $profileIds
     * @return Collection<int, \App\Models\RatingSummary> keyed theo teacher_profile id
     */
    private function ratingsByTeacherId(array $profileIds): Collection
    {
        if ($profileIds === []) {
            return collect();
        }

        return $this->ratingSummaries->query()
            ->where('target_type', ReviewTargetType::Teacher)
            ->whereIn('target_id', $profileIds)
            ->get()
            ->keyBy('target_id');
    }

    /**
     * Các lớp ĐANG HOẠT ĐỘNG mà từng giáo viên phụ trách — 1 truy vấn cho cả trang.
     *
     * @param  array<int, int>  $userIds
     * @return Collection<int, Collection<int, ClassRoom>> keyed theo user_id
     */
    private function classRoomsByTeacher(array $userIds): Collection
    {
        if ($userIds === []) {
            return collect();
        }

        return ClassRoom::query()
            ->where('status', 'active')
            ->whereHas('teachers', fn ($q) => $q->whereIn('users.id', $userIds))
            ->with(['teachers:id'])
            ->withCount('students')
            ->get()
            ->flatMap(fn (ClassRoom $c) => $c->teachers
                ->filter(fn ($t) => in_array($t->id, $userIds, true))
                ->map(fn ($t) => ['userId' => $t->id, 'classRoom' => $c]))
            ->groupBy('userId')
            ->map(fn ($rows) => $rows->pluck('classRoom'));
    }

    /**
     * Số học sinh KHÁC NHAU đang học ở các lớp của từng giáo viên — đếm distinct student_id
     * để học sinh học 2 lớp của cùng thầy không bị tính 2 lần.
     *
     * @param  Collection<int, Collection<int, ClassRoom>>  $classRoomsByTeacher
     * @return array<int, int> keyed theo user_id
     */
    private function studentCountsByTeacher(Collection $classRoomsByTeacher): array
    {
        $counts = [];

        foreach ($classRoomsByTeacher as $userId => $classRooms) {
            $ids = $classRooms->pluck('id')->all();

            $counts[$userId] = $ids === [] ? 0 : $this->classEnrollments->query()
                ->whereIn('class_room_id', $ids)
                ->where('status', 'active')
                ->distinct()
                ->count('student_id');
        }

        return $counts;
    }
}

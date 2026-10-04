<?php

namespace App\Services\Admin;

use App\Models\TeacherProfile;
use App\Repositories\Contracts\TeacherProfileRepositoryInterface;
use App\Support\SubjectCatalog;

/**
 * Gom truy vấn + hành động cho admin.featured-teachers.index — PUB-10 (trang vinh danh, 12.2).
 * Chỉ giáo viên Đã được duyệt mới cho vinh danh (App\Models\TeacherProfile::isFeatured()).
 *
 * SỬA 4/10 (khách: "cho tách riêng đi đừng gộp với trang cuộc thi nha CRUD cho đầy đủ các
 * thông tin đó") — bỏ thanh tab dùng chung với màn Cuộc thi (màn này giờ là một mục menu đứng
 * riêng), và nhận đủ 6 trường khách liệt kê.
 */
class FeaturedTeacherService
{
    public function __construct(
        private TeacherProfileRepositoryInterface $teacherProfiles,
    ) {}

    /** @return array{teachers: array, approvedCount: int} */
    public function indexData(): array
    {
        $teachers = $this->teacherProfiles->approvedWithUser(200)
            ->map(fn (TeacherProfile $p) => $this->row($p))
            ->all();

        return [
            'teachers' => $teachers,
            'approvedCount' => $this->teacherProfiles->countApproved(),
        ];
    }

    /**
     * Một dòng cho bảng admin — ĐỦ 6 trường khách liệt kê.
     *
     * 'accountName' tách khỏi 'name': ô Họ tên trên màn vinh danh là TÊN HIỂN THỊ, để trống thì
     * rơi về tên tài khoản. Bày cả hai để admin biết mình đang ghi đè lên cái gì.
     *
     * @return array<string, mixed>
     */
    private function row(TeacherProfile $p): array
    {
        $subjects = is_array($p->subjects) ? $p->subjects : [];
        $subjectLabels = array_values(array_filter(array_map(
            fn ($code) => SubjectCatalog::label($code) ?? $code,
            $subjects,
        )));

        return [
            'profile_id' => $p->id,
            'accountName' => $p->user->name ?? '',
            'displayName' => $p->display_name ?? '',
            'name' => $p->showcaseName(),
            'workplace' => $p->workplace ?? '',
            'roleTitle' => $p->role_title ?? '',
            'subject' => $subjectLabels[0] ?? '',
            'subjects' => $subjectLabels,
            'featured' => (bool) $p->is_featured,
            'expert' => (bool) $p->is_expert,
            'displayRating' => $p->display_rating,
            'achievement' => $p->achievement_note ?? '',
            'achievements' => self::splitAchievements($p->achievement_note),
        ];
    }

    /**
     * Tách ô "Thành tích tiêu biểu" thành các gạch đầu dòng: mỗi dòng mới hoặc mỗi dấu ";" là
     * một mục.
     *
     * SỬA 4/10 — chuyển lên đây thành hàm tĩnh dùng chung. Trước đây Public\TeacherService có
     * một bản y hệt của riêng nó; hai bản tách khác nhau thì admin xem trước ra một kiểu mà
     * trang công khai hiện một kiểu.
     *
     * @return array<int, string>
     */
    public static function splitAchievements(?string $note): array
    {
        if (blank($note)) {
            return [];
        }

        $parts = preg_split('/[\r\n;]+/u', $note) ?: [];

        return array_values(array_filter(array_map('trim', $parts), fn ($line) => $line !== ''));
    }

    /**
     * THÊM vào danh sách vinh danh, kèm toàn bộ thông tin bày ra trang công khai.
     *
     * @param  array<string, mixed>  $data
     */
    public function feature(TeacherProfile $profile, array $data): TeacherProfile
    {
        $profile->update($this->attributes($data) + ['is_featured' => true]);

        return $profile;
    }

    /**
     * SỬA một người đang vinh danh.
     *
     * Trước 4/10 chỉ có thêm và gỡ: muốn đổi câu thành tích đã công bố thì phải gỡ xuống rồi
     * vinh danh lại, trong khoảng đó người ấy biến mất khỏi trang công khai.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(TeacherProfile $profile, array $data): TeacherProfile
    {
        $profile->update($this->attributes($data));

        return $profile;
    }

    /**
     * XOÁ KHỎI DANH SÁCH VINH DANH — KHÔNG xoá hồ sơ giáo viên, KHÔNG xoá tài khoản.
     *
     * Màn này là trang vinh danh, "xoá" ở đây nghĩa là rút tên khỏi trang công khai. Xoá thật
     * hồ sơ là việc của màn duyệt giáo viên và kéo theo cả lớp, đánh giá, bài giao của người
     * đó — không đặt sau một nút ở trang vinh danh được.
     *
     * Giữ nguyên mọi thông tin đã nhập: vinh danh lại thì không phải gõ lại từ đầu.
     */
    public function unfeature(TeacherProfile $profile): TeacherProfile
    {
        $profile->update(['is_featured' => false]);

        return $profile;
    }

    /**
     * Chuẩn hoá dữ liệu form -> cột.
     *
     * Ô nào để trống thì lưu NULL chứ không lưu chuỗi rỗng — nơi hiển thị dùng filled()/??
     * để rơi về giá trị thay thế, chuỗi rỗng sẽ lọt qua những phép kiểm đó và bày ra một dòng
     * trắng. Đồng thời ô nào cũng XOÁ TRẮNG ĐƯỢC: đã nhập rồi mà không gỡ xuống được là lỗi
     * của bản cũ (nó coi chuỗi rỗng là "không đổi").
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        $text = fn (string $key) => filled(trim((string) ($data[$key] ?? ''))) ? trim((string) $data[$key]) : null;

        return [
            'display_name' => $text('display_name'),
            'workplace' => $text('workplace'),
            'role_title' => $text('role_title'),
            'achievement_note' => $text('achievement_note'),
            'is_expert' => (bool) ($data['is_expert'] ?? false),
            // Để trống = chưa công bố số sao -> trang công khai quay về điểm trung bình tính từ
            // đánh giá đã kiểm duyệt. Xem Public\TeacherService::featuredData().
            'display_rating' => ($data['display_rating'] ?? null) === null || $data['display_rating'] === ''
                ? null
                : round((float) $data['display_rating'], 1),
        ];
    }
}

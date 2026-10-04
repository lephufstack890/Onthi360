<?php

namespace App\Services\Admin;

use App\Enums\TeacherApprovalStatus;
use App\Models\Role;
use App\Models\TeacherProfile;
use App\Models\User;
use App\Repositories\Contracts\TeacherProfileRepositoryInterface;
use App\Support\ImageOptimizer;
use App\Support\SubjectCatalog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
    /** Nơi cất ảnh đại diện giáo viên — cùng đĩa 'public' với ảnh bìa tài liệu, câu chuyện. */
    private const AVATAR_DISK = 'public';

    private const AVATAR_DIR = 'teacher-avatars';

    public function __construct(
        private TeacherProfileRepositoryInterface $teacherProfiles,
        private UserService $users,
    ) {}

    /** @return array{teachers: array, approvedCount: int} */
    public function indexData(): array
    {
        // SỬA 4/10 — showcaseList() thay cho approvedWithUser(): màn này phải thấy cả hồ sơ
        // trưng bày không gắn tài khoản, còn approvedWithUser() nay cố ý loại chúng ra.
        $teachers = $this->teacherProfiles->showcaseList(200)
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
            // Ảnh riêng của trang vinh danh (nếu có), rơi về ảnh người dùng tự đặt.
            'avatarPath' => $p->showcaseAvatarPath(),
            'ownAvatar' => $p->avatar_path,
            // 2 cột này nằm ở bảng users, form sửa ghi thẳng xuống đó — xem syncAccountFields().
            'province' => $p->user?->province,
            'region' => $p->user?->region,
            // Hồ sơ trưng bày (user_id rỗng) không có tài khoản nào đứng sau — nơi hiển thị dựa
            // vào 'hasAccount' để biết có được xoá hẳn hay không.
            'hasAccount' => $p->user_id !== null,
            'accountName' => $p->user?->name ?? '',
            'displayName' => $p->display_name ?? '',
            'name' => $p->showcaseName(),
            'workplace' => $p->workplace ?? '',
            'roleTitle' => $p->role_title ?? '',
            'subject' => $subjectLabels[0] ?? '',
            'subjects' => $subjectLabels,
            'featured' => (bool) $p->is_featured,
            'expert' => (bool) $p->is_expert,
            'sortOrder' => (int) $p->sort_order,
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
    public function feature(TeacherProfile $profile, array $data, ?UploadedFile $avatar = null): TeacherProfile
    {
        $profile->update($this->attributes($data) + $this->avatarAttributes($profile, $avatar) + [
            'is_featured' => true,
            // Để trống ô thứ tự thì xuống cuối, không chen lên đầu danh sách đã sắp.
            'sort_order' => $this->nextSortOrder(),
        ]);
        $this->syncAccountFields($profile, $data);

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
    public function update(TeacherProfile $profile, array $data, ?UploadedFile $avatar = null): TeacherProfile
    {
        $profile->update($this->attributes($data) + $this->avatarAttributes($profile, $avatar));
        $this->syncAccountFields($profile, $data);

        return $profile;
    }

    /**
     * SỬA 4/10 (khách: "chỗ sửa chưa có sửa được tỉnh/thành và khu vực bổ sung giúp tôi luôn
     * nha") — Tỉnh/thành và Khu vực nằm ở bảng USERS chứ không phải teacher_profiles, nên phải
     * ghi riêng một bước.
     *
     * CHỈ ghi khi form thực sự có gửi 2 ô đó lên (array_key_exists, không phải ?? null). Form
     * nào không có 2 ô này — ví dụ form "Thêm vào danh sách" của hồ sơ không gắn tài khoản —
     * mà vẫn chạy qua đây thì sẽ xoá trắng tỉnh/thành của người ta một cách lặng lẽ.
     *
     * @param  array<string, mixed>  $data
     */
    private function syncAccountFields(TeacherProfile $profile, array $data): void
    {
        if ($profile->user_id === null) {
            return;
        }

        $attributes = [];

        foreach (['province', 'region'] as $column) {
            if (array_key_exists($column, $data)) {
                $attributes[$column] = filled($data[$column]) ? $data[$column] : null;
            }
        }

        if ($attributes !== []) {
            $profile->user()->first()?->update($attributes);
        }
    }

    /**
     * Ảnh đại diện: KHÔNG tải ảnh mới thì KHÔNG đụng tới cột.
     *
     * Trả về mảng RỖNG khi không có tệp — khác hẳn với các ô chữ (để trống là xoá). Ô tệp của
     * trình duyệt không gửi gì lên khi người ta không chọn lại, nên coi "không gửi" là "xoá ảnh"
     * thì mỗi lần sửa một chữ trong thành tích là mất luôn ảnh đã tải.
     *
     * Muốn gỡ ảnh thì tick ô "Xoá ảnh hiện tại" — xem $data['remove_avatar'] ở attributes().
     *
     * @return array<string, mixed>
     */
    private function avatarAttributes(TeacherProfile $profile, ?UploadedFile $avatar): array
    {
        if ($avatar === null) {
            return [];
        }

        // Thay ảnh thì xoá ảnh cũ, đừng để rác tồn trong storage.
        $this->forgetAvatar($profile->avatar_path);

        return ['avatar_path' => ImageOptimizer::store(
            $avatar, self::AVATAR_DIR, self::AVATAR_DISK, ImageOptimizer::MAX_WIDTH_AVATAR
        )];
    }

    /** Số thứ tự cho hồ sơ mới — lớn nhất đang dùng + 1, để nó rơi xuống cuối danh sách. */
    private function nextSortOrder(): int
    {
        return $this->teacherProfiles->maxSortOrder() + 1;
    }

    /** Xoá tệp ảnh cũ. Bỏ qua đường dẫn rỗng và đường dẫn http (ảnh ngoài, không do ta giữ). */
    private function forgetAvatar(?string $path): void
    {
        if (filled($path) && ! str_starts_with($path, 'http')) {
            Storage::disk(self::AVATAR_DISK)->delete($path);
        }
    }

    /**
     * SỬA 4/10 (khách: "thêm cả thông tin email sđt mật khẩu các thứ nữa nha giống thêm người
     * dùng luôn mà nó khác là có các thông tin kia nha. Vai trò thêm ở đây mặc định là giáo
     * viên" + "tỉnh thành, khu vực nữa nhé") — TẠO TÀI KHOẢN GIÁO VIÊN THẬT rồi vinh danh luôn.
     *
     * DÙNG LẠI UserService::store() chứ không tự tạo User ở đây. Hàm đó đã lo đủ: băm mật khẩu,
     * gán vai trò, dựng TeacherProfile, và GHI NHẬT KÝ admin đã tạo tài khoản nào (16 mục 4).
     * Viết lại một bản thứ hai ở đây thì sớm muộn hai bản lệch nhau — mà bản ở đây sẽ là bản
     * không ghi nhật ký.
     *
     * Vai trò CỐ ĐỊNH là Giáo viên, không cho chọn: đây là màn vinh danh giáo viên, tạo ra một
     * tài khoản admin từ đây là chuyện không ai ngờ tới.
     *
     * UserService::store() dựng hồ sơ ở trạng thái "Chờ duyệt" (cố ý, để không có lối tắt bỏ
     * qua bước duyệt). Ở đây admin vừa tự tay khai hồ sơ nên duyệt luôn, có ghi lại ai duyệt và
     * duyệt lúc nào — y như khi bấm Duyệt ở hàng đợi.
     *
     * Bọc trong một giao dịch: lỡ hỏng giữa chừng thì không để lại một tài khoản không có hồ sơ.
     *
     * @param  array<string, mixed>  $data
     */
    public function createWithAccount(User $admin, array $data, ?UploadedFile $avatar = null): TeacherProfile
    {
        return DB::transaction(function () use ($admin, $data, $avatar) {
            $user = $this->users->store($admin, [
                'name' => $data['display_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'province' => $data['province'] ?? null,
                'region' => $data['region'] ?? null,
                'password' => $data['password'],
                'roles' => [Role::TEACHER],
            ]);

            $profile = TeacherProfile::where('user_id', $user->id)->firstOrFail();

            $profile->update($this->attributes($data) + $this->avatarAttributes($profile, $avatar) + [
                'sort_order' => $this->nextSortOrder(),
                'approval_status' => TeacherApprovalStatus::Approved,
                'approved_by' => $admin->id,
                'approved_at' => now(),
                'is_featured' => true,
            ]);

            return $profile;
        });
    }

    /**
     * XOÁ HẲN một hồ sơ trưng bày.
     *
     * CHỈ áp dụng cho hồ sơ KHÔNG gắn tài khoản. Hồ sơ của một giáo viên thật kéo theo lớp,
     * đánh giá, bài giao của người đó — không được xoá từ màn vinh danh, và cũng không cần:
     * với họ thì "xoá" nghĩa là rút tên khỏi trang (unfeature) ở dưới.
     *
     * Trả về false khi hồ sơ có tài khoản — nơi gọi lấy đó làm căn cứ từ chối, KHÔNG tin vào
     * việc giao diện đã ẩn nút đi.
     */
    public function deleteStandalone(TeacherProfile $profile): bool
    {
        if ($profile->user_id !== null) {
            return false;
        }

        $profile->delete();

        return true;
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

        $attributes = [
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

        /*
         * SỬA 4/10 — THỨ TỰ HIỂN THỊ. Chỉ ghi khi form thực sự gửi ô này lên (array_key_exists):
         * form nào không có ô đó mà vẫn ghi thì sẽ đẩy hồ sơ về 0 một cách lặng lẽ, tức là nhảy
         * lên đầu danh sách — hỏng đúng thứ khách vừa nhờ làm.
         */
        if (array_key_exists('sort_order', $data) && $data['sort_order'] !== null && $data['sort_order'] !== '') {
            $attributes['sort_order'] = max(0, (int) $data['sort_order']);
        }

        // Ô tick "Xoá ảnh hiện tại" — cách duy nhất để gỡ ảnh xuống, vì ô tệp không gửi gì lên
        // khi người ta không chọn lại (xem avatarAttributes()).
        if (! empty($data['remove_avatar'])) {
            $attributes['avatar_path'] = null;
        }

        return $attributes;
    }
}

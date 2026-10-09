<?php

namespace App\Services\Account;

use App\Models\User;
use App\Support\ImageOptimizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * SỬA 9/10 (khách: "copy UI màn thông tin cá nhân qua màn hồ sơ, nhớ cho update avatar") —
 * ảnh đại diện của CHÍNH người dùng, dùng chung cho 4 khu (học sinh/giáo viên/phụ huynh/admin).
 *
 * Lưu vào users.avatar_path (cột đã có từ migration add_profile_fields_to_users_table, chưa chỗ
 * nào ghi vào) trên đĩa 'public', thư mục user-avatars/. Trang vinh danh giáo viên đã sẵn đọc
 * users.avatar_path làm ảnh dự phòng (TeacherProfile::showcaseAvatarPath()) nên ảnh này tự hiện ở đó
 * khi admin chưa tải ảnh riêng.
 */
class AvatarService
{
    public const DISK = 'public';

    public const DIR = 'user-avatars';

    /** Luật kiểm tra dùng chung cho form hồ sơ ở cả 4 khu. */
    public static function rules(): array
    {
        return [
            'avatar' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_avatar' => ['nullable', 'boolean'],
        ];
    }

    /** Đường dẫn lưu -> URL hiển thị. null nếu chưa có ảnh. */
    public static function url(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        // asset() chứ không phải Storage::url(): asset() bám theo địa chỉ đang truy cập (localhost:8000, tên miền VPS...),
        // còn Storage::url() ghép theo APP_URL nên lệch cổng/tên miền là ảnh vỡ. Cùng cách Course/Assessment đang dùng.
        return str_starts_with($path, 'http') ? $path : asset('storage/'.ltrim($path, '/'));
    }

    /**
     * Thay ảnh (có tệp mới) hoặc gỡ ảnh (remove = true). Không có gì để làm thì không đụng vào.
     * Chỉ xoá tệp cũ nếu nó nằm trong user-avatars/ — ảnh do nơi khác đặt thì để yên.
     */
    public function apply(User $user, ?UploadedFile $file, bool $remove): void
    {
        $old = $user->avatar_path;

        if ($file !== null) {
            $path = ImageOptimizer::store($file, self::DIR, self::DISK, ImageOptimizer::MAX_WIDTH_AVATAR);
            $user->forceFill(['avatar_path' => $path])->save();
            $this->forget($old);

            return;
        }

        if ($remove && filled($old)) {
            $user->forceFill(['avatar_path' => null])->save();
            $this->forget($old);
        }
    }

    private function forget(?string $path): void
    {
        if (filled($path) && str_starts_with($path, self::DIR.'/')) {
            Storage::disk(self::DISK)->delete($path);
        }
    }
}

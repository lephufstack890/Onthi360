<?php

namespace App\Services\Admin;

use App\Enums\ContentStatus;
use App\Models\Testimonial;
use App\Models\User;
use App\Repositories\Contracts\TestimonialRepositoryInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Quản trị khối "Câu chuyện đồng hành" của trang chủ ([HOME-10]).
 *
 * LƯU Ý VỀ TÍNH TRUNG THỰC: xem ghi chú dài ở migration create_testimonials_table. Tóm tắt:
 * chỉ câu chuyện đã đánh dấu "đã xác minh" mới được gắn schema.org/Review gửi Google.
 */
class TestimonialService
{
    private const DISK = 'public';
    private const DIR = 'testimonials';

    /** Số câu chuyện trang chủ hiển thị — bố cục của bản mẫu là lưới 3 thẻ. */
    public const HOME_LIMIT = 3;

    public function __construct(private TestimonialRepositoryInterface $testimonials) {}

    public function indexData(): array
    {
        $rows = $this->testimonials->allForAdmin();

        return [
            'testimonials' => $rows,
            'publishedCount' => $rows->where('status', ContentStatus::Published)->count(),
            'draftCount' => $rows->where('status', ContentStatus::Draft)->count(),
            'sampleCount' => $rows->where('is_sample', true)->count(),
            // SỬA 13/9 — câu MẪU mà đang hiển thị công khai là trường hợp cần cảnh báo gấp nhất:
            // lời chứng thực không có thật đang nằm trên trang chủ cho phụ huynh đọc.
            'publishedSampleCount' => $rows->where('is_sample', true)
                ->where('status', ContentStatus::Published)
                ->count(),
            'homeLimit' => self::HOME_LIMIT,
        ];
    }

    public function createFormData(): array
    {
        return ['testimonial' => null, 'statuses' => $this->statuses()];
    }

    public function editFormData(int $id): array
    {
        return [
            'testimonial' => $this->testimonials->findOrFail($id),
            'statuses' => $this->statuses(),
        ];
    }

    public function store(?User $actor, array $data, ?UploadedFile $avatar = null, ?UploadedFile $banner = null): Testimonial
    {
        $attributes = $this->attributesFrom($data);
        $attributes['created_by'] = $actor?->id;
        $attributes['sort_order'] = $data['sort_order'] ?? ($this->testimonials->maxSortOrder() + 1);
        $attributes['is_sample'] = false;

        if ($avatar !== null) {
            $attributes['avatar_path'] = $avatar->store(self::DIR, self::DISK);
        }

        if ($banner !== null) {
            $attributes['banner_path'] = $banner->store(self::DIR, self::DISK);
        }

        return Testimonial::create($attributes);
    }

    public function update(Testimonial $testimonial, array $data, ?UploadedFile $avatar = null, ?UploadedFile $banner = null): Testimonial
    {
        $attributes = $this->attributesFrom($data);

        if (array_key_exists('sort_order', $data) && $data['sort_order'] !== null) {
            $attributes['sort_order'] = (int) $data['sort_order'];
        }

        // Tải ảnh mới thì xoá ảnh cũ để không tồn rác trong storage.
        if ($avatar !== null) {
            $this->forget($testimonial->avatar_path);
            $attributes['avatar_path'] = $avatar->store(self::DIR, self::DISK);
        }

        if ($banner !== null) {
            $this->forget($testimonial->banner_path);
            $attributes['banner_path'] = $banner->store(self::DIR, self::DISK);
        }

        // Đã sửa nội dung thì không còn là dòng mẫu của seeder nữa.
        $attributes['is_sample'] = false;

        $testimonial->update($attributes);

        return $testimonial;
    }

    /** Bật/tắt hiển thị nhanh ngay từ danh sách. */
    public function togglePublish(Testimonial $testimonial): Testimonial
    {
        if ($testimonial->isPublished()) {
            $testimonial->update(['status' => ContentStatus::Draft->value]);

            return $testimonial;
        }

        $testimonial->update([
            'status' => ContentStatus::Published->value,
            'published_at' => $testimonial->published_at ?? now(),
        ]);

        return $testimonial;
    }

    public function destroy(Testimonial $testimonial, string $reason): void
    {
        Testimonial::$auditReason = $reason;

        $this->forget($testimonial->avatar_path);
        $this->forget($testimonial->banner_path);
        $testimonial->delete();

        Testimonial::$auditReason = null;
    }

    /**
     * @return array<string, string>
     */
    private function statuses(): array
    {
        return [
            ContentStatus::Draft->value => 'Bản nháp (chưa hiện ở trang chủ)',
            ContentStatus::Published->value => 'Đang hiển thị ở trang chủ',
            ContentStatus::Archived->value => 'Lưu trữ',
        ];
    }

    private function attributesFrom(array $data): array
    {
        $status = $data['status'] ?? ContentStatus::Draft->value;

        return [
            'quote' => trim($data['quote']),
            'author_name' => trim($data['author_name']),
            'author_role' => $this->nullIfBlank($data['author_role'] ?? null),
            'author_org' => $this->nullIfBlank($data['author_org'] ?? null),
            'rating' => isset($data['rating']) && $data['rating'] !== '' ? (int) $data['rating'] : null,
            'status' => $status,
            'published_at' => $status === ContentStatus::Published->value ? ($data['published_at'] ?? now()) : null,
            // Ô "đã xác minh" là lời cam kết của người đăng rằng câu chuyện có thật và được
            // phép đăng — chỉ khi đó mới gắn schema.org/Review.
            'verified_at' => ! empty($data['verified']) ? now() : null,
        ];
    }

    private function nullIfBlank(?string $value): ?string
    {
        $value = $value !== null ? trim($value) : null;

        return $value === '' ? null : $value;
    }

    private function forget(?string $path): void
    {
        if ($path !== null && $path !== '' && ! str_starts_with($path, 'http')) {
            Storage::disk(self::DISK)->delete($path);
        }
    }
}

<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\ContentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Một "Câu chuyện đồng hành" hiển thị ở khối [HOME-10] trang chủ.
 * Xem migration create_testimonials_table để biết ý nghĩa từng cột, đặc biệt là verified_at.
 */
class Testimonial extends Model
{
    use Auditable;

    /** Đọc bởi App\Concerns\Auditable — lý do khi admin ẩn/xoá câu chuyện. */
    public static ?string $auditReason = null;

    protected $fillable = [
        'quote', 'author_name', 'author_role', 'author_org', 'avatar_path', 'banner_path',
        'rating', 'status', 'sort_order', 'published_at', 'verified_at', 'is_sample', 'created_by',
    ];

    protected $casts = [
        'status' => ContentStatus::class,
        'published_at' => 'datetime',
        'verified_at' => 'datetime',
        'is_sample' => 'boolean',
        'rating' => 'integer',
        'sort_order' => 'integer',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPublished(): bool
    {
        return $this->status === ContentStatus::Published;
    }

    /** Đã xác minh là câu chuyện có thật -> mới được gắn schema.org/Review. */
    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    /**
     * Đường dẫn ảnh để in ra thẻ <img>. Chưa tải ảnh riêng thì dùng ảnh mặc định của bộ giao
     * diện, xoay vòng theo id để mỗi câu chuyện luôn ra cùng một ảnh (không nhảy mỗi lần tải).
     */
    public function avatarUrl(): string
    {
        return $this->publicUrl($this->avatar_path)
            ?? asset('assets/testi-av-'.(($this->id % 3) + 1).'.png');
    }

    public function bannerUrl(): string
    {
        return $this->publicUrl($this->banner_path)
            ?? asset('assets/testi-banner-'.(($this->id % 3) + 1).'.png');
    }

    private function publicUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        // Ảnh cũ có thể được lưu sẵn dạng URL tuyệt đối; đường dẫn tương đối thì qua disk public.
        return str_starts_with($path, 'http') ? $path : Storage::disk('public')->url($path);
    }
}

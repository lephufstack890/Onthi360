<?php

namespace App\Models;

use App\Enums\CompetitionOrganizerType;
use App\Enums\CompetitionStatus;
use App\Enums\CompetitionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class Competition extends Model
{
    protected $fillable = [
        'title', 'slug', 'type', 'organizer_type', 'organizer_name', 'assessment_id', 'rules',
        'starts_at', 'ends_at', 'publish_result_at', 'status', 'ranking_rule',
        // SỬA 24/10 — phòng thi mới (migration 001260)
        'description', 'edition_label', 'image_path', 'learning_path_id', 'entry_password',
        'course_id', // SỬA 24/10 — "Lộ trình học" = khoá học (migration 001270)
    ];

    protected $casts = [
        'type' => CompetitionType::class,
        'organizer_type' => CompetitionOrganizerType::class,
        'status' => CompetitionStatus::class,
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'publish_result_at' => 'datetime',
        'ranking_rule' => 'array',
        // SỬA 24/10 — mật khẩu khảo sát lưu MÃ HOÁ (APP_KEY) để admin xem lại & gửi học sinh.
        'entry_password' => 'encrypted',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function leaderboardEntries(): HasMany
    {
        return $this->hasMany(LeaderboardEntry::class);
    }

    /**
     * Các kỳ thi (vòng) bên trong cuộc thi này — xem App\Models\CompetitionExam. Mỗi
     * Competition có thể có 0 (chưa thêm kỳ thi nào), 1 (backfill từ assessment_id cũ) hoặc
     * nhiều kỳ thi.
     */
    public function examSittings(): HasMany
    {
        return $this->hasMany(CompetitionExam::class)->orderBy('order');
    }

    /**
     * Giáo viên cố vấn/đồng hành — bắt buộc có ít nhất 1 người khi organizer_type=external
     * (note họp 13/8, mục 1: "cuộc thi ngoài đơn vị tổ chức thì cần có chuyên gia cố vấn
     * giáo viên đồng hành để tăng uy tín"), xem App\Services\Admin\CompetitionService.
     */
    /**
     * SỬA 19/9 — đơn đăng ký tham gia của học sinh (mọi trạng thái). Lọc theo trạng thái ở nơi
     * dùng, xem App\Models\CompetitionRegistration.
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(CompetitionRegistration::class);
    }

    public function advisors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'competition_advisors', 'competition_id', 'teacher_id')->withTimestamps();
    }

    /**
     * SỬA 24/10 (khách: "lộ trình học = khoá học") — KHOÁ HỌC mà cuộc thi / khảo sát thuộc về. Giao diện vẫn gọi là
     * "Lộ trình học" (thẻ cuộc thi, bộ lọc "Lộ trình", link trong phòng thi).
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** Đã chạy migration 001270 (cột course_id) chưa. */
    public static function supportsCourse(): bool
    {
        static $has = null;

        return $has ??= Schema::hasColumn('competitions', 'course_id');
    }

    /** SỬA 24/10 — (không dùng nữa, thay bằng course()) lộ trình học liên quan. */
    public function learningPath(): BelongsTo
    {
        return $this->belongsTo(LearningPath::class);
    }

    /** SỬA 24/10 — đã chạy migration 001260 chưa (chưa thì các trường mới coi như trống, trang không lỗi). */
    public static function supportsHubFields(): bool
    {
        static $has = null;

        return $has ??= Schema::hasColumn('competitions', 'entry_password');
    }

    public function isSurvey(): bool
    {
        return $this->type === CompetitionType::Survey;
    }

    /** Ảnh bìa: ảnh admin tải lên, không có thì 1 trong 3 ảnh mặc định. */
    public function imageUrl(): string
    {
        if (self::supportsHubFields() && $this->image_path) {
            // SỬA 24/10 — ảnh chọn từ catalog lưu dạng "catalog:<id>" (App\Support\CompetitionCover).
            if (\App\Support\CompetitionCover::isCatalog($this->image_path)) {
                $url = \App\Support\CompetitionCover::url($this->image_path);
                if ($url !== null) {
                    return $url;
                }
            } else {
                return asset('storage/'.$this->image_path);
            }
        }

        return asset('assets/contest-img-'.(($this->id % 3) + 1).'.jpg');
    }

    /** Mật khẩu khảo sát (đã giải mã); null nếu chưa đặt / chưa migrate / không giải mã được (đổi APP_KEY). */
    public function entryPassword(): ?string
    {
        if (! self::supportsHubFields()) {
            return null;
        }

        try {
            $value = $this->entry_password;
        } catch (\Throwable) {
            return null;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function hasEntryPassword(): bool
    {
        return $this->entryPassword() !== null;
    }

    public function checkEntryPassword(string $input): bool
    {
        $stored = $this->entryPassword();

        return $stored === null || hash_equals($stored, trim($input));
    }

    public function isExternallyOrganized(): bool
    {
        return $this->organizer_type === CompetitionOrganizerType::External;
    }

    /**
     * "Chờ công bố" không lộ rank tạm thời nếu quy chế cấm (11.2) — dùng computedStatus()
     * (tự tính theo giờ hiện tại) thay vì cột status lưu sẵn, để không bị "trễ" khi qua mốc
     * giờ mà chưa có ai vào sửa cuộc thi để cột status được ghi lại (xem computedStatus()).
     */
    public function ranksArePublic(): bool
    {
        return $this->computedStatus() === CompetitionStatus::Published;
    }

    /**
     * Trạng thái vòng đời TÍNH THEO GIỜ HIỆN TẠI (11.1: Sắp diễn ra→Đang diễn ra→Chờ công
     * bố→Đã công bố→Lưu trữ) thay vì để Admin tự chọn tay lúc thêm/sửa (dễ chọn sai/quên đổi
     * đúng lúc) — CHỈ "Lưu trữ" là hành động thủ công có chủ đích (nút riêng
     * admin.competitions.archive, xem CompetitionService::archive()), còn lại 4 trạng thái
     * kia đều suy ra thẳng từ starts_at/ends_at/publish_result_at. Dùng ở CẢ 2 nơi: lúc lưu
     * (CompetitionService::store()/update() gọi computeStatusFor() để ghi lại cột status cho
     * các truy vấn lọc theo cột, vd trang chủ "sắp tới") LẪN lúc hiển thị (gọi computedStatus()
     * ở đây để không bao giờ hiện sai/trễ dù cột status trong DB có cũ do lâu chưa ai sửa).
     */
    public function computedStatus(): CompetitionStatus
    {
        return self::computeStatusFor(
            $this->starts_at,
            $this->ends_at,
            $this->publish_result_at,
            $this->status === CompetitionStatus::Archived,
        );
    }

    /**
     * Phần lõi tính trạng thái — tách thành static để CompetitionService::store()/update() gọi
     * được TRƯỚC KHI bản ghi tồn tại (từ dữ liệu form vừa nhập, chưa có Model), dùng chung 1
     * chỗ duy nhất với computedStatus() ở trên (tránh 2 nơi tính khác nhau bị lệch).
     */
    public static function computeStatusFor(
        Carbon|string|null $startsAt,
        Carbon|string|null $endsAt,
        Carbon|string|null $publishResultAt,
        bool $archived = false,
    ): CompetitionStatus {
        if ($archived) {
            return CompetitionStatus::Archived;
        }

        $startsAt = $startsAt !== null ? Carbon::parse($startsAt) : null;
        $endsAt = $endsAt !== null ? Carbon::parse($endsAt) : null;
        $publishResultAt = $publishResultAt !== null ? Carbon::parse($publishResultAt) : null;

        $now = now();

        // Chưa đặt lịch (starts_at null) hoặc chưa tới giờ bắt đầu → Sắp diễn ra.
        if ($startsAt === null || $now->lt($startsAt)) {
            return CompetitionStatus::Upcoming;
        }

        // Đã tới/qua giờ bắt đầu — chưa đặt giờ kết thúc (mở vô thời hạn) HOẶC chưa qua giờ
        // kết thúc → Đang diễn ra.
        if ($endsAt === null || $now->lte($endsAt)) {
            return CompetitionStatus::Ongoing;
        }

        // Đã qua giờ kết thúc — còn mốc công bố kết quả ở tương lai thì Chờ công bố, không thì
        // coi như đã công bố ngay khi hết giờ thi (không đặt publish_result_at = không cần chờ).
        if ($publishResultAt !== null && $now->lt($publishResultAt)) {
            return CompetitionStatus::PendingPublish;
        }

        return CompetitionStatus::Published;
    }
}

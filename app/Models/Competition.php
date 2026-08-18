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

class Competition extends Model
{
    protected $fillable = [
        'title', 'slug', 'type', 'organizer_type', 'organizer_name', 'assessment_id', 'rules',
        'starts_at', 'ends_at', 'publish_result_at', 'status', 'ranking_rule',
    ];

    protected $casts = [
        'type' => CompetitionType::class,
        'organizer_type' => CompetitionOrganizerType::class,
        'status' => CompetitionStatus::class,
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'publish_result_at' => 'datetime',
        'ranking_rule' => 'array',
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
    public function advisors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'competition_advisors', 'competition_id', 'teacher_id')->withTimestamps();
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

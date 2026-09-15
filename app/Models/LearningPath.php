<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\ContentStatus;
use App\Enums\PathLanguage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Lộ trình học — cấp trên của Khoá học.
 *
 * Lộ trình → nhiều Khoá học (mỗi khoá là một "bậc") → nhiều Lớp học.
 *
 * Các con số tổng (tổng buổi, tổng tuần, tổng giờ) đều TÍNH từ các bậc chứ không lưu cột
 * riêng — xem ghi chú ở migration create_learning_paths_table. Muốn đổi cách tính thì sửa
 * đúng ở đây, mọi màn dùng chung đều theo.
 */
class LearningPath extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'title', 'slug', 'brand', 'eyebrow', 'subtitle', 'description',
        'grade_from', 'grade_to', 'language', 'goal_label',
        'sessions_per_week', 'hours_per_session', 'outcomes',
        'cover_image_path', 'share_image_path',
        'status', 'sort_order', 'created_by',
    ];

    protected $casts = [
        'status' => ContentStatus::class,
        'language' => PathLanguage::class,
        'outcomes' => 'array',
        'grade_from' => 'integer',
        'grade_to' => 'integer',
        'sessions_per_week' => 'integer',
        'hours_per_session' => 'float',
        'sort_order' => 'integer',
    ];

    /**
     * Đọc bởi App\Concerns\Auditable — đặt trước khi delete() để ghi lý do xoá mềm, cùng quy
     * ước với Course và các model khác trong dự án.
     */
    public static ?string $auditReason = null;

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Các bậc của lộ trình, LUÔN theo đúng thứ tự đã xếp.
     *
     * Sắp xếp đặt ngay trong quan hệ để mọi nơi gọi ->courses đều ra đúng thứ tự, không phải
     * nhớ orderBy ở từng chỗ — quên một chỗ là thang bậc hiện lộn xộn.
     */
    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'learning_path_course')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderBy('learning_path_course.sort_order');
    }

    public function isPublished(): bool
    {
        return $this->status === ContentStatus::Published;
    }

    /** "Khối 6–8", hoặc "Lớp 9" khi chỉ phục vụ một lớp. */
    public function gradeLabel(): string
    {
        return $this->grade_from === $this->grade_to
            ? 'Lớp '.$this->grade_from
            : 'Khối '.$this->grade_from.'–'.$this->grade_to;
    }

    /** Lộ trình này có hợp với học sinh lớp $grade không. */
    public function coversGrade(int $grade): bool
    {
        return $grade >= $this->grade_from && $grade <= $this->grade_to;
    }

    public function languageLabel(): string
    {
        return $this->language?->label() ?? '—';
    }

    /**
     * Tổng số buổi theo chương trình = cộng số buổi của các bậc.
     *
     * Bậc chưa điền số buổi thì tính 0 — và màn quản trị sẽ cảnh báo, xem
     * App\Support\LearningPathReadiness.
     */
    public function totalSessions(): int
    {
        return (int) $this->courses->sum(fn (Course $course) => (int) $course->session_count);
    }

    /** Tổng giờ học = tổng buổi × số giờ mỗi buổi. */
    public function totalHours(): float
    {
        return round($this->totalSessions() * (float) $this->hours_per_session, 1);
    }

    /**
     * Số tuần học ước tính = tổng buổi ÷ số buổi mỗi tuần, làm tròn LÊN.
     *
     * Đây là con số phụ huynh quan tâm nhất ("học bao lâu thì xong") nhưng không được ghi ở
     * đâu trong thiết kế của khách — phải tự cộng mới ra. Vì vậy hiện nó tự động ở cả màn
     * quản trị lẫn trang công khai.
     */
    public function totalWeeks(): int
    {
        $perWeek = max(1, (int) $this->sessions_per_week);

        return (int) ceil($this->totalSessions() / $perWeek);
    }

    /** "2 buổi/tuần · 2 giờ/buổi" — dòng nhịp học in ở chân ảnh lộ trình. */
    public function paceLabel(): string
    {
        $hours = rtrim(rtrim(number_format((float) $this->hours_per_session, 1, ',', ''), '0'), ',');

        return $this->sessions_per_week.' buổi/tuần · '.$hours.' giờ/buổi';
    }

    /**
     * Ảnh thu nhỏ của lộ trình (thumbnail) — dùng ở danh sách quản trị và thẻ lộ trình ngoài
     * trang công khai. Chưa tải ảnh thì trả null để nơi gọi tự quyết định hiện gì thay thế.
     */
    public function coverUrl(): ?string
    {
        return $this->cover_image_path ? asset('storage/'.$this->cover_image_path) : null;
    }

    /**
     * Ảnh dùng khi chia sẻ lên mạng xã hội (ảnh thang bậc vẽ sẵn của khách).
     *
     * KHÔNG phải nội dung chính của trang: trang công khai dựng lại thang bậc bằng HTML theo
     * dữ liệu để khách sửa số buổi là thang tự đổi, khỏi nhờ thiết kế vẽ lại ảnh.
     */
    public function shareImageUrl(): ?string
    {
        return $this->share_image_path ? asset('storage/'.$this->share_image_path) : null;
    }

    /** @return list<string> */
    public function outcomeList(): array
    {
        return array_values(array_filter((array) ($this->outcomes ?? []), fn ($line) => trim((string) $line) !== ''));
    }
}

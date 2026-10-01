<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Concerns\Auditable;
use App\Enums\ContentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;

class Course extends Model
{
    use HasFactory, Auditable, SoftDeletes;

    protected $fillable = [
        'title', 'slug', 'description', 'cover_image_path', 'subject', 'grade', 'status', 'created_by',
        // SỬA 1/10 — 'intro' = GIỚI THIỆU đầy đủ (mục "Giới thiệu khoá học" giữa trang công
        // khai), tách khỏi 'description' = MÔ TẢ NGẮN (dòng tóm tắt dưới tên khoá). Xem
        // migration add_intro_to_courses_table.
        'intro',
        // SỬA 15/9 — bốn trường "bậc" khi khoá học nằm trong một lộ trình, xem migration
        // add_level_fields_to_courses_table.
        'level_code', 'level_subtitle', 'outcome', 'session_count',
        // SỬA 30/9 — cận TRÊN của số buổi khi khoá ghi theo khoảng ("33-50 buổi"), xem
        // migration add_session_range_and_multi_grade_to_courses_table.
        'session_count_max',
        // C1 — sản phẩm loại 'course' dùng để bán khoá này, xem migration
        // add_product_id_to_courses_table.
        'product_id',
    ];

    protected $casts = [
        'status' => ContentStatus::class,
        'session_count' => 'integer',
        'session_count_max' => 'integer',
    ];

    /**
     * SỬA 30/9 — số buổi in ra màn hình: "33-50 buổi" khi khoá ghi theo khoảng, "40 buổi" khi
     * cố định, chuỗi rỗng khi chưa nhập (nơi gọi tự giấu ô đó đi thay vì in "0 buổi").
     */
    public function sessionCountLabel(string $suffix = ' buổi'): string
    {
        $min = (int) ($this->session_count ?? 0);
        $max = (int) ($this->session_count_max ?? 0);

        if ($min <= 0) {
            return '';
        }

        return ($max > $min ? $min.'-'.$max : (string) $min).$suffix;
    }

    /**
     * SỬA 30/9 — cột grade giờ chứa được NHIỀU khối ngăn bằng dấu phẩy ("Lớp 6, Lớp 7"). Hàm
     * này tách ra mảng để form tick sẵn ô đã chọn và để trang công khai dựng dải lọc theo
     * TỪNG khối thay vì một viên ghi cả cụm.
     *
     * @return array<int, string>
     */
    public function gradeList(): array
    {
        return self::splitGrades($this->grade);
    }

    /** @return array<int, string> */
    public static function splitGrades(?string $raw): array
    {
        return collect(explode(',', (string) $raw))
            ->map(fn ($g) => trim($g))
            ->filter(fn ($g) => $g !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Đọc bởi App\Concerns\Auditable — set trước khi delete() để ghi lý do xóa mềm
     * (10.4: "xóa mềm phải có lý do, người thao tác, thời gian và audit log"), xem
     * App\Services\Admin\CourseService::destroy().
     */
    public static ?string $auditReason = null;

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function classRooms(): HasMany
    {
        return $this->hasMany(ClassRoom::class);
    }

    /**
     * Sản phẩm dùng để bán khoá học này (loại 'course').
     *
     * Null nghĩa là khoá chưa mở bán trực tuyến — vẫn vào được bằng mã lớp như trước.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Bản cài đặt này đã chạy migration add_product_id_to_courses_table chưa.
     *
     * CHẶN LỖI LÚC TRIỂN KHAI — cùng một bài học với ContactService::onlyExistingColumns():
     * mã nguồn mới lên máy chủ trước, `php artisan migrate` chạy sau. Trong khoảng giữa đó
     * cột courses.product_id chưa tồn tại, mà màn Sửa khoá học lại ghi thẳng cột này lên —
     * kết quả là quản trị KHÔNG sửa nổi bất kỳ khoá học nào, kể cả chỉ đổi mỗi tiêu đề.
     * Thà tạm thời chưa lưu được ô "Bán khoá học này" còn hơn khoá cứng cả màn quản trị.
     *
     * Chạy migrate xong thì tự khớp lại, không phải sửa gì thêm.
     */
    /**
     * Ảnh đại diện ĐÃ TẢI LÊN của khoá học, chưa có thì null.
     *
     * SỬA 15/9 (khách: "thêm field thumbnail") — dùng ở form quản trị để xem trước ảnh hiện
     * tại. Trang công khai KHÔNG gọi hàm này mà tự rơi về bộ ảnh mặc định course-img-1..5.jpg
     * khi null, nên ở đây cố ý trả null chứ không trả sẵn ảnh mặc định: chỗ nào cần biết
     * "khoá này đã có ảnh riêng chưa" vẫn phân biệt được.
     */
    public function coverUrl(): ?string
    {
        return $this->cover_image_path ? asset('storage/'.$this->cover_image_path) : null;
    }

    public static function supportsProduct(): bool
    {
        static $has = null;

        if ($has === null) {
            $has = Schema::hasColumn('courses', 'product_id');
        }

        return $has;
    }

    /** Đã mở bán trực tuyến chưa: phải có sản phẩm VÀ sản phẩm đó phải có giá học. */
    public function isPurchasable(): bool
    {
        return self::supportsProduct()
            && $this->product_id !== null
            && (int) ($this->product?->price ?? 0) > 0;
    }

    /** Giá học cá nhân của khoá, null khi chưa gắn sản phẩm. */
    public function learningPrice(): ?int
    {
        return (self::supportsProduct() && $this->product_id !== null)
            ? (int) ($this->product?->price ?? 0)
            : null;
    }

    /** Lớp đang mở của khoá này — dùng cho màn tự chọn lớp sau khi mua (C3). */
    public function openClassRooms(): HasMany
    {
        return $this->classRooms()->where('status', 'active');
    }

    /**
     * Các lộ trình có chứa khoá học này.
     *
     * Là nhiều–nhiều vì một khoá dùng lại được ở nhiều lộ trình — xem ghi chú ở migration
     * create_learning_path_course_table.
     */
    public function learningPaths(): BelongsToMany
    {
        return $this->belongsToMany(LearningPath::class, 'learning_path_course')
            ->withPivot('sort_order')
            ->withTimestamps();
    }

    /** Khoá này đã được xếp vào lộ trình nào chưa. */
    public function isInAnyLearningPath(): bool
    {
        return $this->learningPaths()->exists();
    }

    /**
     * Số buổi ĐÃ XẾP LỊCH THẬT của tất cả các lớp thuộc khoá này.
     *
     * Khác session_count (số buổi theo chương trình): con số này đếm class_sessions có ngày
     * giờ cụ thể. Màn quản trị hiện cả hai cạnh nhau để phát hiện lớp xếp thiếu buổi.
     */
    public function scheduledSessionCountFor(ClassRoom $classRoom): int
    {
        return $classRoom->sessions()->count();
    }

    /**
     * SỬA 1/10 — Rút HTML do CKEditor soạn thành một đoạn chữ thuần, cắt đúng độ dài, dùng cho
     * dòng tóm tắt dưới tên khoá và thẻ meta description.
     *
     * SỬA LUÔN MỘT LỖI HIỂN THỊ ĐÃ CÓ (khách gửi ảnh: dòng tóm tắt kết thúc bằng ".&..."):
     * trước đây chỗ gọi làm strip_tags() rồi Str::limit() thẳng. CKEditor hay chèn &nbsp;, và
     * strip_tags KHÔNG giải mã thực thể HTML — cắt đúng giữa chuỗi "&nbsp;" thì còn lại dấu "&"
     * trơ ra màn hình. Phải GIẢI MÃ THỰC THỂ TRƯỚC rồi mới cắt.
     *
     * Gộp luôn khoảng trắng: xuống dòng trong HTML thành một dấu cách, để đoạn tóm tắt không bị
     * ngắt dòng giữa chừng trong khung hẹp.
     */
    public static function plainSummary(?string $html, int $limit = 160): string
    {
        $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // \x{00A0} (khoảng trắng không ngắt, chính là &nbsp; sau khi giải mã) cũng là khoảng trắng.
        $text = trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $text) ?? '');

        return $limit > 0 ? \Illuminate\Support\Str::limit($text, $limit) : $text;
    }

    /**
     * SỬA 1/10 — "Ô soạn thảo này có nội dung thật không?" — dùng để quyết định mục "Giới thiệu
     * khoá học" lấy trường intro hay rơi về description.
     *
     * KHÔNG đo bằng trim(strip_tags()): mở CKEditor rồi không gõ gì vẫn lưu xuống
     * "<p>&nbsp;</p>", strip_tags để lại nguyên chuỗi "&nbsp;" nên bị coi là CÓ nội dung và
     * trang công khai hiện ra một khung trống. plainSummary() giải mã thực thể trước nên chuỗi
     * đó về rỗng đúng như mắt người thấy.
     *
     * Vế thứ hai cho trường hợp bài giới thiệu CHỈ có ảnh/bảng/video, không một chữ nào: vẫn là
     * nội dung thật, rơi về mô tả là mất hẳn phần admin đã soạn.
     */
    public static function hasContent(?string $html): bool
    {
        return self::plainSummary($html, 0) !== ''
            || preg_match('/<(img|iframe|video|table|picture|figure)\b/i', (string) $html) === 1;
    }
}

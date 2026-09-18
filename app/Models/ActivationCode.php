<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Enums\AccessScope;
use App\Enums\ActivationCodeStatus;
use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class ActivationCode extends Model
{
    use HasFactory, Auditable;

    /** Đọc bởi App\Concerns\Auditable nếu có set lý do trước save()/delete() (10.4, 16 mục 4). */
    public static ?string $auditReason = null;

    protected $fillable = [
        'code', 'order_item_id', 'product_id', 'scope', 'status',
        'activated_by', 'activated_at', 'validity_months',
        // SỬA 18/9 — mã admin cấp tay: khoá theo TÀI KHOẢN nhận (assigned_user_id), kèm ghi chú
        // và người cấp. Xem migration add_assigned_user_to_activation_codes_table.
        'assigned_user_id', 'note', 'created_by',
    ];

    protected $casts = [
        'scope' => AccessScope::class,
        'status' => ActivationCodeStatus::class,
        'activated_at' => 'datetime',
    ];

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function activatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by');
    }

    /**
     * SỬA 18/9 — cột assigned_user_id/note/created_by đến từ migration 000860; máy chưa chạy
     * `php artisan migrate` thì cột chưa có. Cùng cách xử lý với Course::supportsProduct():
     * hỏi trước 1 lần rồi nhớ, để màn "Mã kích hoạt" bên admin BÁO RÕ "chưa chạy migration"
     * thay vì đổ lỗi SQL "Unknown column" ra giữa trang.
     */
    public static function supportsAssignedUser(): bool
    {
        static $has = null;

        if ($has === null) {
            $has = Schema::hasColumn('activation_codes', 'assigned_user_id');
        }

        return $has;
    }

    /** SỬA 18/9 — tài khoản DUY NHẤT được phép kích hoạt mã này (null = không khoá theo người). */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * SỬA 18/9 — mã này có bị khoá cho đúng 1 tài khoản không, và có phải tài khoản đó không.
     * Nguồn sự thật DUY NHẤT cho luật "mã của tài khoản nào thì tài khoản đó mở" — cả
     * OrderActivationService::canActivate() lẫn màn admin đều đọc ở đây, không ai tự so lại
     * assigned_user_id bằng tay để khỏi có 2 cách hiểu khác nhau.
     */
    public function isAssignedTo(User $user): bool
    {
        return $this->assigned_user_id === null || (int) $this->assigned_user_id === (int) $user->id;
    }

    public function isUsable(): bool
    {
        return $this->status === ActivationCodeStatus::Unused;
    }
}

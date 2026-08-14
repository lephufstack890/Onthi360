<?php

namespace App\Models;

use App\Enums\AssignmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Assignment extends Model
{
    protected $fillable = [
        'class_room_id', 'assessment_id', 'opens_at', 'closes_at', 'due_at',
        'shift_count', 'rules', 'instructions', 'status', 'created_by',
    ];

    protected $casts = [
        'status' => AssignmentStatus::class,
        'opens_at' => 'datetime',
        'closes_at' => 'datetime',
        'due_at' => 'datetime',
        'shift_count' => 'integer',
        'rules' => 'array',
    ];

    public function classRoom(): BelongsTo
    {
        return $this->belongsTo(ClassRoom::class);
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(Attempt::class);
    }

    /** Trạng thái tổng quát của bài giao (không tính riêng ca thi của từng học sinh). */
    public function isOpenNow(): bool
    {
        $now = now();

        return $this->status === AssignmentStatus::Open
            && ($this->opens_at === null || $this->opens_at->lte($now))
            && ($this->closes_at === null || $this->closes_at->gte($now));
    }

    /**
     * Có chia ca thi hay không (note họp 13/8, mục 7: "Các kỳ thi nếu đông quá thì mình
     * chia thành các ca thi để chống tấn công ddos"). shift_count <= 1 = không chia ca.
     */
    public function hasShifts(): bool
    {
        return ((int) ($this->shift_count ?? 1)) > 1;
    }

    /**
     * Chia đều khung [opens_at, closes_at] thành shift_count ca liên tiếp, rồi gán CỐ ĐỊNH
     * và XÁC ĐỊNH (không cần lưu bảng riêng — tránh việc phải đồng bộ lại khi có học sinh
     * vào/ra lớp sau này) mỗi học sinh vào đúng 1 ca theo hash của (assignment_id, user_id).
     * Không có shift (shift_count <= 1) hoặc thiếu 1 trong 2 mốc thời gian thì trả lại
     * nguyên khung gốc của Assignment — hành vi y hệt trước khi có tính năng chia ca.
     *
     * @return array{opens_at: ?Carbon, closes_at: ?Carbon, index: int, count: int}
     */
    public function shiftWindowFor(int $userId): array
    {
        $count = max(1, (int) ($this->shift_count ?? 1));

        if ($count <= 1 || $this->opens_at === null || $this->closes_at === null) {
            return ['opens_at' => $this->opens_at, 'closes_at' => $this->closes_at, 'index' => 0, 'count' => 1];
        }

        $totalSeconds = max(1, $this->opens_at->diffInSeconds($this->closes_at));
        $sliceSeconds = intdiv($totalSeconds, $count);
        $index = crc32('assignment:'.$this->id.':user:'.$userId) % $count;

        $shiftOpensAt = $this->opens_at->copy()->addSeconds($sliceSeconds * $index);
        $shiftClosesAt = $index === $count - 1
            ? $this->closes_at->copy()
            : $this->opens_at->copy()->addSeconds($sliceSeconds * ($index + 1));

        return ['opens_at' => $shiftOpensAt, 'closes_at' => $shiftClosesAt, 'index' => $index, 'count' => $count];
    }

    /**
     * Bài giao có đang mở CHO ĐÚNG học sinh này ngay bây giờ hay không — dùng thay
     * isOpenNow() ở nơi thật sự cho học sinh vào làm bài (App\Services\AttemptService),
     * vì với ca thi, khung giờ mở thực tế khác nhau theo từng học sinh.
     */
    public function isOpenNowFor(int $userId): bool
    {
        if ($this->status !== AssignmentStatus::Open) {
            return false;
        }

        $window = $this->shiftWindowFor($userId);
        $now = now();

        return ($window['opens_at'] === null || $window['opens_at']->lte($now))
            && ($window['closes_at'] === null || $window['closes_at']->gte($now));
    }
}

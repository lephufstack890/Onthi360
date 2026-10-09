<?php

namespace App\Services\Public;

use App\Enums\AccessRightStatus;
use App\Enums\AccessScope;
use App\Enums\ContentStatus;
use App\Enums\ProductType;
use App\Enums\Visibility;
use App\Models\AccessRight;
use App\Models\MaterialAssignment;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * SỬA 9/10 (khách: "trang tài liệu có cả giao tài liệu, check quyền khách vãng lai / học sinh /
 * giáo viên / admin") — toàn bộ LOGIC giao tài liệu riêng từng học sinh, dựng theo education-main
 * (QuickAssignButton kiểu "material", materialAssignmentStatus, getMaterialAccess nguồn "assignment").
 *
 * Bản mẫu lưu tất cả trong localStorage với vai trò giả; ở đây:
 *   · lượt giao nằm trong bảng material_assignments;
 *   · giao xong sinh/gia hạn MỘT dòng access_rights (source='assignment') để học sinh thấy tài liệu
 *     trong "Tài liệu của tôi" và mở đọc được đúng trong thời hạn cấp quyền — tận dụng hạ tầng
 *     quyền sẵn có (AccessGateService), không viết cơ chế quyền thứ hai;
 *   · "Đã mở" được ghi khi học sinh THẬT SỰ mở trang đọc (markOpened).
 *
 * Phân quyền: chỉ giáo viên và admin giao được; giáo viên chỉ thấy lượt MÌNH giao, admin thấy tất cả;
 * học sinh chỉ thấy lượt giao cho chính mình; khách vãng lai và vai trò khác không có phần này.
 */
class MaterialAssignmentService
{
    /** Các mức thời hạn cấp quyền đọc bản mẫu cho chọn (ngày). */
    public const ACCESS_DAYS = [7, 30, 90, 365];

    /** Trần số học sinh cho MỘT lần giao. */
    public const ASSIGN_MAX_STUDENTS = 50;

    /** Trần số dòng cho danh sách quản lý, để trang không phình vô hạn. */
    private const MANAGED_LIMIT = 300;

    public const SOURCE = 'assignment';

    // ───────────────────────── Vai trò ─────────────────────────

    public function isAdmin(?User $user): bool
    {
        return $user !== null && $user->hasAnyRole(Role::ADMIN, Role::SUPER_ADMIN);
    }

    public function isTeacher(?User $user): bool
    {
        return $user !== null && $user->hasRole(Role::TEACHER);
    }

    public function isStudent(?User $user): bool
    {
        return $user !== null && $user->hasRole(Role::STUDENT);
    }

    /** Giáo viên và admin được giao tài liệu / xem lượt đã giao. */
    public function canAssign(?User $user): bool
    {
        return $this->isAdmin($user) || $this->isTeacher($user);
    }

    /** Bảng đã được tạo trên máy chủ chưa (deploy mã trước khi chạy migrate vẫn không vỡ trang). */
    public function isReady(): bool
    {
        static $ready = null;

        return $ready ??= Schema::hasTable('material_assignments');
    }

    /**
     * Khối mô tả quyền đưa sang view Tài liệu.
     *
     * @return array{role: string, hasLibrary: bool, canViewAssigned: bool, canManage: bool}
     */
    public function scopeFor(?User $viewer): array
    {
        $role = match (true) {
            $viewer === null => 'guest',
            $this->isAdmin($viewer) => 'admin',
            $this->isTeacher($viewer) => 'teacher',
            $this->isStudent($viewer) => 'student',
            default => 'member',
        };

        $ready = $this->isReady();

        return [
            'role' => $role,
            // "Tài liệu của tôi": khu đọc chỉ có cho học sinh và giáo viên (admin/vai trò khác không có).
            'hasLibrary' => in_array($role, ['student', 'teacher'], true),
            'canViewAssigned' => $ready && $role === 'student',
            'canManage' => $ready && in_array($role, ['admin', 'teacher'], true),
        ];
    }

    // ───────────────────────── Giao tài liệu ─────────────────────────

    /**
     * Giao một tài liệu cho NHIỀU học sinh cùng lúc. Tất cả hoặc không: kiểm tra xong hết mới ghi.
     *
     * @param  array<int, int|string>  $studentIds
     * @return Collection<int, MaterialAssignment>
     *
     * @throws ValidationException
     */
    public function assignMany(User $by, int $productId, array $studentIds, Carbon $deadline, int $accessDays, ?string $note = null): Collection
    {
        if (! $this->canAssign($by)) {
            throw ValidationException::withMessages(['students' => 'Bạn không có quyền giao nội dung này.']);
        }

        $product = Product::query()
            ->whereKey($productId)
            ->where('status', ContentStatus::Published->value)
            ->where('visibility', Visibility::Public->value)
            ->where('type', '!=', ProductType::Course->value)
            ->first();

        if ($product === null) {
            throw ValidationException::withMessages(['students' => 'Không tìm thấy tài liệu cần giao.']);
        }

        if (! in_array($accessDays, self::ACCESS_DAYS, true)) {
            throw ValidationException::withMessages(['access_days' => 'Vui lòng chọn thời hạn cấp quyền đọc hợp lệ.']);
        }

        $now = now();

        if ($deadline->lte($now)) {
            throw ValidationException::withMessages(['deadline' => 'Hạn đọc phải ở sau thời điểm hiện tại.']);
        }

        $expiresAt = $now->copy()->addDays($accessDays);

        if ($deadline->gt($expiresAt)) {
            throw ValidationException::withMessages(['deadline' => 'Hạn đọc phải nằm trong thời hạn được cấp quyền.']);
        }

        $ids = collect($studentIds)->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->unique()->values();

        if ($ids->isEmpty()) {
            throw ValidationException::withMessages(['students' => 'Chọn ít nhất 1 học sinh.']);
        }

        if ($ids->count() > self::ASSIGN_MAX_STUDENTS) {
            throw ValidationException::withMessages(['students' => 'Mỗi lần giao tối đa '.self::ASSIGN_MAX_STUDENTS.' học sinh.']);
        }

        // Chỉ học sinh THẬT mới nhận được tài liệu — id gửi lên có thể bị sửa tay.
        $students = User::query()
            ->whereIn('id', $ids->all())
            ->whereHas('roles', fn ($q) => $q->where('name', Role::STUDENT))
            ->get(['id', 'name', 'email', 'phone'])
            ->keyBy('id');

        if ($students->count() !== $ids->count()) {
            throw ValidationException::withMessages(['students' => 'Có học sinh không hợp lệ. Hãy chọn lại danh sách.']);
        }

        $note = $note !== null ? mb_substr(trim($note), 0, 1000) : null;
        $note = $note === '' ? null : $note;

        return DB::transaction(function () use ($by, $product, $deadline, $accessDays, $note, $ids, $students, $now, $expiresAt) {
            return $ids->map(function (int $studentId) use ($by, $product, $deadline, $accessDays, $note, $students, $now, $expiresAt) {
                $assignment = MaterialAssignment::query()->updateOrCreate(
                    [
                        'assigned_by' => $by->id,
                        'student_id' => $studentId,
                        'product_id' => $product->id,
                    ],
                    [
                        'deadline_at' => $deadline,
                        'access_days' => $accessDays,
                        'access_expires_at' => $expiresAt,
                        'note' => $note,
                        // Giao lại = lượt mới: học sinh phải mở lại thì mới tính "Đã mở".
                        'opened_at' => null,
                    ],
                );

                $right = $this->grantAccess($by, $assignment, $now, $expiresAt);

                if ($assignment->access_right_id !== $right->id) {
                    $assignment->forceFill(['access_right_id' => $right->id])->save();
                }

                $assignment->setRelation('student', $students->get($studentId));

                return $assignment;
            });
        });
    }

    /**
     * Cấp (hoặc đặt lại) quyền đọc ứng với lượt giao. Mỗi lượt giao có ĐÚNG MỘT dòng access_rights
     * (tìm theo source + source_id) nên giao lại chỉ đổi hạn của dòng đó, không sinh dòng chồng nhau.
     */
    private function grantAccess(User $by, MaterialAssignment $assignment, Carbon $now, Carbon $expiresAt): AccessRight
    {
        $right = AccessRight::query()
            ->where('source', self::SOURCE)
            ->where('source_id', $assignment->id)
            ->first();

        $attributes = [
            'user_id' => $assignment->student_id,
            'product_id' => $assignment->product_id,
            'scope' => AccessScope::PersonalLearning->value,
            'starts_at' => $now,
            'expires_at' => $expiresAt,
            'status' => AccessRightStatus::Active->value,
            'class_limit' => null,
            'source' => self::SOURCE,
            'source_id' => $assignment->id,
            'created_by' => $by->id,
        ];

        AccessRight::$auditReason = 'Giao tài liệu cho học sinh';

        try {
            if ($right !== null) {
                $right->update($attributes);

                return $right;
            }

            return AccessRight::query()->create($attributes);
        } finally {
            AccessRight::$auditReason = null;
        }
    }

    /**
     * Gợi ý học sinh cho ô chọn nhiều — dùng chung luật tìm kiếm với popup Giao bài ở trang Luyện tập.
     *
     * @return array<int, array{id: int, name: string, email: ?string, phone: ?string}>
     */
    public function searchStudents(string $term): array
    {
        return app(PracticeAssignmentService::class)->searchStudents($term);
    }

    // ───────────────────────── Ghi nhận "đã mở" ─────────────────────────

    /**
     * Học sinh vừa mở trang đọc của tài liệu -> các lượt giao còn chưa mở của em được đánh dấu "Đã mở".
     * KHÔNG BAO GIỜ làm hỏng việc đọc: mọi lỗi ở đây (bảng chưa migrate, DB chập chờn...) đều bị nuốt.
     */
    public function markOpened(?User $student, int $productId): void
    {
        if ($student === null || ! $this->isReady() || ! $this->isStudent($student)) {
            return;
        }

        try {
            MaterialAssignment::query()
                ->where('student_id', $student->id)
                ->where('product_id', $productId)
                ->whereNull('opened_at')
                ->update(['opened_at' => now()]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    // ───────────────────────── Phía học sinh ─────────────────────────

    /**
     * Tài liệu ĐƯỢC GIAO cho một học sinh, kèm trạng thái đọc. Khoá = product_id; cùng một tài liệu
     * được nhiều thầy cô giao thì giữ lượt MỚI NHẤT.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forStudent(User $student): array
    {
        if (! $this->isReady()) {
            return [];
        }

        $assignments = MaterialAssignment::query()
            ->where('student_id', $student->id)
            ->with('assigner:id,name')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        $out = [];

        foreach ($assignments as $a) {
            if (isset($out[$a->product_id])) {
                continue;
            }

            $out[$a->product_id] = $this->row($a) + [
                'teacher' => $a->assigner?->name ?? 'Giáo viên',
            ];
        }

        return $out;
    }

    // ───────────────────────── Phía giáo viên / admin ─────────────────────────

    /**
     * Danh sách "Tài liệu đã giao": mỗi dòng là một học sinh nhận tài liệu.
     * Giáo viên chỉ thấy lượt MÌNH giao; admin thấy tất cả.
     *
     * @return list<array<string, mixed>>
     */
    public function managed(User $viewer): array
    {
        if (! $this->isReady() || ! $this->canAssign($viewer)) {
            return [];
        }

        $query = MaterialAssignment::query()
            ->with(['student:id,name,email,phone', 'assigner:id,name'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(self::MANAGED_LIMIT);

        if (! $this->isAdmin($viewer)) {
            $query->where('assigned_by', $viewer->id);
        }

        return $query->get()->map(fn (MaterialAssignment $a) => $this->row($a) + [
            'teacher' => $a->assigner?->name ?? '—',
            'studentName' => $a->student?->name ?? '—',
            'account' => $a->student?->email ?: ($a->student?->phone ?: '—'),
        ])->values()->all();
    }

    /**
     * Một lượt giao ở dạng mảng thuần để đưa sang giao diện.
     *
     * @return array<string, mixed>
     */
    private function row(MaterialAssignment $a): array
    {
        $now = now();
        $expired = $a->access_expires_at->lte($now);

        $status = match (true) {
            $a->opened_at !== null => 'opened',
            $a->deadline_at->lt($now) => 'overdue',
            default => 'todo',
        };

        return [
            'id' => $a->id,
            'productId' => $a->product_id,
            'status' => $status,
            'statusLabel' => match ($status) {
                'opened' => 'Đã mở tài liệu',
                'overdue' => 'Quá hạn đọc',
                default => 'Chưa mở',
            },
            'note' => $a->note,
            'assignedAt' => ($a->created_at ?? $now)->format('d/m/Y H:i'),
            'assignedTs' => ($a->updated_at ?? $a->created_at ?? $now)->getTimestamp(),
            'deadline' => $a->deadline_at->format('d/m/Y H:i'),
            'deadlineTs' => $a->deadline_at->getTimestamp(),
            'accessDays' => $a->access_days,
            'accessExpiresAt' => $a->access_expires_at->format('d/m/Y H:i'),
            'accessExpired' => $expired,
        ];
    }
}

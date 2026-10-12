<?php

namespace App\Services\Student;

use App\Enums\ParentLinkStatus;
use App\Models\ParentLink;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * SỬA 12/10 — trang "Phụ huynh" của khu Học sinh (source: StudentParentsPanel.jsx).
 *
 * Học sinh TỰ quyết định ai được theo dõi mình:
 *  - tìm phụ huynh bằng email → kiểm tra tên → xác nhận liên kết (liên kết có hiệu lực ngay, verification_method=student_confirm);
 *  - đồng ý / từ chối yêu cầu phụ huynh đã gửi (đang chờ);
 *  - hủy liên kết (đổi sang revoked, giữ lịch sử — Auditable ghi nhật ký).
 * Quyền xem của phụ huynh vẫn chỉ dựa vào status=verified (10.3), không đổi.
 */
class StudentParentLinkService
{
    public const RELATIONSHIPS = ['Mẹ', 'Bố', 'Người giám hộ'];

    private static ?bool $hasRelationship = null;

    private function hasRelationship(): bool
    {
        return self::$hasRelationship ??= Schema::hasColumn('parent_links', 'relationship');
    }

    /** @return array{linked: list<array<string,mixed>>, requests: list<array<string,mixed>>} */
    public function panel(User $student): array
    {
        $links = ParentLink::query()->where('student_user_id', $student->id)->with('parent')->latest('id')->get();

        $linked = [];
        $requests = [];
        foreach ($links as $l) {
            $row = [
                'id' => (int) $l->id,
                'name' => $l->parent?->name ?? 'Tài khoản không còn khả dụng',
                'email' => $l->parent?->email,
                'relationship' => filled($l->getAttribute('relationship')) ? (string) $l->getAttribute('relationship') : 'Phụ huynh',
                'active' => ($l->parent?->status ?? 'active') === 'active',
                'since' => ($l->verified_at ?? $l->created_at)?->format('d/m/Y'),
            ];
            if ($l->status === ParentLinkStatus::Verified) {
                $linked[] = $row;
            } elseif ($l->status === ParentLinkStatus::Pending && $l->parent !== null) {
                $requests[] = $row;
            }
        }

        return ['linked' => $linked, 'requests' => $requests];
    }

    /** Tìm phụ huynh đang hoạt động theo email. @return array{name: string, email: string} */
    public function find(User $student, string $identifier): array
    {
        return $this->presentCandidate($this->resolveParent($student, $identifier));
    }

    public function link(User $student, string $identifier, string $relationship): ParentLink
    {
        if (! in_array($relationship, self::RELATIONSHIPS, true)) {
            throw ValidationException::withMessages(['relationship' => 'Chọn mối quan hệ với phụ huynh.']);
        }
        $parent = $this->resolveParent($student, $identifier);

        $payload = [
            'status' => ParentLinkStatus::Verified->value,
            'verification_method' => 'student_confirm',
            'verified_at' => now(),
        ];
        if ($this->hasRelationship()) {
            $payload['relationship'] = $relationship;
        }

        $existing = ParentLink::query()->where('parent_user_id', $parent->id)->where('student_user_id', $student->id)->first();
        if ($existing) {
            $existing->update($payload);

            return $existing;
        }

        return ParentLink::create($payload + ['parent_user_id' => $parent->id, 'student_user_id' => $student->id]);
    }

    public function respond(User $student, ParentLink $link, bool $accept): ParentLink
    {
        $this->ensureOwner($student, $link);
        if ($link->status !== ParentLinkStatus::Pending) {
            throw ValidationException::withMessages(['link' => 'Yêu cầu đã được xử lý. Hãy tải lại trang.']);
        }

        $link->update($accept
            ? ['status' => ParentLinkStatus::Verified->value, 'verification_method' => 'student_confirm', 'verified_at' => now()]
            : ['status' => ParentLinkStatus::Revoked->value]);

        return $link;
    }

    public function unlink(User $student, ParentLink $link): ParentLink
    {
        $this->ensureOwner($student, $link);
        if ($link->status !== ParentLinkStatus::Verified) {
            throw ValidationException::withMessages(['link' => 'Liên kết này không còn hiệu lực.']);
        }
        $link->update(['status' => ParentLinkStatus::Revoked->value]);

        return $link;
    }

    private function ensureOwner(User $student, ParentLink $link): void
    {
        abort_unless((int) $link->student_user_id === (int) $student->id, 404);
    }

    private function resolveParent(User $student, string $identifier): User
    {
        $email = mb_strtolower(trim($identifier));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['identifier' => 'Nhập email phụ huynh hợp lệ.']);
        }

        $parent = User::query()->whereRaw('lower(email) = ?', [$email])->whereHas('roles', fn ($q) => $q->where('name', Role::PARENT))->first();
        if (! $parent || ($parent->status ?? 'active') !== 'active' || (int) $parent->id === (int) $student->id) {
            throw ValidationException::withMessages(['identifier' => 'Không tìm thấy phụ huynh đang hoạt động. Kiểm tra email đã đăng ký.']);
        }

        $verified = ParentLink::query()->where('parent_user_id', $parent->id)->where('student_user_id', $student->id)
            ->where('status', ParentLinkStatus::Verified->value)->exists();
        if ($verified) {
            throw ValidationException::withMessages(['identifier' => 'Phụ huynh này đã được liên kết.']);
        }

        return $parent;
    }

    /** @return array{name: string, email: string} */
    private function presentCandidate(User $parent): array
    {
        return ['name' => $parent->name, 'email' => $parent->email];
    }
}

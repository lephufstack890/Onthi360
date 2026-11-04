<?php

namespace App\Services\Admin;

use App\Enums\ParentLinkStatus;
use App\Models\ClassEnrollment;
use App\Models\ClassRoom;
use App\Models\ParentLink;
use App\Models\Role;
use App\Models\SystemAnnouncement;
use App\Models\User;
use App\Notifications\SystemAnnouncementPublished;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * SỬA 4/11 (khách: "admin có thể thông báo cho học sinh và phụ huynh") — soạn / gửi / thu hồi thông báo chung của Quản trị.
 *
 *  · Người nhận: Học sinh / Phụ huynh / Giáo viên (chọn nhiều), phạm vi Toàn hệ thống hoặc Theo lớp.
 *      - Toàn hệ thống: mọi tài khoản đang hoạt động có vai trò tương ứng.
 *      - Theo lớp: học sinh đang học trong lớp, phụ huynh đã liên kết (đã xác minh) của các em, giáo viên phụ trách lớp.
 *    Một người thuộc nhiều nhóm (vd vừa giáo viên vừa phụ huynh) chỉ nhận 1 lần.
 *  · Gửi = ghi thẳng từng lô 500 dòng vào bảng notifications (cùng cấu trúc kênh database của Laravel) để gửi cả hệ thống
 *    vẫn nhanh. Thu hồi = xoá các dòng đó (theo khoá ref "sys:<id>") và đánh dấu "Đã thu hồi".
 *  · Ghim trang chủ: hiện ở thanh "Thông báo hệ thống" đầu trang chủ (Public\HomeService::systemNotices()).
 */
class SystemAnnouncementService
{
    private const CHUNK = 500;

    /** @return list<array<string, mixed>> */
    public function rows(): array
    {
        if (! SystemAnnouncement::isReady()) {
            return [];
        }

        $classNames = ClassRoom::query()->pluck('name', 'id');

        return SystemAnnouncement::query()
            ->with('author:id,name')
            ->orderByRaw("CASE WHEN state = 'draft' THEN 0 ELSE 1 END")
            ->orderByDesc('updated_at')
            ->limit(200)
            ->get()
            ->map(fn (SystemAnnouncement $a) => [
                'id' => $a->id,
                'title' => (string) $a->title,
                'body' => (string) $a->body,
                'level' => $a->level === 'important' ? 'important' : 'normal',
                'audiences' => $a->audienceList(),
                'audienceLabel' => $a->audienceLabel(),
                'scope' => $a->scope === 'classes' ? 'classes' : 'all',
                'classIds' => array_values(array_map('intval', (array) ($a->class_ids ?? []))),
                'classLabel' => $a->scope === 'classes'
                    ? collect((array) ($a->class_ids ?? []))->map(fn ($id) => $classNames[$id] ?? null)->filter()->implode(', ')
                    : 'Toàn hệ thống',
                'pinHome' => (bool) $a->pin_home,
                'pinUntil' => $a->pin_until?->format('Y-m-d'),
                'pinnedNow' => $a->isPinnedNow(),
                'state' => $a->state,
                'students' => (int) $a->student_count,
                'parents' => (int) $a->parent_count,
                'teachers' => (int) $a->teacher_count,
                'author' => (string) ($a->author->name ?? 'Quản trị viên'),
                'updatedAt' => $a->updated_at?->format('d/m/Y H:i'),
                'publishedAt' => $a->published_at?->format('d/m/Y H:i'),
                'recalledAt' => $a->recalled_at?->format('d/m/Y H:i'),
            ])->all();
    }

    /** Lớp để chọn khi gửi "Theo lớp" (lớp chưa lưu trữ). @return list<array{id:int,name:string,code:string}> */
    public function classOptions(): array
    {
        return ClassRoom::query()
            ->where('status', '!=', 'archived')
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (ClassRoom $c) => ['id' => (int) $c->id, 'name' => (string) $c->name, 'code' => (string) $c->code])
            ->all();
    }

    /** Số người sẽ nhận (đã bỏ trùng giữa các nhóm). @return array{students:int,parents:int,teachers:int,total:int} */
    public function preview(array $audiences, string $scope, array $classIds): array
    {
        $ids = $this->recipients($audiences, $scope, $classIds);

        return [
            'students' => $ids['students']->count(),
            'parents' => $ids['parents']->count(),
            'teachers' => $ids['teachers']->count(),
            'total' => $ids['all']->count(),
        ];
    }

    /**
     * Lưu nháp hoặc gửi.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function save(User $author, array $data, bool $publish): SystemAnnouncement
    {
        if (! SystemAnnouncement::isReady()) {
            throw ValidationException::withMessages(['title' => 'Chưa cập nhật cơ sở dữ liệu cho thông báo. Hãy chạy php artisan migrate.']);
        }

        $title = trim((string) ($data['title'] ?? ''));
        $body = trim((string) ($data['body'] ?? ''));
        $audiences = array_values(array_intersect(array_keys(SystemAnnouncement::AUDIENCES), (array) ($data['audiences'] ?? [])));
        $scope = ($data['scope'] ?? 'all') === 'classes' ? 'classes' : 'all';
        $classIds = $scope === 'classes'
            ? ClassRoom::query()->whereIn('id', array_map('intval', (array) ($data['class_ids'] ?? [])))->pluck('id')->map(fn ($v) => (int) $v)->values()->all()
            : [];
        $pinHome = filter_var($data['pin_home'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $pinUntil = $pinHome && ! empty($data['pin_until']) ? \Illuminate\Support\Carbon::parse((string) $data['pin_until'])->endOfDay() : null;

        if ($publish) {
            if ($title === '' || $body === '') {
                throw ValidationException::withMessages(['title' => 'Nhập tiêu đề và nội dung trước khi gửi.']);
            }
            if ($audiences === []) {
                throw ValidationException::withMessages(['audiences' => 'Chọn ít nhất một nhóm người nhận (Học sinh / Phụ huynh / Giáo viên).']);
            }
            if ($scope === 'classes' && $classIds === []) {
                throw ValidationException::withMessages(['class_ids' => 'Chọn ít nhất một lớp nhận thông báo.']);
            }
        }

        $announcement = null;
        if (! empty($data['id'])) {
            $announcement = SystemAnnouncement::query()->find((int) $data['id']);
            if ($announcement === null) {
                throw ValidationException::withMessages(['title' => 'Thông báo này không còn trong danh sách.']);
            }
            if ($announcement->state !== SystemAnnouncement::STATE_DRAFT) {
                throw ValidationException::withMessages(['title' => 'Thông báo đã gửi không sửa được. Hãy thu hồi rồi tạo thông báo mới.']);
            }
        }

        $announcement ??= new SystemAnnouncement(['author_id' => $author->id, 'state' => SystemAnnouncement::STATE_DRAFT]);
        $announcement->fill([
            'title' => $title !== '' ? Str::limit($title, 150, '') : null,
            'body' => $body !== '' ? $body : null,
            'level' => ($data['level'] ?? 'normal') === 'important' ? 'important' : 'normal',
            'audiences' => $audiences,
            'scope' => $scope,
            'class_ids' => $classIds,
            'pin_home' => $pinHome,
            'pin_until' => $pinUntil,
        ]);

        if (! $publish) {
            $announcement->state = SystemAnnouncement::STATE_DRAFT;
            $announcement->save();

            return $announcement;
        }

        $ids = $this->recipients($audiences, $scope, $classIds);
        if ($ids['all']->isEmpty()) {
            throw ValidationException::withMessages(['audiences' => 'Không có tài khoản nào thuộc nhóm / lớp đã chọn để nhận thông báo.']);
        }

        DB::transaction(function () use ($announcement, $author, $ids) {
            $announcement->fill([
                'author_id' => $announcement->author_id ?: $author->id,
                'state' => SystemAnnouncement::STATE_PUBLISHED,
                'student_count' => $ids['students']->count(),
                'parent_count' => $ids['parents']->count(),
                'teacher_count' => $ids['teachers']->count(),
                'published_at' => now(),
            ])->save();

            $this->deliver($announcement, $ids['all']);
        });

        return $announcement;
    }

    public function deleteDraft(int $id): bool
    {
        if (! SystemAnnouncement::isReady()) {
            return false;
        }

        return (bool) SystemAnnouncement::query()->where('id', $id)->where('state', SystemAnnouncement::STATE_DRAFT)->delete();
    }

    /** Thu hồi thông báo đã gửi: xoá khỏi hộp thư người nhận, bỏ ghim trang chủ, giữ lại bản ghi làm lịch sử. Trả về số thông báo đã gỡ. */
    public function recall(int $id): int
    {
        if (! SystemAnnouncement::isReady()) {
            return 0;
        }

        $a = SystemAnnouncement::query()->where('id', $id)->where('state', SystemAnnouncement::STATE_PUBLISHED)->first();
        if ($a === null) {
            return 0;
        }

        return DB::transaction(function () use ($a) {
            $removed = DB::table('notifications')
                ->where('type', SystemAnnouncementPublished::class)
                ->where('data', 'like', '%"ref":"'.$a->refKey().'"%')
                ->delete();

            $a->fill(['state' => SystemAnnouncement::STATE_RECALLED, 'recalled_at' => now(), 'pin_home' => false])->save();

            return $removed;
        });
    }

    /** Bật / tắt ghim trang chủ cho thông báo đã gửi. */
    public function togglePin(int $id): ?bool
    {
        if (! SystemAnnouncement::isReady()) {
            return null;
        }

        $a = SystemAnnouncement::query()->where('id', $id)->where('state', SystemAnnouncement::STATE_PUBLISHED)->first();
        if ($a === null) {
            return null;
        }

        $pinned = $a->isPinnedNow();
        $a->fill(['pin_home' => ! $pinned, 'pin_until' => $pinned ? $a->pin_until : null])->save();

        return ! $pinned;
    }

    /** Thông báo đang ghim cho thanh "Thông báo hệ thống" trang chủ. @return list<SystemAnnouncement> */
    public static function pinnedForHome(int $limit = 3): array
    {
        if (! SystemAnnouncement::isReady()) {
            return [];
        }

        return SystemAnnouncement::query()
            ->where('state', SystemAnnouncement::STATE_PUBLISHED)
            ->where('pin_home', true)
            ->where(fn ($q) => $q->whereNull('pin_until')->orWhere('pin_until', '>', now()))
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get()
            ->all();
    }

    /**
     * Danh sách id người nhận theo nhóm (đã lọc tài khoản đang hoạt động) + 'all' là hợp đã bỏ trùng.
     *
     * @return array{students: Collection<int,int>, parents: Collection<int,int>, teachers: Collection<int,int>, all: Collection<int,int>}
     */
    public function recipients(array $audiences, string $scope, array $classIds): array
    {
        $audiences = array_values(array_intersect(array_keys(SystemAnnouncement::AUDIENCES), $audiences));
        $classIds = array_values(array_filter(array_map('intval', $classIds)));
        $out = ['students' => collect(), 'parents' => collect(), 'teachers' => collect()];

        if ($scope === 'classes') {
            if ($classIds !== []) {
                $studentIds = ClassEnrollment::query()
                    ->whereIn('class_room_id', $classIds)->where('status', ClassEnrollment::STATUS_ACTIVE)
                    ->pluck('student_id')->unique()->values();

                if (in_array('students', $audiences, true)) {
                    $out['students'] = $studentIds;
                }
                if (in_array('parents', $audiences, true) && $studentIds->isNotEmpty()) {
                    $out['parents'] = ParentLink::query()
                        ->whereIn('student_user_id', $studentIds->all())
                        ->where('status', ParentLinkStatus::Verified->value)
                        ->pluck('parent_user_id')->unique()->values();
                }
                if (in_array('teachers', $audiences, true)) {
                    $out['teachers'] = DB::table('class_teachers')->whereIn('class_room_id', $classIds)
                        ->pluck('user_id')->unique()->values();
                }
            }
        } else {
            $roleOf = ['students' => Role::STUDENT, 'parents' => Role::PARENT, 'teachers' => Role::TEACHER];
            foreach ($audiences as $aud) {
                $out[$aud] = DB::table('role_user')
                    ->join('roles', 'roles.id', '=', 'role_user.role_id')
                    ->where('roles.name', $roleOf[$aud])
                    ->pluck('role_user.user_id')->unique()->values();
            }
        }

        // Chỉ tài khoản còn hoạt động (không khoá, chưa xoá).
        $candidate = collect($out)->flatten()->unique()->values();
        $active = $candidate->isEmpty() ? collect() : $candidate->chunk(1000)->flatMap(fn ($chunk) => User::query()
            ->whereIn('id', $chunk->all())
            ->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', 'suspended'))
            ->pluck('id'))->map(fn ($v) => (int) $v)->flip();

        foreach ($out as $k => $ids) {
            $out[$k] = $ids->map(fn ($v) => (int) $v)->filter(fn ($id) => $active->has($id))->values();
        }
        $out['all'] = collect($out)->flatten()->unique()->values();

        return $out;
    }

    /** Ghi thông báo vào hộp thư người nhận theo lô (cùng cấu trúc kênh database của Laravel). */
    private function deliver(SystemAnnouncement $a, Collection $userIds): void
    {
        $data = json_encode(SystemAnnouncementPublished::payload($a), JSON_UNESCAPED_UNICODE);
        $type = SystemAnnouncementPublished::class;
        $morph = (new User)->getMorphClass();
        $now = now();

        foreach ($userIds->chunk(self::CHUNK) as $chunk) {
            DB::table('notifications')->insert($chunk->map(fn ($uid) => [
                'id' => (string) Str::uuid(),
                'type' => $type,
                'notifiable_type' => $morph,
                'notifiable_id' => (int) $uid,
                'data' => $data,
                'read_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ])->values()->all());
        }
    }
}

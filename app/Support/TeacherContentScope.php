<?php

namespace App\Support;

use App\Enums\OwnerType;
use App\Models\Assessment;
use App\Models\AssessmentCodingItem;
use App\Models\AssessmentItem;
use App\Models\Question;
use App\Models\Role;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SỬA 6/11 (khách: "tab Đề thi và thêm đề thi bên giáo viên UI và logic giống 100% admin") — giáo viên THƯỜNG dùng thẳng
 * các màn Đề thi của khu quản trị (tạo đề / bộ bài, nhập ZIP, sửa, chọn câu, điểm từng câu, thứ tự, đề PDF, bài lập trình,
 * nhóm mã đề, phát hành, lưu trữ, xoá) — trong khung khu Giáo viên, và CHỈ trên đề của chính mình:
 *
 *  · Chỉ các route trong ROUTES; route quản trị khác vẫn 403 như cũ.
 *  · Mọi route có {assessment} / {item} / {codingItem} / {content}: đề phải là owner_type = teacher, owner_id = giáo viên.
 *  · Gắn mã đề (child_id) và chọn câu cho đề (question_ids) cũng chỉ nhận đề / câu của chính giáo viên.
 *  · Đề / câu tạo mới ở đây thuộc kho riêng của giáo viên (ownerAttributes()), không vào Kho chung.
 *  · Danh sách Kho / Sửa câu hỏi của quản trị → chuyển về trang tương ứng bên Giáo viên.
 *
 * Gọi từ EnsureHasRole (khi giáo viên không có vai quản trị); EnsureAdminPermission bỏ qua request đã được mở ở đây.
 */
final class TeacherContentScope
{
    public const ATTRIBUTE = 'teacher_content_scope';

    public const ROUTES = [
        'admin.content.index',
        'admin.content.show',
        'admin.content.questions.create',
        'admin.content.questions.edit',
        'admin.content.questions.destroy', // nút "Xoá" câu trong danh sách câu của đề (chỉ câu của chính giáo viên)
        'admin.content.assessments.create',
        'admin.content.assessments.store',
        'admin.content.assessments.zipImport',
        'admin.content.assessments.edit',
        'admin.content.assessments.update',
        'admin.content.assessments.destroy',
        'admin.content.assessments.publish',
        'admin.content.assessments.reject',
        'admin.content.assessments.archive',
        'admin.content.assessments.items.edit',
        'admin.content.assessments.items.update',
        'admin.content.assessments.items.move',
        'admin.content.assessments.items.destroy',
        'admin.content.assessments.items.publish',
        'admin.content.assessments.items.publishAll',
        'admin.content.assessments.items.points',
        'admin.content.assessments.pdf.edit',
        'admin.content.assessments.pdf.update',
        'admin.content.assessments.coding-items.store',
        'admin.content.assessments.coding-items.update',
        'admin.content.assessments.coding-items.destroy',
        'admin.content.assessments.coding-items.test-cases.import',
        'admin.content.assessments.variants.attach',
        'admin.content.assessments.variants.code',
        'admin.content.assessments.variants.detach',
    ];

    /** Giáo viên thường: có vai Giáo viên, KHÔNG có vai quản trị / biên tập, và không phải quản trị đang "xem như giáo viên". */
    public static function isPlainTeacher(?User $user): bool
    {
        return $user !== null
            && $user->actingSessionRole() === null
            && $user->hasRole(Role::TEACHER)
            && ! $user->hasAnyRole(Role::ADMIN, Role::SUPER_ADMIN, Role::EDITOR);
    }

    /** Request hiện tại là giáo viên dùng màn Đề thi của quản trị (đã qua kiểm tra ở handle()). */
    public static function active(): bool
    {
        try {
            return (bool) request()->attributes->get(self::ATTRIBUTE, false);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Giáo viên đang dùng các màn này (null nếu không). */
    public static function teacherId(): ?int
    {
        return self::active() ? (int) auth()->id() : null;
    }

    /** Chủ sở hữu cho nội dung tạo mới: giáo viên thường → kho riêng; còn lại → Kho chung như cũ. */
    public static function ownerAttributes(?User $actor = null): array
    {
        $actor ??= auth()->user();

        return $actor !== null && self::isPlainTeacher($actor) && self::active()
            ? ['owner_type' => OwnerType::Teacher->value, 'owner_id' => $actor->id]
            : ['owner_type' => OwnerType::Shared->value, 'owner_id' => null];
    }

    /**
     * @param  list<string>  $roles  vai trò route yêu cầu
     * @return Response|null null = không áp dụng (EnsureHasRole xử lý tiếp như cũ)
     */
    public static function handle(Request $request, Closure $next, array $roles): ?Response
    {
        $user = $request->user();
        $name = $request->route()?->getName();
        if (! is_string($name) || ! in_array($name, self::ROUTES, true)
            || array_intersect($roles, [Role::ADMIN, Role::SUPER_ADMIN, Role::EDITOR]) === []
            || ! self::isPlainTeacher($user)) {
            return null;
        }
        if (! $user->isTeacherApproved()) {
            return redirect()->route('teacher.dashboard')
                ->with('warning', 'Hồ sơ giáo viên của bạn đang chờ Admin duyệt — chưa thể soạn đề.');
        }

        // Trang của quản trị chỉ để chuyển hướng sang trang tương ứng bên Giáo viên.
        if ($name === 'admin.content.index') {
            $tab = (string) $request->query('tab', 'assessments');
            $target = $tab === 'questions'
                ? ['tab' => $request->query('in_exam') === 'used' ? 'used' : 'unused']
                : ['tab' => 'assessments'];

            return redirect()->route('teacher.questions.index', $target + array_filter(['q' => $request->query('q')]));
        }
        if ($name === 'admin.content.questions.create') {
            return redirect()->route('teacher.questions.create', array_filter(['type' => $request->query('type')]));
        }
        if ($name === 'admin.content.questions.destroy') {
            self::ownedQuestion(self::param($request, 'question'), $user);
        }
        if ($name === 'admin.content.questions.edit') {
            return redirect()->route('teacher.questions.edit', self::ownedQuestion(self::param($request, 'question'), $user)->id);
        }
        if ($name === 'admin.content.show') {
            $kind = (string) $request->query('kind', '');
            $id = self::param($request, 'content');
            if ($kind === 'question') {
                return redirect()->route('teacher.questions.edit', self::ownedQuestion($id, $user)->id);
            }
            abort_unless($kind === '' || $kind === 'assessment', 403, 'Bạn chỉ xem được đề của chính mình.');
            self::ownedAssessment($id, $user);
            $request->query->set('kind', 'assessment'); // không để showData() đoán nhầm sang học liệu / câu hỏi trùng id
        }

        // Đề trong đường dẫn phải là đề của chính giáo viên.
        if (($a = self::param($request, 'assessment')) !== null) {
            self::ownedAssessment($a, $user);
        }
        if (($i = self::param($request, 'item')) !== null) {
            $item = $i instanceof AssessmentItem ? $i : AssessmentItem::query()->find((int) $i);
            abort_if($item === null, 404);
            self::ownedAssessment($item->assessment_id, $user);
        }
        if (($c = self::param($request, 'codingItem')) !== null) {
            $coding = $c instanceof AssessmentCodingItem ? $c : AssessmentCodingItem::query()->find((int) $c);
            abort_if($coding === null, 404);
            self::ownedAssessment($coding->assessment_id, $user);
        }
        if ($name === 'admin.content.assessments.variants.attach' && $request->filled('child_id')) {
            self::ownedAssessment((int) $request->input('child_id'), $user);
        }
        if ($name === 'admin.content.assessments.items.update') {
            $ids = array_values(array_unique(array_map('intval', (array) $request->input('question_ids', []))));
            $owned = $ids === [] ? 0 : Question::query()->whereIn('id', $ids)
                ->where('owner_type', OwnerType::Teacher->value)->where('owner_id', $user->id)->count();
            abort_unless($owned === count($ids), 403, 'Chỉ chọn được câu hỏi trong kho của chính bạn.');
        }

        $request->attributes->set(self::ATTRIBUTE, true);

        return $next($request);
    }

    private static function param(Request $request, string $key): mixed
    {
        return $request->route()?->parameter($key);
    }

    private static function ownedAssessment(mixed $value, User $user): Assessment
    {
        $a = $value instanceof Assessment ? $value : Assessment::query()->find((int) $value);
        abort_if($a === null, 404);
        abort_unless(self::ownedBy($a, $user), 403, 'Bạn chỉ xem / sửa được đề của chính mình.');

        return $a;
    }

    private static function ownedQuestion(mixed $value, User $user): Question
    {
        $q = $value instanceof Question ? $value : Question::query()->find((int) $value);
        abort_if($q === null, 404);
        abort_unless(self::ownedBy($q, $user), 403, 'Bạn chỉ xem / sửa được câu hỏi của chính mình.');

        return $q;
    }

    private static function ownedBy(Model $m, User $user): bool
    {
        $type = $m->owner_type instanceof \BackedEnum ? $m->owner_type->value : (string) $m->owner_type;

        return $type === OwnerType::Teacher->value && (int) $m->owner_id === (int) $user->id;
    }
}

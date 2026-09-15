<?php

namespace App\Services\Access;

use App\Models\AccessRight;
use App\Models\ClassRoom;
use App\Models\Course;
use App\Models\User;
use App\Repositories\Contracts\AccessRightRepositoryInterface;
use App\Repositories\Contracts\ClassEnrollmentRepositoryInterface;
use App\Repositories\Contracts\ClassRoomRepositoryInterface;
use App\Repositories\Contracts\CourseRepositoryInterface;
use Illuminate\Validation\ValidationException;

/**
 * C3 — Mua xong khoá học thì tự chọn lớp.
 *
 * ── Hai đường vào lớp, cố ý giữ cả hai ──
 *   · ĐƯỜNG CHÍNH (mới): mua khoá → có quyền → tự chọn một lớp đang mở.
 *   · ĐƯỜNG PHỤ (giữ nguyên): giáo viên đưa mã lớp, học sinh nhập mã
 *     (App\Services\Student\ClassRoomService::joinByCode()).
 * Không bỏ đường mã lớp vì lớp học ở trung tâm và lớp mở theo yêu cầu vẫn chạy bằng mã;
 * bỏ đi là chặn mất một nhóm học sinh đang học thật.
 *
 * ── Vì sao kiểm quyền ở đây chứ không tin route ──
 * Route đã có middleware auth, nhưng quyền MUA KHOÁ NÀY là chuyện khác: một người đăng nhập
 * bình thường vẫn gõ thẳng được địa chỉ lớp của khoá chưa mua. Mọi lần ghi danh đều tự kiểm
 * tra lại quyền, không dựa vào việc màn trước đã kiểm.
 */
class CourseEnrollmentService
{
    public function __construct(
        private CourseRepositoryInterface $courses,
        private ClassRoomRepositoryInterface $classRooms,
        private ClassEnrollmentRepositoryInterface $classEnrollments,
        private AccessRightRepositoryInterface $accessRights,
    ) {}

    /** Màn "Chọn lớp để vào học" của một khoá. */
    public function chooseClassData(User $user, int $courseId): array
    {
        $course = $this->courses->query()->with('product')->findOrFail($courseId);

        $myClassRoomIds = $this->classEnrollments->activeClassRoomIdsForUser($user->id);

        $openClasses = $this->classRooms->query()
            ->where('course_id', $course->id)
            ->where('status', 'active')
            ->withCount('students')
            ->with(['teachers:id,name'])
            ->orderBy('name')
            ->get()
            ->map(fn (ClassRoom $c) => [
                'id' => $c->id,
                'code' => $c->code,
                'name' => $c->name,
                // schedule lưu dạng ['note' => '...'] — xem CourseService::storeClass().
                'scheduleNote' => $c->schedule['note'] ?? null,
                'studentCount' => (int) $c->students_count,
                'teachers' => $c->teachers->pluck('name')->all(),
                'joined' => in_array($c->id, $myClassRoomIds, true),
            ])
            ->all();

        return [
            'course' => $course,
            'openClasses' => $openClasses,
            'hasRight' => $this->hasActiveRight($user, $course),
            // Đã ở sẵn trong một lớp nào của khoá này chưa — để đổi nút thành "Vào học".
            'joinedClassId' => collect($openClasses)->firstWhere('joined', true)['id'] ?? null,
        ];
    }

    /**
     * Ghi danh vào một lớp đang mở của khoá đã mua.
     *
     * @throws ValidationException khi chưa có quyền, lớp không thuộc khoá, lớp đã đóng hoặc đã ở trong lớp.
     */
    public function enroll(User $user, int $courseId, int $classRoomId): ClassRoom
    {
        $course = $this->courses->findOrFail($courseId);

        if (! $this->hasActiveRight($user, $course)) {
            throw ValidationException::withMessages([
                'class_room_id' => 'Bạn chưa có quyền học khoá này. Hãy đặt mua khoá học hoặc nhập mã kích hoạt trước.',
            ]);
        }

        $classRoom = $this->classRooms->query()
            ->where('id', $classRoomId)
            ->where('course_id', $course->id)
            ->where('status', 'active')
            ->first();

        if ($classRoom === null) {
            throw ValidationException::withMessages([
                'class_room_id' => 'Lớp không còn mở hoặc không thuộc khoá học này.',
            ]);
        }

        $existing = $this->classEnrollments->findAnyForUserAndClassRoom($user->id, $classRoom->id);

        if ($existing !== null && $existing->status === 'active') {
            throw ValidationException::withMessages([
                'class_room_id' => 'Bạn đã ở trong lớp này rồi.',
            ]);
        }

        if ($existing !== null) {
            // Từng rời lớp rồi quay lại: unique(class_room_id, student_id) không cho thêm dòng
            // mới nên phải bật lại đúng dòng cũ — cùng cách joinByCode() đang làm.
            $this->classEnrollments->update($existing, [
                'status' => 'active',
                'enrolled_at' => now(),
                'left_at' => null,
            ]);
        } else {
            $this->classEnrollments->create([
                'class_room_id' => $classRoom->id,
                'student_id' => $user->id,
                'status' => 'active',
                'enrolled_at' => now(),
            ]);
        }

        return $classRoom;
    }

    /**
     * Người này có quyền còn hiệu lực với sản phẩm của khoá không.
     *
     * Khoá chưa gắn sản phẩm thì KHÔNG ai có quyền qua đường mua — lối vào duy nhất là mã lớp.
     */
    public function hasActiveRight(User $user, Course $course): bool
    {
        if ($course->product_id === null) {
            return false;
        }

        return $this->accessRights->forUserWithProduct($user->id)
            ->contains(fn (AccessRight $ar) => $ar->product_id === $course->product_id && $ar->isCurrentlyActive());
    }
}

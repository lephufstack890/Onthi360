<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Services\Public\MaterialAssignmentService;
use App\Services\Student\LibraryService;
use App\Services\Teacher\TeacherWorkspaceService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Tài liệu của tôi" (28/8 (2) — "bên giáo viên cũng xem tài liệu giống như học sinh, chỉ
 * khác được xem thêm file hướng dẫn") — teacher.library.index. TÁI DÙNG nguyên
 * App\Services\Student\LibraryService (đã viết cho học sinh trước), chỉ khác đúng 1 điểm:
 * gọi với includeGuide=true nên danh sách tài nguyên có thêm "PDF hướng dẫn" — cùng cách
 * Teacher\MaterialController tái dùng MaterialReadService của học sinh.
 *
 * SỬA 12/10 — mục "Học liệu" theo source mới (TeacherResourcesPanel): thêm nút "Giao tài liệu" (giao quyền đọc có
 * thời hạn cho học sinh của lớp) — gửi về materials.assign như popup ở trang /tai-lieu, nên cần danh sách lớp + học sinh.
 */
class LibraryController extends Controller
{
    public function __construct(
        private LibraryService $library,
        private MaterialAssignmentService $materialAssignments,
        private TeacherWorkspaceService $workspace,
    ) {}

    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'sach');
        $user = $request->user();
        $classes = $this->workspace->classOptions($user);

        return view('teacher.materials.mine', $this->library->indexData($user, $tab, 'teacher.library.index', true) + [
            'canAssign' => $this->materialAssignments->canAssign($user) && $this->materialAssignments->isReady(),
            'assignClasses' => $classes,
            'assignRoster' => $this->workspace->roster(array_column($classes, 'id'), []),
            'accessDays' => MaterialAssignmentService::ACCESS_DAYS,
        ]);
    }
}

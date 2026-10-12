<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ParentLink;
use App\Services\Student\StudentParentLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** student.parents.* — trang "Phụ huynh": xem liên kết, tìm & liên kết, đồng ý/từ chối yêu cầu, hủy liên kết. */
class ParentController extends Controller
{
    public function __construct(private StudentParentLinkService $links) {}

    public function index(Request $request): View
    {
        return view('student.parents.index', $this->links->panel($request->user()) + [
            'relationships' => StudentParentLinkService::RELATIONSHIPS,
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $data = $request->validate(['identifier' => ['required', 'string', 'max:254']]);

        return response()->json(['candidate' => $this->links->find($request->user(), $data['identifier'])]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:254'],
            'relationship' => ['required', 'string', 'max:30'],
        ]);
        $this->links->link($request->user(), $data['identifier'], $data['relationship']);

        return response()->json(['message' => 'Đã liên kết phụ huynh. Phụ huynh có thể theo dõi bài được giao, kết quả và điểm danh của bạn.']);
    }

    public function respond(Request $request, ParentLink $parentLink): JsonResponse
    {
        $data = $request->validate(['accept' => ['required', 'boolean']]);
        $this->links->respond($request->user(), $parentLink, (bool) $data['accept']);

        return response()->json(['message' => $data['accept'] ? 'Đã xác nhận liên kết với phụ huynh.' : 'Đã từ chối yêu cầu liên kết.']);
    }

    public function destroy(Request $request, ParentLink $parentLink): JsonResponse
    {
        $this->links->unlink($request->user(), $parentLink);

        return response()->json(['message' => 'Đã hủy liên kết. Phụ huynh không còn theo dõi dữ liệu học tập của bạn.']);
    }
}

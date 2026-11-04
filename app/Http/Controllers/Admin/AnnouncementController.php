<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemAnnouncement;
use App\Services\Admin\SystemAnnouncementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * SỬA 4/11 (khách: "giáo viên + admin có thể thông báo cho học sinh và phụ huynh") — mục "Thông báo" khu quản trị:
 * soạn, lưu nháp, gửi, thu hồi, ghim trang chủ. Xem App\Services\Admin\SystemAnnouncementService.
 */
class AnnouncementController extends Controller
{
    public function __construct(private readonly SystemAnnouncementService $service) {}

    public function index(Request $request): View
    {
        $rows = $this->service->rows();
        $editId = (int) $request->query('edit', 0);
        $editing = $editId > 0 ? collect($rows)->first(fn ($r) => $r['id'] === $editId && $r['state'] === 'draft') : null;

        return view('admin.announcements.index', [
            'ready' => SystemAnnouncement::isReady(),
            'rows' => $rows,
            'classes' => $this->service->classOptions(),
            'editing' => $editing,
        ]);
    }

    /** Đếm trước số người sẽ nhận (ô "Sẽ gửi tới…" ở form). */
    public function preview(Request $request): JsonResponse
    {
        $audiences = array_values(array_filter(explode(',', (string) $request->query('audiences', ''))));
        $classIds = array_values(array_filter(array_map('intval', explode(',', (string) $request->query('classes', '')))));

        return response()->json($this->service->preview($audiences, (string) $request->query('scope', 'all'), $classIds));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer'],
            'title' => ['nullable', 'string', 'max:150'],
            'body' => ['nullable', 'string', 'max:5000'],
            'level' => ['nullable', 'in:normal,important'],
            'audiences' => ['nullable', 'array'],
            'audiences.*' => ['in:students,parents,teachers'],
            'scope' => ['required', 'in:all,classes'],
            'class_ids' => ['nullable', 'array'],
            'class_ids.*' => ['integer'],
            'pin_home' => ['nullable', 'boolean'],
            'pin_until' => ['nullable', 'date'],
            'action' => ['required', 'in:draft,publish'],
        ], [
            'title.max' => 'Tiêu đề tối đa 150 ký tự.',
            'body.max' => 'Nội dung tối đa 5000 ký tự.',
            'pin_until.date' => 'Ngày bỏ ghim không hợp lệ.',
        ]);

        $publish = $data['action'] === 'publish';

        try {
            $a = $this->service->save(Auth::user(), $data, $publish);
        } catch (ValidationException $e) {
            return redirect()->route('admin.announcements.index', ! empty($data['id']) ? ['edit' => $data['id']] : [])
                ->withErrors($e->errors())->withInput();
        }

        return redirect()->route('admin.announcements.index')->with('ann_status', $publish
            ? 'Đã gửi thông báo tới '.number_format($a->student_count + $a->parent_count + $a->teacher_count, 0, ',', '.').' lượt người nhận.'
            : 'Đã lưu bản nháp.');
    }

    public function destroy(int $announcement): RedirectResponse
    {
        $ok = $this->service->deleteDraft($announcement);

        return redirect()->route('admin.announcements.index')->with('ann_status', $ok ? 'Đã xoá bản nháp.' : 'Chỉ xoá được bản nháp.');
    }

    public function recall(int $announcement): RedirectResponse
    {
        $n = $this->service->recall($announcement);

        return redirect()->route('admin.announcements.index')
            ->with('ann_status', 'Đã thu hồi thông báo — gỡ khỏi hộp thư của '.number_format($n, 0, ',', '.').' người nhận.');
    }

    public function pin(int $announcement): RedirectResponse
    {
        $pinned = $this->service->togglePin($announcement);

        return redirect()->route('admin.announcements.index')->with('ann_status', match ($pinned) {
            true => 'Đã ghim lên thanh Thông báo hệ thống ở trang chủ.',
            false => 'Đã bỏ ghim khỏi trang chủ.',
            null => 'Chỉ ghim được thông báo đã gửi.',
        });
    }
}

<?php

namespace App\Services\Student;

use App\Enums\ContentStatus;
use App\Enums\AccessRightStatus;
use App\Enums\AccessScope;
use App\Models\AttemptAnswer;
use App\Models\Material;
use App\Models\Question;
use App\Models\User;
use App\Repositories\Contracts\MaterialRepositoryInterface;
use App\Services\AccessGateService;
use App\Support\AccessDecision;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "Đọc bài" (25/8) — học sinh xem nội dung PDF của 1 Material (bài) thuộc Sách/Chuyên
 * đề/Đề thi đã mua. KHÔNG tự kiểm tra quyền theo kiểu riêng — mọi quyết định "được đọc hay
 * không" đều giao hẳn cho App\Services\AccessGateService::canAccessMaterial() (bộ máy trung
 * tâm DUY NHẤT của cả hệ thống cho câu hỏi này, xem docblock của lớp đó) để không có 2 nơi
 * tính quyền lệch nhau. Việc CỦA lớp này chỉ là: tìm đúng Material, gọi tới AccessGateService,
 * và chuẩn bị dữ liệu hiển thị (điều hướng bài trước/sau, link file, watermark).
 *
 * File PDF luôn nằm ở disk riêng tư 'local' (xem migration add_code_and_pdf_to_materials_table)
 * — streamPdf() chỉ trả file cho MỘT lần gọi đã qua kiểm tra quyền ở Controller, không có URL
 * công khai nào trỏ thẳng vào file.
 */
class MaterialReadService
{
    public function __construct(
        private MaterialRepositoryInterface $materials,
        private AccessGateService $accessGate,
    ) {}

    /** Tìm Material kèm quan hệ product (AccessGateService::canAccessMaterial() cần $material->product). */
    public function findWithProductOrFail(int $id): Material
    {
        $material = $this->materials->findWithProduct($id);
        abort_if($material === null, 404);

        return $material;
    }

    /**
     * Quyết định DUY NHẤT cho câu hỏi "user này đọc được material này không" — Controller gọi
     * hàm này ở CẢ 2 route (trang đọc + route lấy file), không tin route trước đã kiểm tra rồi.
     * Đọc trực tiếp theo sản phẩm (không qua lớp học) nên luôn truyền $classRoom = null.
     */
    public function decisionFor(User $user, Material $material): AccessDecision
    {
        return $this->accessGate->canAccessMaterial($user, $material);
    }

    /**
     * Dữ liệu cho trang đọc: bài hiện tại + điều hướng bài trước/sau TRONG CÙNG sản phẩm (chỉ
     * tính các bài đã phát hành và có sẵn PDF — bài chỉ làm mục lục/chương cha không có gì để
     * đọc thì bỏ qua, không đưa vào điều hướng để tránh bấm Tiếp mà ra trang trống).
     *
     * SỬA 27/8 ("giáo viên xem học liệu đã gắn lớp như nào"): thêm $routePrefix (mặc định
     * 'student' — GIỮ NGUYÊN hành vi cũ cho Student\MaterialController) để trang đọc/route file
     * dùng ĐÚNG group vai trò đang gọi (route('teacher.materials.read', ...) khi
     * App\Http\Controllers\Teacher\MaterialController gọi với 'teacher') — logic quyền/dữ liệu
     * đọc vẫn NGUYÊN VẸN 1 chỗ này, chỉ khác route/layout hiển thị theo vai trò.
     *
     * @return array{material: Material, prev: ?Material, next: ?Material, pdfUrl: string, watermarkText: string, readRoute: string, layoutView: string}
     */
    public function buildReadData(User $user, Material $material, string $routePrefix = 'student'): array
    {
        $siblings = $this->materials->query()
            ->where('product_id', $material->product_id)
            ->where('status', ContentStatus::Published->value)
            ->whereNotNull('pdf_path')
            ->orderBy('order')
            ->get(['id', 'title', 'order']);

        $index = $siblings->search(fn (Material $m) => $m->id === $material->id);
        $prev = $index !== false && $index > 0 ? $siblings->get($index - 1) : null;
        $next = $index !== false && $index < $siblings->count() - 1 ? $siblings->get($index + 1) : null;

        return [
            'material' => $material,
            'prev' => $prev,
            'next' => $next,
            // SỬA 18/9 — cột "Bài tập" bên phải màn đọc (bản mẫu education-main/src/components/
            // MaterialReaderPage.jsx) và 2 viên quyền ở thanh đầu trang.
            'exercises' => $this->exercisesFor($user, $material),
            'access' => $this->accessChip($user, $material),
            'pdfUrl' => route($routePrefix.'.materials.file', $material->id),
            // Đóng dấu mờ lên từng trang khi hiển thị (giảm thiểu chia sẻ ảnh chụp màn hình ra
            // ngoài) — không chặn được tuyệt đối (không phần mềm nào chặn được chụp màn hình),
            // chỉ để TRUY VẾT được nguồn nếu có rò rỉ, khách đã được báo trước điều này.
            'watermarkText' => trim(($user->name ?? '').' · '.($user->email ?? '')),
            'readRoute' => $routePrefix.'.materials.read',
            'layoutView' => 'layouts.'.$routePrefix,
        ];
    }

    /**
     * SỬA 18/9 — BÀI TẬP gắn với tài liệu đang đọc, cho cột bên phải của bản mẫu.
     *
     * Nguồn thật: Question có product_id = đúng sản phẩm chứa bài này và đã phát hành — CÙNG tập
     * bài tập mà trang "Tài liệu của tôi" đang liệt kê (Student\LibraryService::exercisesFor()),
     * nên hai màn không bao giờ lệch nhau.
     *
     * Trạng thái là của CHÍNH người đang đọc, tính từ attempt_answers thật (cùng cách
     * Public\PracticeService::problemRows() đang tính): có câu đúng -> "Đã hoàn thành", có nộp
     * mà chưa đúng -> "Đang làm", chưa nộp -> "Sẵn sàng".
     */
    private function exercisesFor(User $user, Material $material): array
    {
        if ($material->product_id === null) {
            return [];
        }

        $questions = Question::query()
            ->where('product_id', $material->product_id)
            ->where('status', ContentStatus::Published->value)
            ->with(['tags:id,name'])
            ->orderBy('id')
            ->get();

        if ($questions->isEmpty()) {
            return [];
        }

        $mine = AttemptAnswer::query()
            ->selectRaw('question_id, COUNT(*) as mine, SUM(CASE WHEN verdict = ? OR score > 0 THEN 1 ELSE 0 END) as mine_accepted', ['accepted'])
            ->whereIn('question_id', $questions->pluck('id')->all())
            ->whereHas('attempt', fn ($q) => $q->where('user_id', $user->id))
            ->groupBy('question_id')
            ->get()
            ->keyBy('question_id');

        return $questions->map(function (Question $question) use ($mine) {
            $row = $mine->get($question->id);
            $count = (int) ($row->mine ?? 0);
            $accepted = (int) ($row->mine_accepted ?? 0);

            [$statusKey, $statusLabel] = match (true) {
                $accepted > 0 => ['done', 'Đã hoàn thành'],
                $count > 0 => ['progress', 'Đang làm'],
                default => ['open', 'Sẵn sàng'],
            };

            // Độ khó: ưu tiên giá trị quản trị nhập; chưa nhập thì suy từ ĐIỂM của câu — cùng
            // công thức Public\PracticeService đang dùng, không bịa thêm thang riêng.
            $meta = $question->metadata ?? [];
            $level = (int) ($meta['difficulty'] ?? 0);
            if ($level < 1 || $level > 5) {
                $level = max(1, min(5, (int) ceil(($question->points ?: 10) / 20)));
            }

            return [
                'id' => $question->id,
                'title' => $question->title,
                'tags' => $question->tags->pluck('name')->take(3)->values()->all(),
                'points' => (int) $question->points,
                'difficultyLabel' => match (true) {
                    $level <= 2 => 'Cơ bản',
                    $level === 3 => 'Trung bình',
                    default => 'Khó',
                },
                'status' => $statusKey,
                'statusLabel' => $statusLabel,
            ];
        })->values()->all();
    }

    /**
     * SỬA 18/9 — 2 viên ở thanh đầu trang bản mẫu: "Đã sở hữu" và "Còn N ngày".
     *
     * Chỉ dựa trên quyền THẬT còn hiệu lực của người đang đọc với sản phẩm chứa tài liệu này.
     * expires_at = NULL nghĩa là quyền VĨNH VIỄN (xem App\Models\AccessRight::isCurrentlyActive())
     * nên in "Không giới hạn" chứ không tính ra một con số ngày.
     */
    private function accessChip(User $user, Material $material): array
    {
        $right = $user->accessRights()
            ->where('product_id', $material->product_id)
            ->whereIn('scope', [AccessScope::PersonalLearning->value, AccessScope::TeacherTeaching->value])
            ->where('status', AccessRightStatus::Active)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->orderByRaw('expires_at IS NULL DESC')
            ->orderByDesc('expires_at')
            ->first();

        if ($right === null) {
            // Vào được trang này qua cửa khác (lớp học/mở tự do) — không có quyền cá nhân để in.
            return ['owned' => false, 'remainingLabel' => null];
        }

        return [
            'owned' => true,
            'remainingLabel' => $right->expires_at === null
                ? 'Không giới hạn'
                : 'Còn '.max(0, (int) now()->diffInDays($right->expires_at, false)).' ngày',
        ];
    }

    /** Trả file PDF thô cho đúng 1 lần gọi ĐÃ qua decisionFor() ở Controller — không tự kiểm tra lại quyền ở đây. */
    public function streamPdf(Material $material): StreamedResponse
    {
        abort_if(blank($material->pdf_path), 404);

        return Storage::disk('local')->response($material->pdf_path);
    }
}

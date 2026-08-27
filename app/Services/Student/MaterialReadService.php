<?php

namespace App\Services\Student;

use App\Enums\ContentStatus;
use App\Models\Material;
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
            'pdfUrl' => route($routePrefix.'.materials.file', $material->id),
            // Đóng dấu mờ lên từng trang khi hiển thị (giảm thiểu chia sẻ ảnh chụp màn hình ra
            // ngoài) — không chặn được tuyệt đối (không phần mềm nào chặn được chụp màn hình),
            // chỉ để TRUY VẾT được nguồn nếu có rò rỉ, khách đã được báo trước điều này.
            'watermarkText' => trim(($user->name ?? '').' · '.($user->email ?? '')),
            'readRoute' => $routePrefix.'.materials.read',
            'layoutView' => 'layouts.'.$routePrefix,
        ];
    }

    /** Trả file PDF thô cho đúng 1 lần gọi ĐÃ qua decisionFor() ở Controller — không tự kiểm tra lại quyền ở đây. */
    public function streamPdf(Material $material): StreamedResponse
    {
        abort_if(blank($material->pdf_path), 404);

        return Storage::disk('local')->response($material->pdf_path);
    }
}

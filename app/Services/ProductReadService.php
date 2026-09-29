<?php

namespace App\Services;

use App\Enums\ContentStatus;
use App\Models\Material;
use App\Models\Product;
use App\Models\User;
use App\Support\AccessDecision;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * SỬA 29/9 (khách chốt: "bỏ file pdf sách đi, mỗi chương thêm từng file pdf... khi mua xong
 * hoặc giáo viên gắn vào lớp thì từng file pdf sẽ ghép dài để lướt lên lướt xuống đọc") —
 * ĐỌC LIỀN MẠCH CẢ SẢN PHẨM.
 *
 * Khác App\Services\Student\MaterialReadService (mở ĐÚNG 1 Material, chuyển bài bằng nút
 * ‹ Bài trước / Bài sau ›): lớp này dựng DANH SÁCH CÓ THỨ TỰ các tệp PDF của cả sản phẩm để
 * trang đọc nối lại thành một dải cuộn duy nhất. Việc "ghép" nằm ở TRÌNH DUYỆT (pdf.js vẽ
 * lần lượt từng tệp vào cùng một dải) — CỐ Ý không ghép thành 1 tệp PDF trên máy chủ:
 *   · không nhân đôi dung lượng lưu trữ (sách vài trăm MB thì tốn gấp đôi);
 *   · admin thay PDF 1 chương là học sinh thấy ngay, không phải ghép lại cả quyển;
 *   · mỗi tệp vẫn đi qua route có kiểm tra quyền + đóng dấu mờ theo người đọc, không tạo ra
 *     một tệp "cả quyển" dễ bị tải trọn gói ra ngoài.
 *
 * QUYỀN: dùng AccessGateService::canAccessProduct() — KHÔNG dùng canAccessMaterial(). Đây là
 * điểm cốt lõi của yêu cầu "mua xong HOẶC giáo viên gắn vào lớp thì đọc được":
 * canAccessMaterial() chỉ xét quyền CÁ NHÂN nên học sinh được cấp qua lớp sẽ bị chặn, còn
 * canAccessProduct() có đủ 2 cửa (quyền cá nhân HOẶC sản phẩm đang gắn active ở lớp mình là
 * thành viên — xem hasActiveClassGrantedAccess()).
 */
class ProductReadService
{
    public function __construct(private AccessGateService $accessGate) {}

    public function findOrFail(int $productId): Product
    {
        return Product::query()->findOrFail($productId);
    }

    /**
     * Quyết định DUY NHẤT cho câu hỏi "user này đọc được sản phẩm này không" — controller gọi
     * ở CẢ 2 route (trang đọc + route lấy từng tệp), không tin route trước đã kiểm tra rồi.
     */
    public function decisionFor(User $user, Product $product): AccessDecision
    {
        return $this->accessGate->canAccessProduct($user, $product);
    }

    /**
     * Các mảnh PDF của sản phẩm, ĐÚNG THỨ TỰ đọc:
     *   1. lần lượt từng chương/phần/đề (Material type=chapter, theo cột order): PDF CỦA CHÍNH
     *      chương đó trước (ô upload mới ở trang chi tiết sản phẩm), rồi tới các học liệu con
     *      gắn vào chương đó (Material con có PDF — vẫn dùng được như trước, 1 chương nhiều tệp);
     *   2. cuối cùng là học liệu KHÔNG gắn chương nào (dữ liệu cũ, hoặc admin quên gắn) — vẫn
     *      đọc được chứ không biến mất khỏi trang đọc;
     *   3. nếu KHÔNG có mảnh nào ở 2 bước trên mà sản phẩm vẫn còn "File PDF" tổng kiểu cũ thì
     *      dùng chính tệp đó làm 1 mảnh (qua route access.resource, cũng kiểm tra quyền y như
     *      vậy) — sách cũ chưa kịp chia chương vẫn đọc được bình thường, KHÔNG phải chạy lệnh
     *      chuyển dữ liệu nào trước khi lên bản mới.
     *
     * @return array<int, array{key:string, title:string, label:?string, sub:bool, url:string}>
     */
    public function partsFor(Product $product, string $routePrefix = 'student'): array
    {
        $chapters = Material::query()
            ->where('product_id', $product->id)
            ->where('type', 'chapter')
            ->whereNull('parent_id')
            ->orderBy('order')
            ->orderBy('id')
            ->get(['id', 'title', 'pdf_path', 'status']);

        $leaves = Material::query()
            ->where('product_id', $product->id)
            ->where('type', '!=', 'chapter')
            ->where('status', ContentStatus::Published->value)
            ->whereNotNull('pdf_path')
            ->orderBy('order')
            ->orderBy('id')
            ->get(['id', 'title', 'parent_id'])
            ->groupBy('parent_id');

        $chapterWord = $product->chapterLabel() ?: 'Phần';
        $parts = [];
        $index = 0;

        foreach ($chapters as $chapter) {
            $index++;
            $label = $chapterWord.' '.$index;

            if (filled($chapter->pdf_path) && $chapter->status === ContentStatus::Published) {
                $parts[] = $this->part($chapter->id, $chapter->title, $label, false, $routePrefix, $product->id);
            }

            foreach ($leaves->get($chapter->id, collect()) as $leaf) {
                // Học liệu con: chương đã có PDF riêng thì mấy tệp này là phần đọc thêm -> lùi
                // vào trong ở mục lục (sub = true), nhãn chương chỉ in 1 lần cho mảnh đầu.
                $parts[] = $this->part($leaf->id, $leaf->title, $label, true, $routePrefix, $product->id);
            }
        }

        foreach ($leaves->get(null, collect()) as $orphan) {
            $parts[] = $this->part($orphan->id, $orphan->title, null, false, $routePrefix, $product->id);
        }

        if ($parts === [] && filled($product->content_pdf_path)) {
            $parts[] = [
                'key' => 'legacy',
                'title' => 'Toàn bộ nội dung',
                'label' => null,
                'sub' => false,
                'url' => route('access.resource', ['product' => $product->id, 'kind' => 'content']),
            ];
        }

        return $parts;
    }

    /** @return array{key:string, title:string, label:?string, sub:bool, url:string} */
    private function part(int $materialId, string $title, ?string $label, bool $sub, string $routePrefix, int $productId): array
    {
        return [
            'key' => 'm'.$materialId,
            'title' => $title,
            'label' => $label,
            'sub' => $sub,
            'url' => route($routePrefix.'.products.read.file', ['product' => $productId, 'material' => $materialId]),
        ];
    }

    /** Có gì để đọc không — dùng để quyết định hiện nút "Đọc tài liệu" hay câu "chưa có nội dung". */
    public function hasReadableParts(Product $product): bool
    {
        if (filled($product->content_pdf_path)) {
            return true;
        }

        return Material::query()
            ->where('product_id', $product->id)
            ->where('status', ContentStatus::Published->value)
            ->whereNotNull('pdf_path')
            ->exists();
    }

    /**
     * Dữ liệu trang đọc. $routePrefix ('student'|'teacher') quyết định layout + route lấy tệp
     * + nút Quay lại — cùng cơ chế MaterialReadService::buildReadData() đang dùng, để 1 view
     * dùng chung cho 2 vai trò mà không hard-code route của vai trò nào.
     *
     * @return array{product: Product, parts: array, chapterWord: string, watermarkText: string, layoutView: string, libraryRoute: string}
     */
    public function buildReadData(User $user, Product $product, string $routePrefix = 'student'): array
    {
        return [
            'product' => $product,
            'parts' => $this->partsFor($product, $routePrefix),
            'chapterWord' => $product->chapterLabel() ?: 'Phần',
            // Đóng dấu mờ tên + email người đang đọc lên từng trang — y như trang đọc 1 bài
            // (MaterialReadService): không chặn được chụp màn hình, chỉ để TRUY VẾT nguồn rò rỉ.
            'watermarkText' => trim(($user->name ?? '').' · '.($user->email ?? '')),
            'layoutView' => 'layouts.'.$routePrefix,
            'libraryRoute' => $routePrefix.'.library.index',
        ];
    }

    /**
     * Lấy đúng 1 Material để trả tệp PDF: PHẢI thuộc chính sản phẩm đang đọc, đã phát hành và
     * có tệp — chặn việc tự sửa id trên URL để lấy tệp của sản phẩm khác (route chỉ kiểm tra
     * quyền theo $product).
     */
    public function resolvePartOrFail(Product $product, int $materialId): Material
    {
        $material = Material::query()
            ->where('product_id', $product->id)
            ->where('status', ContentStatus::Published->value)
            ->whereNotNull('pdf_path')
            ->find($materialId);

        abort_if($material === null, 404);

        return $material;
    }

    public function streamPdf(Material $material): StreamedResponse
    {
        abort_if(blank($material->pdf_path), 404);

        return Storage::disk('local')->response($material->pdf_path);
    }
}

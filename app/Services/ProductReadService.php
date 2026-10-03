<?php

namespace App\Services;

use App\Enums\AccessRightStatus;
use App\Enums\AccessScope;
use App\Enums\ContentStatus;
use App\Models\AttemptAnswer;
use App\Models\Material;
use App\Models\Product;
use App\Models\Question;
use App\Models\User;
use App\Support\QuestionDifficulty;
use App\Support\QuestionOrder;
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
     * SỬA 3/10 (khách: "đọc tài liệu trong học sinh khi click cũng hiển thị giống ngoài public,
     * 2 cái phải đồng bộ") — ID chương/phần ĐẦU TIÊN đọc được của sản phẩm.
     *
     * Khu học sinh dùng nó để mở ĐÚNG màn đọc mà trang công khai đang mở
     * (MaterialService::readHref cũng chọn đúng kiểu này), thay cho màn đọc sản phẩm cũ.
     *
     * Trả null khi sản phẩm KHÔNG có chương nào mà chỉ có một tệp PDF gắn thẳng
     * (content_pdf_path) — lúc đó không có Material nào để mở, phải giữ màn cũ.
     */
    public function firstReadableMaterialId(Product $product): ?int
    {
        return Material::query()
            ->where('product_id', $product->id)
            ->where('status', ContentStatus::Published->value)
            ->whereNotNull('pdf_path')
            ->orderBy('order')
            ->value('id');
    }

    /**
     * Dữ liệu trang đọc. $routePrefix ('student'|'teacher') quyết định layout + route lấy tệp
     * + nút Quay lại — cùng cơ chế MaterialReadService::buildReadData() đang dùng, để 1 view
     * dùng chung cho 2 vai trò mà không hard-code route của vai trò nào.
     *
     * SỬA 29/9 (2) (khách: "chưa thấy chỗ làm bài tập với học liệu") — trả thêm 3 khối cho cột
     * bên phải: BÀI TẬP của sản phẩm (kèm trạng thái làm bài của chính người đang đọc), HỌC LIỆU
     * đính kèm (audio/ảnh của từng chương + tệp gắn thẳng sản phẩm), và viên quyền sở hữu.
     *
     * @return array{product: Product, parts: array, exercises: array, attachments: array, access: array, chapterWord: string, watermarkText: string, layoutView: string, libraryRoute: string, isTeacherView: bool}
     */
    public function buildReadData(User $user, Product $product, string $routePrefix = 'student'): array
    {
        $isTeacherView = $routePrefix === 'teacher';

        return [
            'product' => $product,
            'parts' => $this->partsFor($product, $routePrefix),
            'exercises' => $this->exercisesFor($user, $product),
            'attachments' => $this->attachmentsFor($product, $routePrefix, $isTeacherView),
            'access' => $this->accessChip($user, $product),
            'chapterWord' => $product->chapterLabel() ?: 'Phần',
            // Đóng dấu mờ tên + email người đang đọc lên từng trang — y như trang đọc 1 bài
            // (MaterialReadService): không chặn được chụp màn hình, chỉ để TRUY VẾT nguồn rò rỉ.
            'watermarkText' => trim(($user->name ?? '').' · '.($user->email ?? '')),
            // SỬA 3/10 — khung riêng toàn màn hình, y màn đọc một bài: bản mẫu không có thanh
            // bên, mà để thanh bên vào thì trang có hai bộ điều hướng chồng nhau.
            'layoutView' => 'layouts.reader',
            'libraryRoute' => $routePrefix.'.library.index',
            'isTeacherView' => $isTeacherView,
        ];
    }

    /**
     * BÀI TẬP của sản phẩm đang đọc + trạng thái làm bài CỦA CHÍNH người đang đọc.
     *
     * Chép đúng cách tính của Student\MaterialReadService::exercisesFor() (trang đọc 1 bài) để
     * 2 màn không bao giờ lệch nhau: nguồn là Question có product_id = sản phẩm này và đã phát
     * hành — cùng tập bài mà "Tài liệu của tôi" đang liệt kê; trạng thái tính từ attempt_answers
     * thật (có câu đúng -> Đã hoàn thành, có nộp mà chưa đúng -> Đang làm, chưa nộp -> Sẵn sàng).
     *
     * @return array<int, array{id:int,productId:int,title:string,tags:array,points:int,difficultyLabel:string,difficultyStars:int,status:string,statusLabel:string}>
     */
    private function exercisesFor(User $user, Product $product): array
    {
        // SỬA 30/9 (6) — xếp theo ĐÚNG thứ tự hiển thị chuẩn (gom theo dạng bài, trong dạng thì
        // theo thứ tự ưu tiên hiển thị), thay cho xếp theo id như trước. Dùng chung một hàm với
        // hai nút "Bài trước / Bài tiếp theo" ở màn làm bài, nếu không thì bấm "Bài tiếp theo"
        // sẽ nhảy sang một bài không nằm ngay dưới trong danh sách này.
        $questions = QuestionOrder::apply(
            Question::query()
                ->where('product_id', $product->id)
                ->where('status', ContentStatus::Published->value)
                ->with(['tags:id,name'])
        )->get();

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

        return $questions->map(function (Question $question) use ($mine, $product) {
            $row = $mine->get($question->id);
            $count = (int) ($row->mine ?? 0);
            $accepted = (int) ($row->mine_accepted ?? 0);

            [$statusKey, $statusLabel] = match (true) {
                $accepted > 0 => ['done', 'Đã hoàn thành'],
                $count > 0 => ['progress', 'Đang làm'],
                default => ['open', 'Sẵn sàng'],
            };


            return [
                'id' => $question->id,
                // SỬA 3/10 — 2 khoá THÊM cho khung Bài tập dùng chung với màn đọc tài liệu
                // (partials/reader-exercise-panel): id sản phẩm để dựng link "Xem đề" của giáo
                // viên, và số sao độ khó lấy từ QuestionDifficulty — CÙNG nguồn với nhãn chữ
                // ngay dưới, không tự quy đổi lại từ điểm.
                'productId' => $product->id,
                'title' => $question->title,
                'tags' => $question->tags->pluck('name')->take(3)->values()->all(),
                'points' => (int) $question->points,
                // SỬA 30/9 — nhãn độ khó lấy từ App\Support\QuestionDifficulty (nơi DUY NHẤT
                // định nghĩa 5 mức), thay cho 3 nhãn tự tính ở đây vốn lệch với kho và trang
                // Luyện tập (mức 1-2 sao đều ra "Cơ bản", 4-5 sao đều ra "Khó").
                'difficultyLabel' => QuestionDifficulty::label(
                    QuestionDifficulty::resolve($question->metadata, (int) $question->points)
                ),
                // SỬA 3/10 — số sao cho thẻ bài tập kiểu mới; cùng nguồn với nhãn chữ ngay trên.
                'difficultyStars' => QuestionDifficulty::stars(
                    QuestionDifficulty::resolve($question->metadata, (int) $question->points)
                ),
                'status' => $statusKey,
                'statusLabel' => $statusLabel,
            ];
        })->values()->all();
    }

    /**
     * HỌC LIỆU đính kèm — 2 nguồn, gộp về 1 danh sách cho cột bên phải:
     *   · audio/ảnh của từng học liệu trong sản phẩm (Material::audio_path/image_path, tải ở
     *     trang "Thêm học liệu") — phát/xem TRỰC TIẾP trong trang đọc, qua route asset có kiểm
     *     tra quyền, vì đây là tệp riêng tư ở disk 'local';
     *   · tệp gắn thẳng vào sản phẩm: ZIP bài tập, học liệu media, và PDF hướng dẫn (CHỈ giáo
     *     viên — luật thật nằm ở AccessService::downloadResource(), đây chỉ là chỗ hiển thị).
     *
     * @return array<int, array{kind:string,title:string,chapterTitle:?string,url:string}>
     */
    /**
     * SỬA 3/10 — đổi từ private sang public để MÀN ĐỌC TÀI LIỆU dùng lại (khách: "đọc tài liệu
     * trong học sinh khi click cũng hiển thị giống ngoài public, 2 cái phải đồng bộ"). Chép
     * lại lần hai là có ngày hai bên liệt kê khác nhau.
     */
    public function attachmentsFor(Product $product, string $routePrefix, bool $isTeacherView): array
    {
        $items = [];

        $medias = Material::query()
            ->where('product_id', $product->id)
            ->where('status', ContentStatus::Published->value)
            ->where(fn ($q) => $q->whereNotNull('audio_path')->orWhereNotNull('image_path'))
            ->with('parent:id,title')
            ->orderBy('order')
            ->orderBy('id')
            ->get();

        foreach ($medias as $media) {
            foreach (['audio', 'image'] as $kind) {
                if (blank($media->{$kind.'_path'})) {
                    continue;
                }

                $items[] = [
                    'kind' => $kind,
                    'title' => $media->title,
                    'chapterTitle' => $media->parent?->title,
                    'url' => route($routePrefix.'.products.read.asset', [
                        'product' => $product->id,
                        'material' => $media->id,
                        'kind' => $kind,
                    ]),
                ];
            }
        }

        $productFiles = [
            ['exercise', 'ZIP bài tập', filled($product->exercise_zip_path)],
            ['media', 'Học liệu (ảnh động/audio)', filled($product->media_path)],
            ['guide', 'PDF hướng dẫn (giáo viên)', $isTeacherView && filled($product->guide_pdf_path)],
        ];

        foreach ($productFiles as [$kind, $label, $present]) {
            if (! $present) {
                continue;
            }

            $items[] = [
                'kind' => 'file',
                'title' => $label,
                'chapterTitle' => null,
                'url' => route('access.resource', ['product' => $product->id, 'kind' => $kind]),
            ];
        }

        return $items;
    }

    /**
     * Viên "Đã sở hữu / còn N ngày" ở thanh đầu trang — chép cách tính của
     * MaterialReadService::accessChip(). expires_at = NULL là quyền vĩnh viễn nên in "Không
     * giới hạn" chứ không bịa ra một con số ngày. Vào được đây qua lớp học (không có quyền cá
     * nhân) thì trả owned = false, thanh đầu trang tự in "Cấp qua lớp học".
     *
     * @return array{owned:bool, remainingLabel:?string}
     */
    private function accessChip(User $user, Product $product): array
    {
        $right = $user->accessRights()
            ->where('product_id', $product->id)
            ->whereIn('scope', [AccessScope::PersonalLearning->value, AccessScope::TeacherTeaching->value])
            ->where('status', AccessRightStatus::Active)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->orderByRaw('expires_at IS NULL DESC')
            ->orderByDesc('expires_at')
            ->first();

        if ($right === null) {
            return ['owned' => false, 'remainingLabel' => null];
        }

        return [
            'owned' => true,
            'remainingLabel' => $right->expires_at === null
                ? 'Không giới hạn'
                : 'Còn '.max(0, (int) now()->diffInDays($right->expires_at, false)).' ngày',
        ];
    }

    /**
     * Trả tệp audio/ảnh của 1 học liệu cho trang đọc. Cùng luật với streamPdf(): tệp nằm ở disk
     * riêng tư 'local', chỉ ra khỏi máy chủ sau khi controller đã kiểm tra quyền sản phẩm.
     */
    public function streamAsset(Material $material, string $kind): StreamedResponse
    {
        $path = $kind === 'audio' ? $material->audio_path : $material->image_path;
        abort_if(blank($path), 404);

        return Storage::disk('local')->response($path);
    }

    /**
     * Học liệu có tệp audio/ảnh thuộc đúng sản phẩm này — KHÁC resolvePartOrFail() (đòi có PDF).
     */
    public function resolveAssetOrFail(Product $product, int $materialId, string $kind): Material
    {
        $column = $kind === 'audio' ? 'audio_path' : 'image_path';

        $material = Material::query()
            ->where('product_id', $product->id)
            ->where('status', ContentStatus::Published->value)
            ->whereNotNull($column)
            ->find($materialId);

        abort_if($material === null, 404);

        return $material;
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

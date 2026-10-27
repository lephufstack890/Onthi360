<?php

namespace App\Services\Admin;

use App\Enums\ContentStatus;
use App\Models\Product;
use App\Models\Question;
use App\Models\User;
use App\Support\QuestionZipPackage;
use App\Support\UniqueCodeFromFilename;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ZipArchive;

/**
 * SỬA 27/10 (khách: "up bài tập cho tài liệu hàng loạt — 1 file ZIP chứa 96 ZIP bài, up từng cái cũng đuối; up hàng loạt hay
 * từng file đều được. Bài đã có thì báo không ghi đè được, bắt xoá file ZIP đó ra rồi up lại để tránh trùng").
 *
 * HAI BƯỚC, để không bị quá giờ máy chủ khi gói lớn (mỗi bài còn trích chữ từ PDF đề):
 *   1. prepare(): nhận nhiều ZIP bài và/hoặc ZIP chứa nhiều ZIP bài (như Chuong-01.zip) → bung ra thư mục tạm, đọc &
 *      kiểm từng gói (QuestionZipPackage::parse — cùng luật nhập 1 bài), kiểm TRÙNG MÃ: mã bài = tên file ZIP (giống nhập
 *      từng bài). Có bất kỳ bài trùng (đã có trong hệ thống hoặc lặp trong chính lần tải) / gói lỗi → KHÔNG nhập bài nào,
 *      trả danh sách file cần xoá khỏi gói rồi tải lại. Không có gì sai → trả token + danh sách bài.
 *   2. importOne(): trình duyệt gọi lần lượt từng bài (thanh tiến độ). Mỗi bài tạo bằng ĐÚNG hàm nhập 1 bài
 *      (ContentService::productExerciseStoreFromZipPackage) rồi chuyển Phát hành ngay (nhập hàng loạt không có màn rà
 *      từng bài; vẫn bấm Sửa từng bài như thường). finish() dọn thư mục tạm.
 */
final class ProductExerciseBulkImportService
{
    /** Tối đa số bài một lần nhập. */
    public const MAX_ITEMS = 300;

    private const DIR = 'exercise-bulk';

    public function __construct(private readonly ContentService $content) {}

    /**
     * @param  array<int, UploadedFile>  $files
     * @return array{ok: bool, token?: string, items?: array, duplicates?: array, invalid?: array, total: int}
     */
    public function prepare(Product $product, User $admin, array $files, ?int $chapterId): array
    {
        $this->cleanupOld();

        $token = Str::random(32);
        $dir = self::DIR.'/'.$token;
        $disk = Storage::disk('local');
        $disk->makeDirectory($dir);
        $disk->put($dir.'/.started', (string) time()); // để cleanupOld() không dọn nhầm lần tải đang chạy

        $invalid = [];
        $raw = []; // [file, from, bytes|null, src|null]
        foreach ($files as $file) {
            $name = basename((string) $file->getClientOriginalName());
            $zip = new ZipArchive();
            if ($zip->open((string) $file->getRealPath()) !== true) {
                $invalid[] = ['file' => $name, 'message' => 'Không mở được tệp ZIP.'];

                continue;
            }
            if ($zip->locateName('question.json') !== false) {
                $zip->close();
                $raw[] = ['file' => $name, 'from' => null, 'src' => (string) $file->getRealPath()];

                continue;
            }
            // Gói chứa nhiều ZIP bài: lấy mọi *.zip bên trong (bỏ tệp mô tả / thư mục rác của macOS).
            $found = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = (string) $zip->getNameIndex($i);
                $base = basename($entry);
                if (! str_ends_with(strtolower($entry), '.zip') || str_contains($entry, '__MACOSX/') || str_starts_with($base, '.')) {
                    continue;
                }
                $bytes = $zip->getFromIndex($i);
                if ($bytes === false) {
                    $invalid[] = ['file' => $base, 'message' => 'Không đọc được trong gói '.$name.'.'];

                    continue;
                }
                $found++;
                $raw[] = ['file' => $base, 'from' => $name, 'bytes' => $bytes];
            }
            $zip->close();
            if ($found === 0) {
                $invalid[] = ['file' => $name, 'message' => 'Không có question.json ở gốc và cũng không chứa ZIP bài nào bên trong.'];
            }
        }

        if (count($raw) > self::MAX_ITEMS) {
            $disk->deleteDirectory($dir);
            throw ValidationException::withMessages(['files' => 'Một lần nhập tối đa '.self::MAX_ITEMS.' bài (đang có '.count($raw).'). Chia nhỏ gói rồi nhập nhiều lần.']);
        }

        // Lưu tạm từng gói + đọc / kiểm định dạng (cùng luật nhập 1 bài).
        $items = [];
        foreach ($raw as $k => $r) {
            $path = $dir.'/'.sprintf('%03d', $k).'.zip';
            isset($r['bytes']) ? $disk->put($path, $r['bytes']) : $disk->put($path, (string) file_get_contents($r['src']));
            try {
                $package = QuestionZipPackage::parse($this->asUpload($disk->path($path), $r['file']));
            } catch (ValidationException $e) {
                $invalid[] = ['file' => $r['file'], 'from' => $r['from'], 'message' => (string) collect($e->errors())->flatten()->first()];

                continue;
            }
            $content = $package['json']['content'] ?? [];
            $items[] = [
                'file' => $r['file'],
                'from' => $r['from'],
                'path' => $path,
                'code' => UniqueCodeFromFilename::base($r['file']),
                'title' => (string) ($content['title'] ?? $r['file']),
                'typeLabel' => self::TYPE_LABELS[(string) ($content['type'] ?? '')] ?? (string) ($content['type'] ?? ''),
                'tests' => count($package['testCases'] ?? []),
            ];
        }

        // Trùng mã: lặp trong chính lần tải này, hoặc đã có trong hệ thống (mã bài duy nhất toàn hệ thống).
        $duplicates = [];
        foreach (collect($items)->groupBy(fn ($i) => mb_strtolower($i['code'])) as $group) {
            if ($group->count() > 1) {
                foreach ($group as $i) {
                    $duplicates[] = ['file' => $i['file'], 'from' => $i['from'], 'code' => $i['code'], 'where' => 'Trùng mã với file khác trong chính lần tải này'];
                }
            }
        }
        // withTrashed: cột code là UNIQUE cả với bài đã xoá mềm — bỏ qua là lúc tạo sẽ lỗi trùng khoá.
        $existing = Question::withTrashed()->whereIn('code', collect($items)->pluck('code')->unique()->values()->all() ?: ['__none__'])
            ->with('product:id,title')->get(['id', 'code', 'title', 'product_id', 'deleted_at'])
            ->keyBy(fn ($q) => mb_strtolower((string) $q->code));
        foreach ($items as $i) {
            if (($q = $existing->get(mb_strtolower($i['code']))) !== null) {
                $duplicates[] = ['file' => $i['file'], 'from' => $i['from'], 'code' => $i['code'], 'where' => self::whereLabel($q)];
            }
        }

        if ($duplicates !== [] || $invalid !== [] || $items === []) {
            $disk->deleteDirectory($dir);

            return ['ok' => false, 'total' => count($raw), 'duplicates' => $duplicates, 'invalid' => $invalid];
        }

        $disk->put($dir.'/meta.json', json_encode([
            'product_id' => (int) $product->id,
            'user_id' => (int) $admin->id,
            'chapter_id' => $chapterId,
            'items' => $items,
        ], JSON_UNESCAPED_UNICODE));

        return [
            'ok' => true,
            'token' => $token,
            'total' => count($items),
            'items' => array_map(fn ($i) => array_intersect_key($i, array_flip(['file', 'from', 'code', 'title', 'typeLabel', 'tests'])), $items),
        ];
    }

    /**
     * Nhập bài thứ $index của lần tải $token. Bài vừa có người nhập (bấm 2 lần / 2 tab) thì báo trùng, không tạo bản sao.
     *
     * @return array{code: string, title: string, editUrl: string}
     *
     * @throws ValidationException
     */
    public function importOne(Product $product, User $admin, string $token, int $index): array
    {
        $meta = $this->meta($token);
        abort_if($meta === null || (int) $meta['product_id'] !== (int) $product->id, 404);
        $item = $meta['items'][$index] ?? null;
        abort_if($item === null, 404);

        $exercise = $this->content->productExerciseStoreFromZipPackage(
            $product,
            $admin,
            $this->asUpload(Storage::disk('local')->path($item['path']), $item['file']),
            $meta['chapter_id'] !== null ? (int) $meta['chapter_id'] : null,
        );
        $exercise->update(['status' => ContentStatus::Published->value]);

        return [
            'code' => (string) $exercise->code,
            'title' => (string) $exercise->title,
            'editUrl' => route('admin.products.exercises.edit', [$product->id, $exercise->id]),
        ];
    }

    public function finish(Product $product, string $token): void
    {
        $meta = $this->meta($token);
        if ($meta !== null && (int) $meta['product_id'] === (int) $product->id) {
            Storage::disk('local')->deleteDirectory(self::DIR.'/'.$token);
        }
    }

    /**
     * SỬA 27/10 (khách: "up từng bài thì check luôn, có bài đó rồi thì báo trùng; không trùng mới cho up") — kiểm nhanh theo TÊN
     * file ZIP (mã bài = tên file) ngay khi chọn file, trước khi tải lên. Máy chủ vẫn kiểm lại lúc nhập thật.
     *
     * @return array{code: string, exists: bool, message: ?string}
     */
    public function checkFilename(string $filename): array
    {
        $code = UniqueCodeFromFilename::base(basename($filename));
        $q = Question::withTrashed()->where('code', $code)->with('product:id,title')->first(['id', 'code', 'title', 'product_id', 'deleted_at']);

        return [
            'code' => $code,
            'exists' => $q !== null,
            'message' => $q !== null ? 'Trùng: bài "'.$code.'" — '.self::whereLabel($q).'. Không nhập được (không ghi đè) — xoá bài cũ trước hoặc chọn file khác.' : null,
        ];
    }

    /** "Sách X · Bài 1. Nhập xuất #1" — bài trùng mã đang nằm ở đâu. */
    public static function whereLabel(Question $q): string
    {
        $place = $q->product_id !== null ? ('tài liệu "'.($q->product?->title ?? '#'.$q->product_id).'"') : 'Kho câu hỏi chung';

        return ($q->trashed() ? 'Đã có (bài đã xoá, còn lưu trong hệ thống) ở ' : 'Đã có trong ').$place.' — bài "'.$q->title.'"';
    }

    private const TYPE_LABELS = [
        'programming' => 'Lập trình', 'single_choice' => 'Trắc nghiệm', 'true_false' => 'Đúng / Sai',
        'short_answer' => 'Điền từ', 'composite' => 'Nhiều phần',
    ];

    private function asUpload(string $absolutePath, string $originalName): UploadedFile
    {
        return new UploadedFile($absolutePath, $originalName, 'application/zip', null, true);
    }

    private function meta(string $token): ?array
    {
        if (! preg_match('/^[A-Za-z0-9]{32}$/', $token)) {
            return null;
        }
        $path = self::DIR.'/'.$token.'/meta.json';
        $disk = Storage::disk('local');

        return $disk->exists($path) ? json_decode((string) $disk->get($path), true) : null;
    }

    /** Dọn các lần tải bỏ dở quá 1 ngày. */
    private function cleanupOld(): void
    {
        $disk = Storage::disk('local');
        foreach ($disk->directories(self::DIR) as $d) {
            $stamp = 0;
            foreach (['meta.json', '.started'] as $f) {
                if ($disk->exists($d.'/'.$f)) {
                    $stamp = max($stamp, $disk->lastModified($d.'/'.$f));
                }
            }
            if ($stamp < now()->subDay()->getTimestamp()) {
                $disk->deleteDirectory($d);
            }
        }
    }
}

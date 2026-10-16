<?php

namespace App\Repositories\Eloquent;

use App\Models\Tag;
use App\Repositories\Contracts\TagRepositoryInterface;
use App\Support\PracticeQuestionPool;
use App\Support\TagName;
use Illuminate\Database\Eloquent\Collection;

class TagRepository extends EloquentRepository implements TagRepositoryInterface
{
    protected string $modelClass = Tag::class;

    private const PRACTICE_TYPES = ['mcq', 'fill_blank', 'coding', 'composite'];

    public function allOrderedByName(): Collection
    {
        return $this->query()->orderBy('name')->get();
    }

    public function allWithPracticeQuestions(): Collection
    {
        return $this->query()
            ->whereHas('questions', function ($q) {
                // SỬA 3/10 — cùng luật với danh sách "Bài tập chuyên đề", xem PracticeQuestionPool.
                PracticeQuestionPool::excludeExamQuestions(
                    $q->where('status', 'published')
                        ->whereNull('product_id')
                        ->whereIn('type', self::PRACTICE_TYPES)
                );
            })
            ->orderBy('name')
            ->get();
    }

    public function practiceCountsByType(): array
    {
        // SỬA 3/10 — số trên chip phải đếm ĐÚNG số dòng danh sách bày ra, nếu không chip ghi
        // 42 mà bấm vào chỉ ra 30 dòng. Xem PracticeQuestionPool.
        $rows = PracticeQuestionPool::excludeExamQuestions(
            \Illuminate\Support\Facades\DB::table('question_tag')
                ->join('questions', 'questions.id', '=', 'question_tag.question_id')
                ->join('tags', 'tags.id', '=', 'question_tag.tag_id')
                ->where('questions.status', 'published')
                ->whereNull('questions.product_id')
                ->whereIn('questions.type', self::PRACTICE_TYPES)
                ->whereNull('questions.deleted_at')
        )
            ->groupBy('tags.id', 'tags.name', 'questions.type')
            ->select('tags.id', 'tags.name', 'questions.type', \Illuminate\Support\Facades\DB::raw('COUNT(*) as aggregate'))
            ->orderBy('tags.name')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $id = (int) $row->id;
            $out[$id] ??= ['name' => $row->name, 'counts' => array_fill_keys(self::PRACTICE_TYPES, 0), 'total' => 0];
            $out[$id]['counts'][$row->type] = (int) $row->aggregate;
            $out[$id]['total'] += (int) $row->aggregate;
        }

        return $out;
    }

    public function practiceTotalsByType(): array
    {
        // SỬA 3/10 — như trên, xem PracticeQuestionPool.
        $rows = PracticeQuestionPool::excludeExamQuestions(
            \Illuminate\Support\Facades\DB::table('questions')
                ->where('status', 'published')
                ->whereNull('product_id')
                ->whereNull('deleted_at')
                ->whereIn('type', self::PRACTICE_TYPES)
        )
            ->groupBy('type')
            ->select('type', \Illuminate\Support\Facades\DB::raw('COUNT(*) as aggregate'))
            ->get();

        $out = array_fill_keys(self::PRACTICE_TYPES, 0);
        foreach ($rows as $row) {
            $out[$row->type] = (int) $row->aggregate;
        }

        return $out;
    }

    public function findOrCreateByName(string $name): Tag
    {
        return Tag::firstOrCreate(['name' => trim($name)]);
    }

    /**
     * SỬA 16/10 (khách: "nhập câu hỏi / nhập đề bằng file ZIP thì tag có dấu, ví dụ 'nhập xuất', đừng kiểu 'nhap-xuat' —
     * tương tự toàn bộ trường hợp") — dùng cho MỌI chỗ nhập ZIP (Kho câu hỏi admin + giáo viên, bài tập trong tài liệu,
     * đề Luyện tập nhập ZIP). Mỗi tên: TagName::fromPackage() ("nhap-xuat" -> "nhập xuất"), rồi so theo TagName::key():
     *   - đã có tag cùng khoá CÓ DẤU            -> dùng luôn tag đó (không tạo trùng);
     *   - chỉ có 1 tag cùng khoá KHÔNG dấu cũ   -> đổi tên tag cũ thành bản có dấu (các câu đang gắn tag đó cũng có dấu theo);
     *   - chưa có                                -> tạo mới bằng tên có dấu.
     * So khoá trong PHP (không dựa vào collation DB) để không vướng ràng buộc unique khi collation bỏ qua dấu.
     */
    public function idsFromPackageNames(array $names): array
    {
        $all = Tag::query()->get(['id', 'name']);
        $ids = [];

        foreach ($names as $raw) {
            $name = TagName::fromPackage((string) $raw);
            if ($name === '') {
                continue;
            }

            $key = TagName::key($name);
            $same = $all->filter(fn (Tag $t) => TagName::key($t->name) === $key)->values();

            $tag = $same->first(fn (Tag $t) => mb_strtolower($t->name, 'UTF-8') === mb_strtolower($name, 'UTF-8'))
                ?? $same->first(fn (Tag $t) => TagName::hasMarks($t->name));

            if (! $tag && $same->count() === 1) {
                $tag = $same->first();
                if (TagName::hasMarks($name)) {
                    $tag->forceFill(['name' => mb_substr($name, 0, 120)])->save();
                }
            }

            if (! $tag && $same->isNotEmpty()) {
                $tag = $same->first();
            }

            if (! $tag) {
                $tag = Tag::create(['name' => mb_substr($name, 0, 120)]);
                $all->push($tag);
            }

            $ids[] = $tag->id;
        }

        return array_values(array_unique($ids));
    }
}

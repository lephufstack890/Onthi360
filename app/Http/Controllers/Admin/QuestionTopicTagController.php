<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Question;
use App\Models\Tag;
use App\Services\Public\PracticeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * SỬA 16/11 (khách: "thêm nút sửa tag chuyên đề ngay trên từng bài ở trang Luyện tập, chỉ admin thấy; bấm thì hiện popup
 * để cập nhật tag và chỉ định TAG CHÍNH — tag chính hiện to, tag phụ nhỏ bên dưới").
 * POST admin/content/questions/{question}/topic-tags (quyền: Ngân hàng nội dung › Sửa).
 */
class QuestionTopicTagController extends Controller
{
    public function update(Request $request, Question $question): JsonResponse
    {
        $data = $request->validate([
            'tag_ids' => ['present', 'array', 'max:20'],
            'tag_ids.*' => ['integer', 'distinct', 'exists:tags,id'],
            'primary_id' => ['nullable', 'integer'],
        ], ['tag_ids.max' => 'Mỗi bài gắn tối đa 20 chuyên đề.'], ['tag_ids' => 'chuyên đề', 'primary_id' => 'tag chính']);

        $ids = array_values(array_unique(array_map('intval', $data['tag_ids'])));
        $primary = isset($data['primary_id']) && in_array((int) $data['primary_id'], $ids, true) ? (int) $data['primary_id'] : ($ids[0] ?? null);
        $before = $question->tags()->pluck('tags.id')->all();

        DB::transaction(function () use ($question, $ids, $primary) {
            if (Question::tagPrimaryReady()) {
                $question->tags()->sync(collect($ids)->mapWithKeys(fn ($id) => [$id => ['is_primary' => $id === $primary]])->all());
            } else {
                $question->tags()->sync($ids);
            }
        });

        PracticeService::forgetProblemCache();

        try {
            AuditLog::create([
                'actor_id' => Auth::id(),
                'action' => 'question_tags_updated',
                'auditable_type' => Question::class,
                'auditable_id' => $question->id,
                'changes' => ['before' => ['tag_ids' => $before], 'after' => ['tag_ids' => $ids, 'primary_id' => $primary]],
            ]);
        } catch (\Throwable $e) {
            report($e);
        }

        $tags = Tag::query()->whereIn('id', $ids)->get(['id', 'name'])->keyBy('id');
        $ordered = collect($primary ? [$primary] : [])->merge(array_diff($ids, [$primary]))
            ->map(fn ($id) => $tags->get($id))->filter()
            ->map(fn ($t) => ['id' => (int) $t->id, 'name' => (string) $t->name])->values();

        return response()->json([
            'ok' => true,
            'message' => $ids === [] ? 'Đã gỡ hết chuyên đề của bài.' : 'Đã lưu '.count($ids).' chuyên đề, tag chính: '.($ordered->first()['name'] ?? '—').'.',
            'tags' => $ordered,
        ]);
    }
}

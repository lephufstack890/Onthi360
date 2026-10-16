<?php

use App\Support\TagName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * SỬA 16/10 (khách: "tag có dấu, ví dụ 'nhập xuất', đừng kiểu 'nhap-xuat' — tương tự toàn bộ trường hợp") — chuyển các tag
 * kiểu slug đã nhập từ gói ZIP trước đây sang tên có dấu (App\Support\TagName::fromPackage): "nhap-xuat" -> "nhập xuất",
 * "vong-lap" -> "vòng lặp"… Tag nào cùng khoá (không dấu / hoa thường / gạch nối) với 1 tag khác thì GỘP: câu hỏi của tag
 * thừa chuyển sang tag giữ lại rồi xoá tag thừa. Tag đã có dấu giữ nguyên tên.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tags = DB::table('tags')->orderBy('id')->get(['id', 'name']);

        $groups = [];
        foreach ($tags as $t) {
            $target = TagName::hasMarks($t->name) ? trim($t->name) : TagName::fromPackage($t->name);
            if ($target === '') {
                continue;
            }
            $groups[TagName::key($target)][] = ['id' => $t->id, 'name' => $t->name, 'target' => $target];
        }

        DB::transaction(function () use ($groups) {
            foreach ($groups as $rows) {
                // Giữ tag đã có dấu sẵn (id nhỏ nhất); không có thì giữ tag đầu tiên.
                $keep = collect($rows)->first(fn ($r) => TagName::hasMarks($r['name'])) ?? $rows[0];
                $finalName = mb_substr($keep['target'], 0, 120);

                foreach ($rows as $r) {
                    if ($r['id'] === $keep['id']) {
                        continue;
                    }
                    $qids = DB::table('question_tag')->where('tag_id', $r['id'])->pluck('question_id');
                    foreach ($qids as $qid) {
                        DB::table('question_tag')->insertOrIgnore(['question_id' => $qid, 'tag_id' => $keep['id']]);
                    }
                    DB::table('question_tag')->where('tag_id', $r['id'])->delete();
                    DB::table('tags')->where('id', $r['id'])->delete();
                }

                if ($finalName !== $keep['name']) {
                    DB::table('tags')->where('id', $keep['id'])->update(['name' => $finalName, 'updated_at' => now()]);
                }
            }
        });
    }

    public function down(): void
    {
        // Không hoàn tác: tên có dấu là dữ liệu đúng, tag đã gộp không tách lại được.
    }
};

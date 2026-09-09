<?php

namespace App\Repositories\Eloquent;

use App\Models\Tag;
use App\Repositories\Contracts\TagRepositoryInterface;
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
                $q->where('status', 'published')
                    ->whereNull('product_id')
                    ->whereIn('type', self::PRACTICE_TYPES);
            })
            ->orderBy('name')
            ->get();
    }

    public function practiceCountsByType(): array
    {
        $rows = \Illuminate\Support\Facades\DB::table('question_tag')
            ->join('questions', 'questions.id', '=', 'question_tag.question_id')
            ->join('tags', 'tags.id', '=', 'question_tag.tag_id')
            ->where('questions.status', 'published')
            ->whereNull('questions.product_id')
            ->whereIn('questions.type', self::PRACTICE_TYPES)
            ->whereNull('questions.deleted_at')
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
        $rows = \Illuminate\Support\Facades\DB::table('questions')
            ->where('status', 'published')
            ->whereNull('product_id')
            ->whereNull('deleted_at')
            ->whereIn('type', self::PRACTICE_TYPES)
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
}

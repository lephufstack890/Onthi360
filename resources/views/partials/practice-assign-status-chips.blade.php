{{-- SỬA 7/10 — dãy chip lọc trạng thái của "Bài/Đề được giao" (Tất cả / Hoàn thành / Chưa hoàn
     thành / Chờ chấm), đúng assignmentStatusFilters của bản mẫu. Tham số: $kind = 'problem'|'exam'. --}}
@php
    $isExamKind = $kind === 'exam';
    $scopeVar = $isExamKind ? 'examScope' : 'problemScope';
    $statusVar = $isExamKind ? 'examAssignStatus' : 'problemAssignStatus';
    $countsVar = $isExamKind ? 'examAssignCounts' : 'problemAssignCounts';
    $pageVar = $isExamKind ? 'examPageIndex' : 'problemPageIndex';
    $statusFilters = [
        ['id' => 'all', 'label' => 'Tất cả'],
        ['id' => 'completed', 'label' => 'Hoàn thành'],
        ['id' => 'incomplete', 'label' => 'Chưa hoàn thành'],
        ['id' => 'pending', 'label' => 'Chờ chấm'],
    ];
@endphp
<div role="group" aria-label="Lọc trạng thái {{ $isExamKind ? 'đề' : 'bài tập' }} được giao" class="oi-asg-filters"
     x-show="{{ $scopeVar }} === 'assigned'" x-cloak>
    <span class="oi-asg-filter-label"><x-lucide name="filter" class="h-3.5 w-3.5" />Trạng thái:</span>
    @foreach ($statusFilters as $f)
        <button type="button" class="oi-asg-chip {{ $f['id'] === 'completed' ? 'is-done' : '' }}"
                :aria-pressed="{{ $statusVar }} === '{{ $f['id'] }}'"
                @click="{{ $statusVar }} = '{{ $f['id'] }}'; {{ $pageVar }} = 1">
            {{ $f['label'] }}<b x-text="{{ $countsVar }}['{{ $f['id'] }}'] || 0"></b>
        </button>
    @endforeach
</div>

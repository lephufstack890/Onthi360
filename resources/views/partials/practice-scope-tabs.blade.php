{{-- ═══════════ THANH PHẠM VI: Tất cả / Được giao / Đã giao ═══════════
     SỬA 7/10 — dựng theo education-main/PracticePage.jsx (scopeTabs). Dùng trong phạm vi Alpine
     của onthiPracticePage. Tham số: $kind = 'problem' | 'exam'.

     Khách vãng lai chỉ thấy "Tất cả …"; học sinh thêm "… được giao"; giáo viên/admin thêm "… đã
     giao" (danh sách theo dõi học sinh). Quyền do Public\PracticeAssignmentService::scopeFor(). --}}
@php
    $isExamKind = $kind === 'exam';
    $scopeVar = $isExamKind ? 'examScope' : 'problemScope';
@endphp
<div role="group" aria-label="{{ $isExamKind ? 'Danh mục đề thi' : 'Danh mục bài tập' }}" class="oi-scope-tabs">
    <button type="button" class="oi-scope-tab is-all" :aria-pressed="{{ $scopeVar }} === 'all'"
            @click="setScope('{{ $kind }}', 'all')">{{ $isExamKind ? 'Tất cả đề' : 'Tất cả bài tập' }}</button>
    @if ($assignScope['canViewAssigned'])
        <button type="button" class="oi-scope-tab" :aria-pressed="{{ $scopeVar }} === 'assigned'"
                @click="setScope('{{ $kind }}', 'assigned')">{{ $isExamKind ? 'Đề được giao' : 'Bài được giao' }}</button>
    @endif
    @if ($assignScope['canManage'])
        <button type="button" class="oi-scope-tab" :aria-pressed="{{ $scopeVar }} === 'managed'"
                @click="setScope('{{ $kind }}', 'managed')">{{ $isExamKind ? 'Đề đã giao' : 'Bài đã giao' }}</button>
    @endif
</div>

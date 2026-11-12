{{-- SỬA 12/11 — dải tab của Quản trị › Bảng xếp hạng. --}}
<div class="flex flex-wrap items-center justify-between gap-3">
    <x-ws.tabs :tabs="[
        ['label' => 'Danh sách bảng xếp hạng', 'href' => route('admin.ranking.index'), 'active' => $rankingTab === 'boards'],
        ['label' => 'Tài khoản vi phạm', 'href' => route('admin.ranking.violations.index'), 'active' => $rankingTab === 'violations', 'count' => $violationCount],
    ]" />
    <a href="{{ route('leaderboard.index', ['scope' => $rankingTab === 'violations' ? 'violations' : 'all-time']) }}" target="_blank" rel="noopener"
       class="inline-flex items-center gap-1.5 text-xs font-bold text-blue-600 hover:underline">
        <x-lucide name="external-link" class="h-3.5 w-3.5" /> Xem trang công khai
    </a>
</div>

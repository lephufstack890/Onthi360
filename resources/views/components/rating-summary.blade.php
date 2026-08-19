@props(['average' => null, 'count' => 0, 'verified' => true])
<div role="img" aria-label="Đánh giá {{ $average ? number_format($average, 1) : 0 }} trên 5 sao, {{ $count }} lượt đánh giá">
    @if ($count < 5)
        <span class="text-sm text-slate-400">Chưa đủ đánh giá để xếp hạng</span>
    @else
        <span class="text-amber-500 font-semibold">★ {{ number_format($average, 1) }}</span>
        <span class="text-sm text-slate-500">· {{ $count }} đánh giá{{ $verified ? ' đã xác thực' : '' }}</span>
    @endif
</div>

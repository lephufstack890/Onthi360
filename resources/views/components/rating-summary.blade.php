{{--
  Rating summary dùng chung (9.5): dưới 5 review công bố phải hiện
  "Chưa đủ đánh giá để xếp hạng"; từ 5 review hiện điểm TB 1 số lẻ + số
  review + nhãn "đã xác thực". Có nhãn text cho screen reader (13.3),
  không chỉ dựa vào icon sao.
--}}
@props(['average' => null, 'count' => 0, 'verified' => true])
<div role="img" aria-label="Đánh giá {{ $average ? number_format($average, 1) : 0 }} trên 5 sao, {{ $count }} lượt đánh giá">
    @if ($count < 5)
        <span class="text-sm text-slate-400">Chưa đủ đánh giá để xếp hạng</span>
    @else
        <span class="text-amber-500 font-semibold">★ {{ number_format($average, 1) }}</span>
        <span class="text-sm text-slate-500">· {{ $count }} đánh giá{{ $verified ? ' đã xác thực' : '' }}</span>
    @endif
</div>

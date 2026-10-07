{{-- Điểm sao đánh giá của đề — dựng theo education-main/src/components/ExamRating.jsx: 5 ngôi sao
     tô theo phần lẻ (4,3 sao = 4 sao đầy + 30% sao thứ 5), điểm "x,x/5" và "(N đánh giá)".
     Tham số: $rating (float|null), $count (int). Chưa ai chấm -> "Chưa có đánh giá".
     Điểm và số lượt là số THẬT từ bảng assessment_ratings, không phải số minh hoạ. --}}
@php
    $erCount = (int) ($count ?? 0);
    $erRating = isset($rating) && is_numeric($rating) ? (float) $rating : null;
@endphp
@if ($erRating === null || $erCount < 1)
    <span class="oi-muted-note">Chưa có đánh giá</span>
@else
    <span class="oi-rating" role="img" aria-label="Đánh giá {{ number_format($erRating, 1, ',', '') }} trên 5 sao từ {{ $erCount }} lượt đánh giá">
        <span class="oi-rating-stars" aria-hidden="true">
            @for ($i = 0; $i < 5; $i++)
                <span class="oi-rating-star">
                    <x-lucide name="star" class="oi-star is-off" />
                    <span class="oi-rating-fill" style="width: {{ max(0, min(100, ($erRating - $i) * 100)) }}%"><x-lucide name="star" class="oi-star is-on" /></span>
                </span>
            @endfor
        </span>
        <strong>{{ number_format($erRating, 1, ',', '') }}/5</strong>
        <span class="oi-rating-count">({{ $erCount }} đánh giá)</span>
    </span>
@endif

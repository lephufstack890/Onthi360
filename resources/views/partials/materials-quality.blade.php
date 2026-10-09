{{-- Độ khó + đánh giá của một tài liệu, dựng bằng Alpine — dùng ở hộp chi tiết và bảng "Tài liệu được giao / đã giao".
     Dựng theo education-main/src/components/MaterialQuality.jsx. Tham số: $expr = biểu thức Alpine trỏ tới tài liệu
     (vd 'selected' hoặc 'row.item'). Thẻ ở lưới tài liệu vẫn dựng sẵn ở máy chủ bằng practice-difficulty-stars /
     practice-exam-rating để có nội dung ngay cả khi chưa chạy JS. --}}
<div class="mp-quality">
    <div class="mp-quality-row">
        <b>Độ khó</b>
        <span class="oi-muted-note" x-show="!{{ $expr }}.difficultyLevel">Chưa xếp độ khó</span>
        <span class="oi-stars" x-show="{{ $expr }}.difficultyLevel" role="img" :aria-label="'Độ khó ' + {{ $expr }}.difficultyLevel + ' trên 5 sao'">
            <template x-for="i in 5" :key="i">
                <span class="mp-sw" :class="i <= {{ $expr }}.difficultyLevel ? 'is-on' : ''"><x-lucide name="star" /></span>
            </template>
            <span class="mp-q-num" x-text="{{ $expr }}.difficultyLevel + '/5 · ' + difficultyLabels[{{ $expr }}.difficultyLevel - 1]"></span>
        </span>
    </div>
    <div class="mp-quality-row">
        <b>Đánh giá</b>
        <span class="oi-muted-note" x-show="!({{ $expr }}.average && {{ $expr }}.count >= 1)">Chưa có đánh giá</span>
        <span class="mp-q-inline" x-show="{{ $expr }}.average && {{ $expr }}.count >= 1" role="img" :aria-label="'Đánh giá ' + fmtRating({{ $expr }}.average) + ' trên 5 sao từ ' + {{ $expr }}.count + ' lượt đánh giá'">
            <span class="mp-q-stars">
                <template x-for="i in 5" :key="i">
                    <span class="mp-rs"><x-lucide name="star" /><span class="mp-rs-fill" :style="{ width: starFill({{ $expr }}.average, i - 1) + '%' }"><x-lucide name="star" /></span></span>
                </template>
            </span>
            <strong x-text="fmtRating({{ $expr }}.average) + '/5'"></strong>
            <span class="mp-q-count" x-text="'(' + {{ $expr }}.count + ' đánh giá)'"></span>
        </span>
    </div>
</div>

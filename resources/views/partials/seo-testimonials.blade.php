{{-- Dữ liệu có cấu trúc schema.org/Review cho khối "Câu chuyện đồng hành".

     CHỈ khai báo những câu chuyện ĐÃ XÁC MINH (verified_at khác null) — tức là admin đã tự
     cam kết đây là lời của người thật và được phép đăng. Câu chuyện chưa xác minh vẫn hiển
     thị bình thường trên trang, chỉ không gửi cho Google.

     Lý do rạch ròi như vậy: chính sách dữ liệu có cấu trúc của Google cấm khai báo Review cho
     nội dung không phải đánh giá thật của người dùng. Vi phạm có thể bị phạt thủ công toàn bộ
     tên miền, mất sạch thứ hạng — cái giá đắt hơn nhiều so với vài ngôi sao trên kết quả tìm
     kiếm. Nên mặc định là KHÔNG khai báo. --}}
@php
    $seoReviews = collect($testimonials ?? [])->filter(fn ($t) => ($t['verified'] ?? false) === true)->values();
@endphp

@if ($seoReviews->isNotEmpty())
    @php
        $seoReviewGraph = [
            '@context' => 'https://schema.org',
            '@type' => 'EducationalOrganization',
            'name' => 'Ôn Thi 360',
            'url' => route('home'),
            'review' => $seoReviews->map(fn ($t) => array_filter([
                '@type' => 'Review',
                'reviewBody' => $t['quote'],
                'author' => ['@type' => 'Person', 'name' => $t['author']],
                'datePublished' => $t['publishedAt'] ?? null,
                'reviewRating' => $t['rating'] ? [
                    '@type' => 'Rating',
                    'ratingValue' => $t['rating'],
                    'bestRating' => 5,
                    'worstRating' => 1,
                ] : null,
            ]))->all(),
        ];
    @endphp
    <script type="application/ld+json">{!! json_encode($seoReviewGraph, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
@endif

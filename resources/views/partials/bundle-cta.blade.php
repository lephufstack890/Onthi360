{{-- SỬA 18/10 — MODULE BỘ ĐỀ: nút "Xem danh sách đề" ở hộp mua của trang chi tiết tài liệu (public/materials/show) khi tài
     liệu là Bộ đề có đề luyện tập. Tài liệu loại khác / chưa chạy migration -> không hiện gì. Tham số: $product. --}}
@php
    $ctaSvc = app(\App\Services\ExamBundle\ExamBundleService::class);
    $ctaCount = \App\Services\ExamBundle\ExamBundleService::ready() && $ctaSvc->isBundle($product) ? $ctaSvc->items($product)->count() : 0;
@endphp
@if ($ctaCount > 0)
    <a href="{{ route('bundles.show', $product->id) }}"
       style="display:flex;align-items:center;justify-content:center;gap:6px;min-height:44px;margin-top:10px;padding:0 16px;border:1px solid #bfdbfe;border-radius:16px;background:#eff6ff;color:#1d4ed8;font-size:13px;font-weight:800;text-decoration:none">
        <x-lucide name="clipboard-list" class="h-4 w-4" /> Xem danh sách đề ({{ $ctaCount }} đề)
    </a>
@endif

@extends('layouts.admin')

@section('title', 'Câu chuyện đồng hành')
@section('page-title', 'Câu chuyện đồng hành')

@section('content')
{{-- Quản trị khối [HOME-10] "Câu chuyện đồng hành" của trang chủ công khai.
     SỬA 12/9 — trước đây 3 câu chuyện nằm cứng trong welcome.blade.php; giờ Admin tự đăng. --}}
@php
    $testimonials = $testimonials ?? collect();
    $publishedCount = $publishedCount ?? 0;
    $draftCount = $draftCount ?? 0;
    $sampleCount = $sampleCount ?? 0;
    $publishedSampleCount = $publishedSampleCount ?? 0;
    $homeLimit = $homeLimit ?? 3;
@endphp

@if (session('status') === 'testimonial-created')
    @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã thêm câu chuyện mới.'])
@elseif (session('status') === 'testimonial-updated')
    @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã lưu thay đổi.'])
@elseif (session('status') === 'testimonial-toggled')
    @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã đổi trạng thái hiển thị.'])
@elseif (session('status') === 'testimonial-deleted')
    @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã xoá câu chuyện.'])
@endif

<x-ws.page-header title="Câu chuyện đồng hành" icon="heart"
                  subtitle="Khối hiển thị ở trang chủ công khai. Trang chủ lấy {{ $homeLimit }} câu chuyện đang hiển thị, theo đúng thứ tự bên dưới.">
    <x-slot:actions>
        <x-ws.btn :href="route('admin.testimonials.create')" variant="onhero" icon="plus">Thêm câu chuyện</x-ws.btn>
        <x-ws.btn :href="route('home').'#testimonials'" variant="onhero-ghost" icon="eye">Xem ở trang chủ</x-ws.btn>
    </x-slot:actions>
</x-ws.page-header>

<div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
    <x-ws.stat label="Đang hiển thị" :value="$publishedCount" tone="emerald" icon="check-circle-2"
               :hint="$publishedCount === 0
                    ? 'Không có câu nào — khối này ẩn hẳn ở trang chủ'
                    : ($publishedCount > $homeLimit
                        ? 'Trang chủ chỉ lấy '.$homeLimit.' câu đầu tiên'
                        : 'Trang chủ đang lấy đủ '.$publishedCount.' câu')" />
    <x-ws.stat label="Bản nháp" :value="$draftCount" tone="amber" icon="pen-line" hint="Chưa hiện ở trang chủ" />
    <x-ws.stat label="Nội dung mẫu" :value="$sampleCount" :tone="$publishedSampleCount > 0 ? 'rose' : 'violet'" icon="alert-triangle"
               :hint="$publishedSampleCount > 0 ? $publishedSampleCount.' câu đang hiện công khai' : 'Cần thay bằng câu chuyện thật'" />
</div>

{{-- Cảnh báo nội dung mẫu. SỬA 13/9 — tách hai mức: đang hiển thị công khai thì gấp hơn
     nhiều so với còn nằm trong bản nháp. Trước đây chỉ có một câu chữ "đang để ở dạng bản
     nháp" nên khi admin bấm Hiển thị thì câu cảnh báo thành sai sự thật. --}}
@if ($publishedSampleCount > 0)
    <div class="flex items-start gap-3 rounded-3xl border border-rose-200 bg-rose-50 p-4">
        <x-ws.icon-tile icon="alert-triangle" tone="rose" />
        <p class="flex-1 text-[13px] leading-relaxed text-rose-800">
            <strong>{{ $publishedSampleCount }} câu chuyện mẫu đang hiển thị công khai ở trang chủ.</strong>
            Đây là nội dung hệ thống viết sẵn để xem trước bố cục, <strong>không phải lời của người thật</strong>.
            Anh/chị nên sửa lại thành câu chuyện có thật (đã xin phép người kể) hoặc bấm “Ẩn đi”, trước khi
            trang được nhiều người biết đến. Phụ huynh phát hiện lời chứng thực không có thật là mất niềm tin rất khó
            lấy lại. Riêng dữ liệu đánh giá gửi Google thì hệ thống đã chặn sẵn — câu chưa xác minh không bao giờ được khai báo.
        </p>
    </div>
@elseif ($sampleCount > 0)
    <div class="flex items-start gap-3 rounded-3xl border border-amber-100 bg-amber-50 p-4">
        <x-ws.icon-tile icon="alert-triangle" tone="amber" />
        <p class="flex-1 text-[13px] leading-relaxed text-amber-800">
            Có <strong>{{ $sampleCount }} câu chuyện mẫu</strong> do hệ thống tạo sẵn để anh/chị thấy trước bố cục,
            hiện còn nằm ở bản nháp nên chưa ai thấy. Đây <strong>không phải lời của người thật</strong> — hãy sửa
            thành câu chuyện có thật (đã xin phép người kể) rồi mới bấm hiển thị.
        </p>
    </div>
@endif

@if ($testimonials->isEmpty())
    <x-ws.empty-state icon="heart" title="Chưa có câu chuyện nào"
                      description="Thêm câu chuyện đầu tiên để khối này hiện lên ở trang chủ."
                      action-label="Thêm câu chuyện" :action-href="route('admin.testimonials.create')" />
@else
    <x-ws.table :columns="['Thứ tự', 'Câu chuyện', 'Người kể', 'Xác minh', 'Trạng thái', '']" min-width="min-w-[880px]">
        @foreach ($testimonials as $t)
            @php
                $isPublished = $t->isPublished();
                // Đếm riêng trong SỐ ĐANG HIỂN THỊ: bảng này liệt kê cả bản nháp nên không thể
                // dùng số thứ tự dòng để đoán câu nào lọt vào 3 ô của trang chủ.
                if ($isPublished) {
                    $publishedRank = ($publishedRank ?? 0) + 1;
                }
                $onHome = $isPublished && $publishedRank <= $homeLimit;
            @endphp
            <tr>
                <td class="px-4 py-3">
                    <span class="inline-flex items-center gap-1.5">
                        <span class="grid h-7 w-7 place-items-center rounded-lg bg-slate-100 text-[11px] font-black text-slate-600">{{ $t->sort_order }}</span>
                        @if ($onHome)
                            <x-ws.badge tone="brand">Ở trang chủ</x-ws.badge>
                        @endif
                    </span>
                </td>

                <td class="px-4 py-3">
                    <div class="flex min-w-0 items-start gap-3">
                        <img src="{{ $t->bannerUrl() }}" alt="" loading="lazy" decoding="async"
                             class="h-10 w-14 shrink-0 rounded-lg border border-sky-100 object-cover">
                        <p class="line-clamp-2 max-w-md text-[13px] italic text-slate-600">{{ $t->quote }}</p>
                    </div>
                </td>

                <td class="px-4 py-3">
                    <div class="flex min-w-0 items-center gap-2.5">
                        <img src="{{ $t->avatarUrl() }}" alt="" loading="lazy" decoding="async"
                             class="h-8 w-8 shrink-0 rounded-full border border-sky-100 object-cover">
                        <div class="min-w-0">
                            <p class="truncate text-[13px] font-bold text-slate-700">{{ $t->author_name }}</p>
                            <p class="truncate text-[11px] text-slate-400">{{ $t->author_role }}@if ($t->author_org) · {{ $t->author_org }}@endif</p>
                        </div>
                    </div>
                </td>

                <td class="px-4 py-3">
                    @if ($t->isVerified())
                        <x-ws.badge tone="success"><x-lucide name="shield-check" class="h-3 w-3" />Đã xác minh</x-ws.badge>
                    @else
                        <x-ws.badge tone="neutral">Chưa xác minh</x-ws.badge>
                    @endif
                </td>

                <td class="px-4 py-3">
                    @if ($isPublished)
                        <x-ws.badge tone="success">Đang hiển thị</x-ws.badge>
                    @elseif ($t->status === \App\Enums\ContentStatus::Archived)
                        <x-ws.badge tone="neutral">Lưu trữ</x-ws.badge>
                    @else
                        <x-ws.badge tone="warning">Bản nháp</x-ws.badge>
                    @endif
                    @if ($t->is_sample)
                        <x-ws.badge tone="danger" class="mt-1">Nội dung mẫu</x-ws.badge>
                    @endif
                </td>

                <td class="px-4 py-3">
                    <div class="flex items-center justify-end gap-1.5">
                        <form method="POST" action="{{ route('admin.testimonials.toggle', $t->id) }}">
                            @csrf
                            <x-ws.btn type="submit" size="sm" :variant="$isPublished ? 'ghost' : 'success'"
                                      :icon="$isPublished ? 'eye-off' : 'eye'">
                                {{ $isPublished ? 'Ẩn đi' : 'Hiển thị' }}
                            </x-ws.btn>
                        </form>
                        <x-ws.btn :href="route('admin.testimonials.edit', $t->id)" size="sm" variant="soft" icon="pencil">Sửa</x-ws.btn>
                    </div>
                </td>
            </tr>
        @endforeach
    </x-ws.table>
@endif
@endsection

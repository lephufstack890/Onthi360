{{--
  Route: admin.content.show
  Spec: 6.2 (chặn phát hành khi thiếu cấu hình) — hiển thị lỗi rõ theo từng câu.
  TODO controller: truyền $item thật + $publishErrors từ App\Services\QuestionPublishGuard.
--}}
@extends('layouts.admin')

@section('title', 'Chi tiết nội dung')
@section('page-title', 'Chi tiết nội dung')

@section('content')
    {{-- $item, $publishErrors do App\Http\Controllers\Admin\ContentController truyền vào
    (publishErrors rỗng cho tới khi có App\Services\QuestionPublishGuard thật). --}}
    @php
        $item = $item ?? ['id' => 0, 'title' => '', 'status' => ''];
        $publishErrors = $publishErrors ?? [];
    @endphp

    <a href="{{ route('admin.content.index') }}" class="text-sm text-slate-500 mb-4 inline-block">‹ Quay lại Nội dung</a>

    <x-page-header :title="$item['title']" subtitle="Trạng thái hiện tại và điều kiện phát hành." />

    @if (!empty($publishErrors))
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 mb-6">
            <p class="text-sm font-medium text-amber-800 mb-2">Chưa thể phát hành — còn thiếu:</p>
            <ul class="list-disc list-inside text-sm text-amber-700 space-y-1">
                @foreach ($publishErrors as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="bg-white rounded-2xl border border-slate-200 p-5">
        <p class="text-sm text-slate-500">TODO: preview toàn văn đề, danh sách câu, cấu hình chấm — xem trước như học sinh.</p>
    </div>
@endsection

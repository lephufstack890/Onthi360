@extends('layouts.teacher')

@section('title', 'Tạo đề PDF')
@section('page-title', 'Tạo đề PDF')

@section('content')
    @php $types = $types ?? []; @endphp

    <a href="{{ route('teacher.papers.index') }}" class="text-[13px] text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-blue-600">‹ Quay lại Đề PDF của tôi</a>

    <x-ws.page-header title="Tạo đề PDF" subtitle="Tải đề dạng PDF + đáp án trên phiếu trả lời — riêng tư cho tới khi Admin duyệt" />

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-5 sm:p-6">
        <form method="POST" action="{{ route('teacher.papers.store') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-[13px] font-medium text-slate-600 mb-1" for="title">Tên đề</label>
                <input id="title" name="title" type="text" value="{{ old('title') }}" required maxlength="255"
                       class="admin-input" placeholder="VD: Đề thi thử THPT lần 1">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[13px] font-medium text-slate-600 mb-1" for="type">Loại</label>
                    <x-ws.select id="type" name="type" required>
                        @foreach ($types as $value => $label)
                            <option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>
                        @endforeach
                    </x-ws.select>
                </div>
                <div>
                    <label class="block text-[13px] font-medium text-slate-600 mb-1" for="duration_minutes">Thời gian làm bài (phút)</label>
                    <input id="duration_minutes" name="duration_minutes" type="number" min="0" value="{{ old('duration_minutes') }}"
                           class="admin-input">
                </div>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white shadow-sm shadow-blue-200 transition-colors hover:bg-blue-700">Tạo đề — tiếp tục tải PDF</button>
                <a href="{{ route('teacher.papers.index') }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-sky-100 bg-white px-4 py-2 text-xs font-bold text-slate-700 transition-colors hover:border-sky-200 hover:bg-sky-50">Huỷ</a>
            </div>
        </form>
    </div>
@endsection

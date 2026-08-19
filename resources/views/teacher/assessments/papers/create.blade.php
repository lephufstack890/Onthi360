{{--
  Route: teacher.papers.create / .store
  Chỉ tạo khung đề (tên/loại/thời gian) — PDF + đáp án + bài lập trình con hoàn thiện ở màn
  "Quản lý đề PDF" (papers/pdf.blade.php) ngay sau khi lưu (16/8 mục 1.2/5).
--}}
@extends('layouts.teacher')

@section('title', 'Tạo đề PDF')
@section('page-title', 'Tạo đề PDF')

@section('content')
    @php $types = $types ?? []; @endphp

    <a href="{{ route('teacher.papers.index') }}" class="text-sm text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-rose-600">‹ Quay lại Đề PDF của tôi</a>

    <x-page-header title="Tạo đề PDF" subtitle="Tải đề dạng PDF + đáp án trên phiếu trả lời — riêng tư cho tới khi Admin duyệt" />

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <div class="bg-white rounded-2xl border border-slate-200 p-6">
        <form method="POST" action="{{ route('teacher.papers.store') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1" for="title">Tên đề</label>
                <input id="title" name="title" type="text" value="{{ old('title') }}" required maxlength="255"
                       class="w-full rounded-lg border border-slate-200 text-sm p-2.5" placeholder="VD: Đề thi thử THPT lần 1">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="type">Loại</label>
                    <x-select id="type" name="type" required>
                        @foreach ($types as $value => $label)
                            <option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>
                        @endforeach
                    </x-select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="duration_minutes">Thời gian làm bài (phút)</label>
                    <input id="duration_minutes" name="duration_minutes" type="number" min="0" value="{{ old('duration_minutes') }}"
                           class="w-full rounded-lg border border-slate-200 text-sm p-2.5">
                </div>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit" class="px-5 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium shadow-sm hover:bg-rose-700 transition">Tạo đề — tiếp tục tải PDF</button>
                <a href="{{ route('teacher.papers.index') }}" class="px-5 py-2.5 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium hover:border-rose-200 hover:text-rose-600 transition">Huỷ</a>
            </div>
        </form>
    </div>
@endsection

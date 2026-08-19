{{--
  Route: admin.content.assessments.create / .store
  Spec: 6.1/6.3 (đề/bộ bài). Phạm vi cố ý chỉ gồm metadata — gắn câu hỏi vào đề là luồng
  riêng của giáo viên khi soạn đề, không lặp lại ở đây (xem ContentService).
--}}
@extends('layouts.admin')

@section('title', 'Tạo đề/bộ bài')
@section('page-title', 'Tạo đề/bộ bài')

@section('content')
    @php $types = $types ?? []; $publishAnswerRules = $publishAnswerRules ?? []; @endphp

    <a href="{{ route('admin.content.index', ['tab' => 'assessments']) }}" class="text-sm text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-rose-600">‹ Quay lại Nội dung</a>

    <x-page-header title="📄 Tạo đề/bộ bài" subtitle="Chỉ tạo thông tin chung của đề — gắn danh sách câu hỏi vào đề thực hiện ở màn soạn đề của giáo viên." />

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <div class="bg-white rounded-2xl border border-slate-200 p-6">
        <form method="POST" action="{{ route('admin.content.assessments.store') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1" for="title">Tên đề/bộ bài</label>
                <input id="title" name="title" type="text" value="{{ old('title') }}" required maxlength="255"
                       placeholder="Ví dụ: Đề thi giữa kỳ 1 - Tin học 10"
                       class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="type">Loại</label>
                    <x-select id="type" name="type" required>
                        @foreach ($types as $value => $label)
                            <option value="{{ $value }}" @selected(old('type', 'practice') === $value)>{{ $label }}</option>
                        @endforeach
                    </x-select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="total_points">Tổng điểm</label>
                    <input id="total_points" name="total_points" type="number" min="0" value="{{ old('total_points', 10) }}"
                           class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="duration_minutes">Thời gian làm bài (phút)</label>
                    <input id="duration_minutes" name="duration_minutes" type="number" min="0" value="{{ old('duration_minutes') }}"
                           placeholder="Để trống nếu không giới hạn"
                           class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="publish_answer_rule">Hiện đáp án</label>
                    <x-select id="publish_answer_rule" name="publish_answer_rule" required>
                        @foreach ($publishAnswerRules as $value => $label)
                            <option value="{{ $value }}" @selected(old('publish_answer_rule', 'never') === $value)>{{ $label }}</option>
                        @endforeach
                    </x-select>
                </div>
            </div>

            <div class="rounded-lg bg-sky-50 border border-sky-100 p-3 text-xs text-sky-700">
                Đề luôn tạo ở trạng thái <span class="font-medium">Nháp</span> — vào trang chi tiết để phát hành sau.
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit" class="px-5 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium shadow-sm hover:bg-rose-700 transition">Tạo đề/bộ bài</button>
                <a href="{{ route('admin.content.index', ['tab' => 'assessments']) }}" class="px-5 py-2.5 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium hover:border-rose-200 hover:text-rose-600 transition">Huỷ</a>
            </div>
        </form>
    </div>
@endsection

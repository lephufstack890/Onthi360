{{--
  Route: teacher.questions.create | Frame: TEA-03 (tạo mới)
  Spec: 6.1 (3 loại câu) + 6.2 (điều kiện phát hành — chặn nếu thiếu
  test/đáp án/điểm/cấu hình OJ).
  TODO controller: submit qua App\Services\QuestionPublishGuard trước khi
  cho phát hành; lưu nháp luôn được phép dù thiếu cấu hình.
--}}
@extends('layouts.teacher')

@section('title', 'Tạo câu hỏi')
@section('page-title', 'Tạo câu hỏi')

@section('content')
    @php
        $type = $type ?? request('type', 'mcq');
        $types = [
            ['key' => 'mcq', 'label' => 'Trắc nghiệm'],
            ['key' => 'fill', 'label' => 'Điền đáp án'],
            ['key' => 'code', 'label' => 'Lập trình'],
        ];
    @endphp

    <a href="{{ route('teacher.questions.index') }}" class="text-sm text-slate-500 mb-4 inline-block">‹ Quay lại Kho câu hỏi</a>

    <x-page-header title="Tạo câu hỏi" />

    <div class="flex gap-2 mb-6">
        @foreach ($types as $t)
            <a href="{{ route('teacher.questions.create', ['type' => $t['key']]) }}"
               class="px-4 py-2 rounded-lg text-sm font-medium {{ $type === $t['key'] ? 'bg-rose-600 text-white' : 'border border-slate-200 text-slate-600' }}">
                {{ $t['label'] }}
            </a>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 p-5 space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1">Tên câu hỏi</label>
                <input type="text" class="w-full rounded-lg border border-slate-200 text-sm p-2.5" placeholder="VD: Bài 14 - Quy hoạch động cơ bản">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1">Nội dung đề bài</label>
                <textarea rows="5" class="w-full rounded-lg border border-slate-200 text-sm p-3" placeholder="Nhập đề bài..."></textarea>
            </div>

            @if ($type === 'mcq')
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-2">Phương án trả lời</label>
                    <div class="space-y-2">
                        @foreach (['A', 'B', 'C', 'D'] as $opt)
                            <div class="flex items-center gap-2">
                                <input type="radio" name="correct">
                                <input type="text" class="flex-1 rounded-lg border border-slate-200 text-sm p-2" placeholder="Phương án {{ $opt }}">
                            </div>
                        @endforeach
                    </div>
                    <p class="text-xs text-slate-400 mt-2">Chọn radio ở phương án đúng. Chưa chọn = chặn phát hành (6.2).</p>
                </div>
            @elseif ($type === 'fill')
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Đáp án đúng</label>
                        <input type="text" class="w-full rounded-lg border border-slate-200 text-sm p-2.5">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Dạng dữ liệu</label>
                        <select class="w-full rounded-lg border border-slate-200 text-sm p-2.5">
                            <option>Số</option><option>Chuỗi</option>
                        </select>
                    </div>
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox"> Phân biệt hoa/thường
                </label>
            @else
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Time limit (giây)</label>
                        <input type="number" value="1" class="w-full rounded-lg border border-slate-200 text-sm p-2.5">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Memory limit (MB)</label>
                        <input type="number" value="256" class="w-full rounded-lg border border-slate-200 text-sm p-2.5">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Test cases (công khai/ẩn)</label>
                    <div class="rounded-lg border-2 border-dashed border-slate-200 p-4 text-center text-sm text-slate-400">
                        Kéo thả hoặc bấm để tải file test — 0 test đã thêm
                    </div>
                </div>
            @endif
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <h3 class="font-medium text-slate-700 mb-3">Điểm & trạng thái</h3>
            <div class="mb-4">
                <label class="block text-sm text-slate-600 mb-1">Điểm</label>
                <input type="number" value="10" class="w-full rounded-lg border border-slate-200 text-sm p-2.5">
            </div>
            <div class="rounded-lg bg-amber-50 border border-amber-100 p-3 text-xs text-amber-700 mb-4">
                Chưa thể phát hành: {{ $type === 'code' ? 'thiếu test ẩn.' : 'chưa xác nhận đáp án đúng.' }}
            </div>
            <button type="button" class="w-full px-4 py-2 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium mb-2">Lưu nháp</button>
            <button type="button" class="w-full px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium">Phát hành</button>
        </div>
    </div>
@endsection

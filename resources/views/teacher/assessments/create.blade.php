{{--
  Route: teacher.assessments.create | Frame: TEA-04
  Spec: 6.3 (đề hỗn hợp trộn nhiều kiểu câu) + 8.4 (giao đề: chọn lớp,
  mốc thời gian, quy tắc — không có ngoại lệ từng học sinh).
  TODO controller: submit tạo Assessment + AssessmentItem[], sau đó
  Assignment nếu giao cho lớp cụ thể.
--}}
@extends('layouts.teacher')

@section('title', 'Tạo đề')
@section('page-title', 'Tạo đề')

@section('content')
    {{-- Dữ liệu thật (rỗng cho tới khi có AssessmentBuilderService) do
    App\Http\Controllers\Teacher\AssessmentController truyền vào. --}}
    @php
        $selected = $selected ?? [];
        $typeIcons = ['mcq' => '🔤', 'fill' => '✏️', 'code' => '💻'];
    @endphp

    <a href="{{ route('teacher.questions.index') }}" class="text-sm text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-rose-600">‹ Quay lại</a>

    <x-page-header title="Tạo đề" subtitle="Trộn được lập trình, trắc nghiệm và điền đáp án trong cùng một đề (6.3)." />

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-4">
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <label class="block text-sm font-medium text-slate-600 mb-1">Tên đề</label>
                <input type="text" class="w-full rounded-lg border border-slate-200 text-sm p-2.5" placeholder="VD: Đề ôn chương 3 - Cấu trúc dữ liệu">
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-medium text-slate-700 flex items-center gap-2"><span>📋</span> Danh sách câu đã chọn</h3>
                    <button type="button" class="text-sm text-rose-600 font-medium">+ Thêm câu từ kho</button>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse ($selected as $i => $q)
                        <div class="flex items-center justify-between py-3">
                            <div class="flex items-center gap-3">
                                <span class="w-6 h-6 rounded-full bg-slate-100 text-xs flex items-center justify-center text-slate-500 shrink-0">{{ $i + 1 }}</span>
                                <span class="text-base">{{ $typeIcons[$q['type']] ?? '❓' }}</span>
                                <div>
                                    <p class="text-sm text-slate-700">{{ $q['title'] }}</p>
                                    <p class="text-xs text-slate-400">{{ $q['type'] }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <input type="number" value="{{ $q['points'] }}" class="w-16 rounded-lg border border-slate-200 text-sm p-1.5 text-center">
                                <button type="button" class="text-slate-400">↕</button>
                                <button type="button" class="text-rose-500">Xóa</button>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-400 py-3">Chưa chọn câu nào — bấm "+ Thêm câu từ kho" để bắt đầu.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 p-5 space-y-4">
            <h3 class="font-medium text-slate-700 flex items-center gap-2"><span>⚙️</span> Cấu hình</h3>
            <div>
                <label class="block text-sm text-slate-600 mb-1">Thời lượng (phút)</label>
                <input type="number" value="45" class="w-full rounded-lg border border-slate-200 text-sm p-2.5">
            </div>
            <div>
                <label class="block text-sm text-slate-600 mb-1">Nộp lại tối đa</label>
                <input type="number" value="2" class="w-full rounded-lg border border-slate-200 text-sm p-2.5">
            </div>
            <div>
                <label class="block text-sm text-slate-600 mb-1">Công bố đáp án/lời giải</label>
                <x-select>
                    <option>Sau khi hết hạn nộp</option>
                    <option>Ngay sau khi nộp</option>
                    <option>Chỉ khi giáo viên bật</option>
                </x-select>
            </div>
            <div class="rounded-lg bg-sky-50 border border-sky-100 p-3 text-xs text-sky-700">
                Tổng điểm hiện tại: <strong>25</strong>
            </div>
            <button type="button" class="w-full px-4 py-2 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium">Lưu nháp</button>
            <button type="button" class="w-full px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium">Lưu & Giao cho lớp</button>
        </div>
    </div>
@endsection

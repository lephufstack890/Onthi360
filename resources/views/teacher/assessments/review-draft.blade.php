{{--
  Route: teacher.assessments.reviewDraft | Frame: TEA-05 (rà soát)
  Spec: 6.4 — xem song song tệp gốc/câu tách, gắn cờ vùng nhận dạng kém,
  thêm-xóa-gộp-tách-đổi thứ tự câu, sửa nội dung/đáp án/điểm, chọn dạng,
  thêm metadata/cấu hình OJ. Không cho xuất bản khi còn thiếu điều kiện.
  TODO controller: truyền $draftQuestions thật từ App\Models\DraftQuestion
  (bảng draft_questions) + ảnh/text gốc theo vùng.
--}}
@extends('layouts.teacher')

@section('title', 'Rà soát đề nhập')
@section('page-title', 'Rà soát đề nhập')

@section('content')
    @php
        $drafts = [
            ['no' => 1, 'type' => 'Trắc nghiệm', 'confidence' => 'Cao', 'tone' => 'success', 'flagged' => false, 'title' => 'Cấu trúc dữ liệu nào cho phép FIFO?'],
            ['no' => 2, 'type' => 'Điền đáp án', 'confidence' => 'Trung bình', 'tone' => 'warning', 'flagged' => true, 'title' => 'Độ phức tạp thuật toán tìm kiếm nhị phân là O(...)'],
            ['no' => 3, 'type' => 'Lập trình', 'confidence' => 'Thấp — có công thức/ảnh', 'tone' => 'danger', 'flagged' => true, 'title' => 'Viết hàm tính giai thừa đệ quy'],
        ];
    @endphp

    <a href="{{ route('teacher.assessments.import') }}" class="text-sm text-slate-500 mb-4 inline-block">‹ Quay lại Nhập đề</a>

    <x-page-header title="Rà soát: De_thi_thu_HSG.docx" subtitle="Kết quả OCR/trích xuất là bản nháp — phải rà soát và xác nhận trước khi phát hành (6.4)." />

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Tệp gốc --}}
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <h3 class="font-medium text-slate-700 mb-3">Tệp gốc</h3>
            <div class="aspect-[3/4] bg-slate-50 rounded-xl flex items-center justify-center text-slate-300 text-sm">
                Xem trước trang PDF/Word gốc
            </div>
        </div>

        {{-- Danh sách câu tách --}}
        <div class="space-y-3">
            @foreach ($drafts as $d)
                <div class="bg-white rounded-2xl border {{ $d['flagged'] ? 'border-amber-200' : 'border-slate-200' }} p-4">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-medium text-slate-500">Câu {{ $d['no'] }} · {{ $d['type'] }}</span>
                        <x-status-badge :tone="$d['tone']">Độ tin cậy: {{ $d['confidence'] }}</x-status-badge>
                    </div>
                    <p class="text-sm text-slate-700 mb-3">{{ $d['title'] }}</p>
                    @if ($d['flagged'])
                        <p class="text-xs text-amber-700 bg-amber-50 rounded-lg px-3 py-2 mb-3">⚠ Vùng nhận dạng kém — vui lòng kiểm tra kỹ nội dung/đáp án trước khi xác nhận.</p>
                    @endif
                    <div class="flex items-center gap-3 text-sm">
                        <button type="button" class="text-slate-600">Sửa nội dung</button>
                        <button type="button" class="text-slate-600">Gộp với câu khác</button>
                        <button type="button" class="text-slate-600">Đổi dạng câu</button>
                        <button type="button" class="text-rose-500">Xóa</button>
                    </div>
                </div>
            @endforeach

            <button type="button" class="w-full rounded-2xl border-2 border-dashed border-slate-200 text-slate-400 text-sm py-3 hover:border-rose-300 hover:text-rose-500">
                + Thêm câu thủ công
            </button>
        </div>
    </div>

    <div class="rounded-2xl bg-amber-50 border border-amber-100 p-4 mt-6 flex items-center justify-between flex-wrap gap-3">
        <p class="text-sm text-amber-800">Chưa thể chuyển vào kho — Câu 2, Câu 3 còn thiếu xác nhận đáp án/cấu hình OJ.</p>
        <div class="flex gap-2">
            <button type="button" class="px-4 py-2 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium">Lưu tiến trình rà soát</button>
            <button type="button" class="px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium">Chuyển vào kho câu hỏi</button>
        </div>
    </div>
@endsection

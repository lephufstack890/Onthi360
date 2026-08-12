{{--
  Route: teacher.assessments.import | Frame: TEA-05 (tải)
  Spec: 6.4 (Tải Word/PDF/PDF scan → OCR/trích xuất → phân rã bản nháp →
  rà soát → phát hành). OCR không tự phát hành (6.4, 17).
  TODO controller: upload thật (quét virus/định dạng — 16 mục 6), dispatch
  job OCR/trích xuất, poll trạng thái xử lý.
--}}
@extends('layouts.teacher')

@section('title', 'Nhập đề')
@section('page-title', 'Nhập đề (Word/PDF/OCR)')

@section('content')
    @php
        $processingFiles = [
            ['name' => 'De_on_chuong3.pdf', 'status' => 'Đang OCR...', 'tone' => 'info', 'progress' => 60],
            ['name' => 'De_thi_thu_HSG.docx', 'status' => 'Cần rà soát', 'tone' => 'warning', 'progress' => 100],
        ];
    @endphp

    <x-page-header title="Nhập đề" subtitle="Giảm thao tác nhập tay — không thay thế bước kiểm duyệt chuyên môn và không tự phát hành (6.4)." />

    <div class="rounded-2xl border-2 border-dashed border-slate-200 bg-white p-10 text-center mb-6">
        <div class="text-4xl mb-3">📄</div>
        <p class="font-medium text-slate-700">Kéo thả file vào đây hoặc bấm để chọn</p>
        <p class="text-sm text-slate-400 mt-1">Hỗ trợ Word (.docx), PDF có lớp văn bản, PDF scan/ảnh (tự OCR tiếng Việt)</p>
        <button type="button" class="mt-4 px-5 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium">Chọn file</button>
    </div>

    <h3 class="font-medium text-slate-700 mb-3">Đang xử lý</h3>
    <div class="space-y-3">
        @foreach ($processingFiles as $f)
            <div class="bg-white rounded-2xl border border-slate-200 p-4 flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <x-icon-tile emoji="📄" tone="sky" />
                    <div>
                        <p class="text-sm font-medium text-slate-700">{{ $f['name'] }}</p>
                        <div class="w-48 mt-1"><x-progress-bar :percent="$f['progress']" tone="{{ $f['tone'] === 'warning' ? 'warning' : 'info' }}" /></div>
                    </div>
                </div>
                <div class="text-right">
                    <x-status-badge :tone="$f['tone']">{{ $f['status'] }}</x-status-badge>
                    @if ($f['status'] === 'Cần rà soát')
                        <a href="{{ route('teacher.assessments.reviewDraft') }}" class="block mt-1 text-sm text-rose-600 font-medium">Rà soát ngay ›</a>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <p class="text-xs text-slate-400 mt-6">Khi OCR thất bại một phần, tệp vẫn vào trạng thái "Cần rà soát" — không âm thầm bỏ câu (6.4).</p>
@endsection

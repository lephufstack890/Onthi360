{{--
  Route: teacher.assessments.import / .import.store | Frame: TEA-05 (tải)
  Spec: 6.4 (Tải Word/PDF/PDF scan → OCR/trích xuất → phân rã bản nháp →
  rà soát → phát hành). OCR không tự phát hành (6.4, 17).
  Dữ liệu thật do App\Http\Controllers\Teacher\AssessmentController truyền vào
  qua App\Services\Teacher\AssessmentService::importStatusFor(); xử lý tải lên
  thật do App\Services\Teacher\DocumentImportService thực hiện (docx: đọc
  trực tiếp; pdf có lớp văn bản: pdftotext; pdf scan/ảnh: pdftoppm + tesseract
  OCR tiếng Việt — máy chủ cần cài các công cụ này, xem lỗi bên dưới nếu thiếu).
--}}
@extends('layouts.teacher')

@section('title', 'Nhập đề')
@section('page-title', 'Nhập đề (Word/PDF/OCR)')

@section('content')
    @php
        $processingFiles = $processingFiles ?? [];
        $maxFileMb = (int) round(($maxFileKb ?? 20480) / 1024);
    @endphp

    <x-page-header title="Nhập đề" subtitle="Giảm thao tác nhập tay — không thay thế bước kiểm duyệt chuyên môn và không tự phát hành (6.4)." />

    @if (session('status') === 'import-failed')
        @include('partials.toast-flash', ['type' => 'error', 'message' => 'Xử lý tệp thất bại: '.session('importError', 'Lỗi không rõ.')])
    @endif
    @if (session('status') === 'draft-added' || session('status') === 'draft-promoted-one' || session('status') === 'draft-saved-pending' || session('status') === 'draft-merged' || session('status') === 'draft-discarded')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã lưu thay đổi.'])
    @endif

    <form method="POST" action="{{ route('teacher.assessments.import.store') }}" enctype="multipart/form-data"
          x-data="{ fileName: null, submitting: false }"
          @submit="submitting = true">
        @csrf
        <label for="import-file"
               class="block rounded-3xl border-2 border-dashed border-slate-200 bg-white p-10 lg:p-14 text-center mb-2 hover:border-rose-300 transition cursor-pointer">
            <div class="w-16 h-16 rounded-2xl bg-rose-50 flex items-center justify-center text-4xl mx-auto mb-4">📄</div>
            <p class="font-medium text-slate-700" x-text="fileName ? fileName : 'Bấm để chọn file (.docx hoặc .pdf)'"></p>
            <p class="text-sm text-slate-400 mt-1">Hỗ trợ Word (.docx), PDF có lớp văn bản, PDF scan/ảnh (tự OCR tiếng Việt) — tối đa {{ $maxFileMb }}MB</p>
            <input id="import-file" type="file" name="file" accept=".docx,.pdf" required class="hidden"
                   @change="fileName = $event.target.files[0]?.name ?? null">
            <span class="inline-block mt-4 px-5 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium">Chọn file</span>
        </label>
        @error('file')
            <p class="text-xs text-rose-500 mb-4">{{ $message }}</p>
        @enderror
        <button type="submit" :disabled="!fileName || submitting"
                class="w-full sm:w-auto px-6 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium disabled:opacity-40 disabled:cursor-not-allowed mb-6">
            <span x-show="!submitting">Tải lên & xử lý</span>
            <span x-show="submitting">Đang xử lý... (có thể mất một lúc với PDF scan)</span>
        </button>
    </form>

    <h3 class="font-medium text-slate-700 mb-3 flex items-center gap-2"><span>⏳</span> Đã tải lên gần đây</h3>
    <div class="space-y-3">
        @forelse ($processingFiles as $f)
            <div class="bg-white rounded-2xl border border-slate-200 p-4">
                <div class="flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <x-icon-tile emoji="📄" tone="sky" />
                        <div>
                            <p class="text-sm font-medium text-slate-700">{{ $f['name'] }}</p>
                            <div class="w-48 mt-1"><x-progress-bar :percent="$f['progress']" tone="{{ $f['tone'] === 'warning' ? 'warning' : ($f['tone'] === 'danger' ? 'danger' : 'info') }}" /></div>
                        </div>
                    </div>
                    <div class="text-right">
                        <x-status-badge :tone="$f['tone']">{{ $f['status'] }}</x-status-badge>
                        @if ($f['status'] === 'Cần rà soát')
                            <a href="{{ route('teacher.assessments.reviewDraft', ['document' => $f['id']]) }}" class="block mt-1 text-sm text-rose-600 font-medium">Rà soát ngay ›</a>
                        @endif
                    </div>
                </div>
                @if ($f['errorLog'])
                    <p class="text-xs text-rose-600 bg-rose-50 rounded-lg px-3 py-2 mt-3">⚠ {{ $f['errorLog'] }}</p>
                @endif
            </div>
        @empty
            <x-empty-state title="Chưa có tệp nào đang xử lý" description="Tải Word/PDF lên ở trên để bắt đầu." />
        @endforelse
    </div>

    <p class="text-xs text-slate-400 mt-6 flex items-center gap-1.5"><span>ℹ️</span> Khi OCR thất bại một phần, tệp vẫn vào trạng thái "Cần rà soát" — không âm thầm bỏ câu (6.4). Kết quả nhập luôn ở dạng Nháp trong Kho câu hỏi, giáo viên vẫn phải tự bấm "Phát hành" từng câu.</p>
@endsection

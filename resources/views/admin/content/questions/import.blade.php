@extends('layouts.admin')

@section('title', 'Nhập đề')
@section('page-title', 'Nhập đề (Word/PDF/OCR) — Kho chung')

@section('content')
    @php
        $documents = $documents ?? [];
        $maxFileMb = (int) round(($maxFileKb ?? 20480) / 1024);
    @endphp

    <a href="{{ route('admin.content.index', ['tab' => 'drafts']) }}" class="text-sm text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-rose-600">‹ Quay lại Nội dung</a>

    <x-page-header title="Nhập đề — Kho chung" subtitle="Giảm thao tác nhập tay — không thay thế bước kiểm duyệt chuyên môn và không tự phát hành (6.4). Câu tách ra sẽ vào Kho chung (6.5), không phải kho riêng của giáo viên." />

    @if (session('status') === 'import-failed')
        @include('partials.toast-flash', ['type' => 'error', 'message' => 'Xử lý tệp thất bại: '.session('importError', 'Lỗi không rõ.')])
    @endif
    @if (session('status') === 'draft-added' || session('status') === 'draft-promoted-one' || session('status') === 'draft-saved-pending' || session('status') === 'draft-merged' || session('status') === 'draft-discarded')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã lưu thay đổi.'])
    @endif

    <form method="POST" action="{{ route('admin.content.questions.import.store') }}" enctype="multipart/form-data"
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

    <h3 class="font-medium text-slate-700 mb-3 flex items-center gap-2"><span>⏳</span> Đã tải lên gần đây (toàn Kho chung)</h3>
    <div class="space-y-3">
        @forelse ($documents as $d)
            <div class="bg-white rounded-2xl border border-slate-200 p-4">
                <div class="flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <x-icon-tile emoji="📄" tone="sky" />
                        <div>
                            <p class="text-sm font-medium text-slate-700">{{ $d['name'] }}</p>
                            <p class="text-xs text-slate-400">Người tải lên: {{ $d['uploader'] }}</p>
                            <div class="w-48 mt-1"><x-progress-bar :percent="$d['progress']" tone="{{ $d['tone'] === 'warning' ? 'warning' : ($d['tone'] === 'danger' ? 'danger' : 'info') }}" /></div>
                        </div>
                    </div>
                    <div class="text-right">
                        <x-status-badge :tone="$d['tone']">{{ $d['status'] }}</x-status-badge>
                        @if ($d['reviewable'])
                            <a href="{{ route('admin.content.questions.reviewDraft', ['document' => $d['id']]) }}" class="block mt-1 text-sm text-rose-600 font-medium">Rà soát ngay ›</a>
                        @endif
                    </div>
                </div>
                @if ($d['errorLog'])
                    <p class="text-xs text-rose-600 bg-rose-50 rounded-lg px-3 py-2 mt-3">⚠ {{ $d['errorLog'] }}</p>
                @endif
            </div>
        @empty
            <x-empty-state title="Chưa có tệp nào đang xử lý" description="Tải Word/PDF lên ở trên để bắt đầu." />
        @endforelse
    </div>

    <p class="text-xs text-slate-400 mt-6 flex items-center gap-1.5"><span>ℹ️</span> Khi OCR thất bại một phần, tệp vẫn vào trạng thái "Cần rà soát" — không âm thầm bỏ câu (6.4). Kết quả nhập luôn ở dạng Nháp trong Kho chung, vẫn phải tự bấm "Phát hành" từng câu.</p>
@endsection

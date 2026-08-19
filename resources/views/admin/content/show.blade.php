@extends('layouts.admin')

@section('title', 'Chi tiết nội dung')
@section('page-title', 'Chi tiết nội dung')

@section('content')
    @php
        $type = $type ?? null;
        $publishErrors = $publishErrors ?? [];
        $hasBeenAttempted = $hasBeenAttempted ?? false;
        $statusValue = $item['statusValue'] ?? null;

        $editRoute = match ($type) {
            'material' => route('admin.content.materials.edit', $item['id']),
            'question' => route('admin.content.questions.edit', $item['id']),
            'assessment' => route('admin.content.assessments.edit', $item['id']),
            default => null,
        };
        $publishRoute = match ($type) {
            'material' => route('admin.content.materials.publish', $item['id']),
            'question' => route('admin.content.questions.publish', $item['id']),
            'assessment' => route('admin.content.assessments.publish', $item['id']),
            default => null,
        };
        $rejectRoute = match ($type) {
            'material' => route('admin.content.materials.reject', $item['id']),
            'question' => route('admin.content.questions.reject', $item['id']),
            'assessment' => route('admin.content.assessments.reject', $item['id']),
            default => null,
        };
        $archiveRoute = match ($type) {
            'material' => route('admin.content.materials.archive', $item['id']),
            'question' => route('admin.content.questions.archive', $item['id']),
            'assessment' => route('admin.content.assessments.archive', $item['id']),
            default => null,
        };
    @endphp

    <a href="{{ route('admin.content.index') }}" class="text-sm text-slate-500 mb-4 inline-block">‹ Quay lại Nội dung</a>

    @php
        $contentStatusMessage = match (session('status')) {
            'material-created', 'question-created', 'assessment-created' => 'Đã tạo nội dung mới.',
            'material-updated', 'question-updated', 'assessment-updated' => 'Đã lưu thay đổi.',
            'question-versioned' => 'Đã tạo phiên bản mới, câu gốc được giữ nguyên (6.2).',
            'material-published', 'question-published', 'assessment-published' => 'Đã phát hành.',
            'material-rejected', 'question-rejected', 'assessment-rejected' => 'Đã trả về nháp, đã ghi lý do.',
            'material-archived', 'question-archived', 'assessment-archived' => 'Đã lưu trữ, đã ghi lý do.',
            default => session('status') ? 'Đã cập nhật.' : null,
        };
    @endphp
    @if ($contentStatusMessage)
        @include('partials.toast-flash', ['type' => 'success', 'message' => $contentStatusMessage])
    @endif

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <x-page-header :title="$item['title']" :subtitle="$typeLabel">
        @if ($editRoute)
            <x-slot:actions>
                <a href="{{ $editRoute }}" class="px-4 py-2 rounded-lg border border-slate-200 bg-white text-slate-600 text-sm font-medium hover:border-rose-200 hover:text-rose-600 transition">✏️ Sửa</a>
            </x-slot:actions>
        @endif
    </x-page-header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-5">
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="font-medium text-slate-700">Trạng thái hiện tại</h2>
                    <x-status-badge :tone="$item['tone']">{{ $item['status'] }}</x-status-badge>
                </div>

                @if ($hasBeenAttempted)
                    <p class="text-xs text-amber-700 bg-amber-50 border border-amber-100 rounded-lg p-2 mb-2">Đã có học sinh làm câu này — sửa nội dung sẽ tạo phiên bản mới thay vì sửa trực tiếp (6.2).</p>
                @endif

                @if (!empty($publishErrors))
                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                        <p class="text-sm font-medium text-amber-800 mb-2">Chưa thể phát hành — còn thiếu:</p>
                        <ul class="list-disc list-inside text-sm text-amber-700 space-y-1">
                            @foreach ($publishErrors as $err)
                                <li>{{ $err }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            @if ($type === 'question' && $model)
                <div class="bg-white rounded-2xl border border-slate-200 p-5">
                    <h2 class="font-medium text-slate-700 mb-3 flex items-center gap-2"><span>📝</span> Nội dung đề bài</h2>
                    <p class="text-xs text-slate-400 mb-2">Mã: {{ $model->code }} · Điểm: {{ $model->points }} · Phiên bản: v{{ $model->version }}</p>
                    <div class="rich-content text-sm text-slate-600 leading-relaxed">{!! $model->body ?: '<span class="text-slate-400">Chưa có nội dung.</span>' !!}</div>
                </div>
            @elseif ($type === 'assessment' && $model && $model->isPdfMode())
                {{-- SỬA 18/8 (đề PDF + phiếu đáp án, 16/8 mục 1.2): content_mode=pdf_answer_sheet
                     không có Question nào để liệt kê — thay bằng tóm tắt PDF/mã đề/đáp án/bài
                     lập trình + nút sang màn cấu hình riêng (admin.content.assessments.pdf.edit). --}}
                <div class="bg-white rounded-2xl border border-slate-200 p-5 text-sm text-slate-500 space-y-1">
                    <p>Loại: {{ $model->type->value }} · Tổng điểm: {{ $model->total_points }}</p>
                    <p>Thời gian làm bài: {{ $model->duration_minutes ? $model->duration_minutes.' phút' : 'Không giới hạn' }}</p>
                    <p>Mã đề: {{ $model->exam_code ?: '— chưa đặt' }}</p>
                </div>

                <div class="bg-white rounded-2xl border border-slate-200 p-5">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="font-medium text-slate-700 flex items-center gap-2"><span>📄</span> Đề PDF + phiếu đáp án</h2>
                        <a href="{{ route('admin.content.assessments.pdf.edit', $model->id) }}" class="text-sm text-rose-600 font-medium">Quản lý đề PDF ›</a>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-center">
                        <div class="rounded-xl bg-slate-50 p-3">
                            <p class="text-xs text-slate-400">File PDF đề</p>
                            <p class="text-sm font-medium {{ $model->pdf_path ? 'text-emerald-600' : 'text-amber-600' }}">{{ $model->pdf_path ? 'Đã tải' : 'Chưa tải' }}</p>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-3">
                            <p class="text-xs text-slate-400">PDF lời giải</p>
                            <p class="text-sm font-medium {{ $model->solution_pdf_path ? 'text-emerald-600' : 'text-slate-400' }}">{{ $model->solution_pdf_path ? 'Đã tải' : 'Chưa có' }}</p>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-3">
                            <p class="text-xs text-slate-400">Câu đáp án</p>
                            <p class="text-sm font-medium text-slate-700">{{ $model->answerKeys->count() }}</p>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-3">
                            <p class="text-xs text-slate-400">Bài lập trình</p>
                            <p class="text-sm font-medium text-slate-700">{{ $model->codingItems->count() }}</p>
                        </div>
                    </div>

                    @if ($model->pdf_path && (!is_null($model->preview_page_from) || !is_null($model->preview_page_to)))
                        <p class="text-xs text-slate-400 mt-3">Xem thử: trang {{ $model->preview_page_from ?? 1 }} – {{ $model->preview_page_to ?? '?' }}</p>
                    @endif
                </div>
            @elseif ($type === 'assessment' && $model)
                @php $assessmentTypeIcons = ['mcq' => '🔤', 'fill_blank' => '✏️', 'coding' => '💻']; @endphp
                <div class="bg-white rounded-2xl border border-slate-200 p-5 text-sm text-slate-500 space-y-1">
                    <p>Loại: {{ $model->type->value }} · Tổng điểm: {{ $model->total_points }}</p>
                    <p>Thời gian làm bài: {{ $model->duration_minutes ? $model->duration_minutes.' phút' : 'Không giới hạn' }}</p>
                </div>

                {{-- SỬA 18/8: trước đây chỗ này chỉ có 1 dòng TODO, click "Xem" không thấy câu hỏi
                     nào trong đề — nay hiện đúng danh sách câu hỏi thật ($model->items, đã eager-load
                     items.question ở ContentService::showData()) + nút sang màn "Chọn câu hỏi". --}}
                <div class="bg-white rounded-2xl border border-slate-200 p-5">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="font-medium text-slate-700 flex items-center gap-2"><span>📋</span> Câu hỏi trong đề ({{ $model->items->count() }})</h2>
                        <a href="{{ route('admin.content.assessments.items.edit', $model->id) }}" class="text-sm text-rose-600 font-medium">Quản lý câu hỏi ›</a>
                    </div>

                    @if ($model->items->isEmpty())
                        <x-empty-state title="Đề này chưa có câu hỏi nào" description="Bấm 'Quản lý câu hỏi' để chọn câu hỏi cho đề." actionLabel="Chọn câu hỏi" :actionHref="route('admin.content.assessments.items.edit', $model->id)" />
                    @else
                        <div class="divide-y divide-slate-100">
                            @foreach ($model->items as $it)
                                <div class="flex items-center justify-between py-2.5 gap-3">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <span class="text-base shrink-0">{{ $assessmentTypeIcons[$it->question?->type?->value] ?? '❓' }}</span>
                                        <p class="text-sm text-slate-700 truncate">{{ $it->question->title ?? '(Câu hỏi đã bị xoá)' }}</p>
                                    </div>
                                    <span class="text-xs text-slate-400 shrink-0">{{ $it->effectivePoints() }} điểm</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @elseif ($type === 'material' && $model)
                <div class="bg-white rounded-2xl border border-slate-200 p-5 text-sm text-slate-500 space-y-1">
                    <p>Thuộc sản phẩm: {{ $model->product->title ?? '—' }}</p>
                    <p>Loại: {{ ['chapter' => 'Chương', 'section' => 'Bài/Mục', 'assessment_ref' => 'Tham chiếu đề/bộ bài'][$model->type] ?? $model->type }}</p>
                </div>
            @else
                <div class="bg-white rounded-2xl border border-slate-200 p-5">
                    <p class="text-sm text-slate-500">Không tìm thấy nội dung phù hợp.</p>
                </div>
            @endif
        </div>

        @if ($type)
            <div class="bg-white rounded-2xl border border-slate-200 p-5 space-y-3">
                <h3 class="font-medium text-slate-700 mb-1">Hành động</h3>

                @if ($statusValue !== 'published')
                    <form method="POST" action="{{ $publishRoute }}">
                        @csrf
                        <button type="submit" @disabled(!empty($publishErrors))
                                class="w-full px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-medium disabled:opacity-40 disabled:cursor-not-allowed">
                            Phát hành
                        </button>
                    </form>
                @endif

                @if ($statusValue === 'published')
                    <div x-data="{ reason: '' }" class="space-y-2 pt-1 border-t border-slate-100">
                        <p class="text-xs text-slate-500 pt-2">Trả về nháp (bắt buộc nêu lý do, 10.4):</p>
                        <form method="POST" action="{{ $rejectRoute }}" class="space-y-2">
                            @csrf
                            <textarea name="reason" x-model="reason" rows="2" required class="w-full rounded-lg border border-slate-200 text-sm p-2" placeholder="Lý do..."></textarea>
                            <button type="submit" :disabled="reason.trim().length === 0" class="w-full px-4 py-2 rounded-lg border border-amber-300 text-amber-700 text-sm font-medium disabled:opacity-40 disabled:cursor-not-allowed">Trả về nháp</button>
                        </form>
                    </div>
                @endif

                @if ($statusValue !== 'archived')
                    <div x-data="{ reason: '' }" class="space-y-2 pt-2 border-t border-slate-100">
                        <p class="text-xs text-slate-500 pt-2">Lưu trữ (bắt buộc nêu lý do, 10.4):</p>
                        <form method="POST" action="{{ $archiveRoute }}" class="space-y-2">
                            @csrf
                            <textarea name="reason" x-model="reason" rows="2" required class="w-full rounded-lg border border-slate-200 text-sm p-2" placeholder="Lý do..."></textarea>
                            <button type="submit" :disabled="reason.trim().length === 0" class="w-full px-4 py-2 rounded-lg border border-rose-300 text-rose-600 text-sm font-medium disabled:opacity-40 disabled:cursor-not-allowed">Lưu trữ</button>
                        </form>
                    </div>
                @endif
            </div>
        @endif
    </div>

    @push('scripts')
        <style>
            .rich-content ul { list-style: disc; padding-left: 1.25rem; margin-bottom: 0.5rem; }
            .rich-content ol { list-style: decimal; padding-left: 1.25rem; margin-bottom: 0.5rem; }
            .rich-content p { margin-bottom: 0.5rem; }
        </style>
    @endpush
@endsection

@extends('layouts.admin')

@section('title', 'Chi tiết nội dung')
@section('page-title', 'Chi tiết nội dung')

@section('content')
    @php
        $type = $type ?? null;
        $model = $model ?? null;
        $publishErrors = $publishErrors ?? [];
        $hasBeenAttempted = $hasBeenAttempted ?? false;
        $statusValue = $item['statusValue'] ?? null;
        // SỬA 26/8 ("gộp Học liệu vào Sản phẩm & quyền"): học liệu giờ quản lý từ trang sản
        // phẩm, không còn tab riêng ở Nội dung — quay lại đúng sản phẩm sở hữu bài này thay
        // vì Nội dung (Câu hỏi/Đề vẫn quay lại Nội dung như cũ).
        $backHref = ($type === 'material' && $model?->product_id)
            ? route('admin.products.show', $model->product_id)
            : route('admin.content.index');
        $backLabel = $type === 'material' ? '‹ Quay lại sản phẩm' : '‹ Quay lại Nội dung';

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

    {{--
      SỬA 8/10 (khách: "cập nhật UI màn Kho bài tập / câu hỏi và đề theo source mới, logic giữ nguyên") — trang
      chi tiết nội dung theo phong cách của màn Khóa & Lớp / Tài liệu: đầu trang + huy hiệu, thẻ trắng bo tròn,
      khối "Hành động" ở thẻ xanh nhạt. Mọi biến, route, form Phát hành / Trả về nháp / Lưu trữ (bắt buộc lý do)
      và điều kiện hiển thị GIỮ NGUYÊN như bản cũ.
    --}}
    @include('partials.admin-content-ui')
    @php
        $badgeOf = fn ($tone) => match ($tone) {
            'success' => 'acx-badge--ok',
            'warning' => 'acx-badge--warn',
            'danger', 'error' => 'acx-badge--off',
            'info' => 'acx-badge--info',
            default => '',
        };
        $headIcon = match ($type) { 'assessment' => 'file-text', 'material' => 'book-open', default => 'library' };
    @endphp

    <div class="acx-wrap">
        <a href="{{ $backHref }}" class="acx-back">{{ $backLabel }}</a>

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

        <div class="acx-head">
            <div class="acx-head__id">
                <span class="acx-avatar"><x-lucide :name="$headIcon" /></span>
                <div style="min-width:0">
                    <h1>{{ $item['title'] }}</h1>
                    <div class="acx-head__meta">
                        <span class="acx-badge {{ $badgeOf($item['tone'] ?? null) }}">{{ $item['status'] }}</span>
                        <span>{{ $typeLabel }}</span>
                    </div>
                </div>
            </div>
            @if ($editRoute)
                <div class="acx-head__actions">
                    <a href="{{ $editRoute }}" class="acx-btn"><x-lucide name="pen-line" /> Sửa</a>
                </div>
            @endif
        </div>

        <div class="acx-grid acx-grid--main">
            <div class="acx-stack">
                @if ($hasBeenAttempted || !empty($publishErrors))
                    <section class="acx-card acx-card--white">
                        <h2><x-lucide name="info" /> Trạng thái hiện tại</h2>
                        @if ($hasBeenAttempted)
                            <p class="text-xs text-amber-700 bg-amber-50 border border-amber-100 rounded-xl p-2 mb-2">Đã có học sinh làm câu này — sửa nội dung sẽ tạo phiên bản mới thay vì sửa trực tiếp (6.2).</p>
                        @endif

                        @if (!empty($publishErrors))
                            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                                <p class="text-[13px] font-medium text-amber-800 mb-2">Chưa thể phát hành — còn thiếu:</p>
                                <ul class="list-disc list-inside text-[13px] text-amber-700 space-y-1">
                                    @foreach ($publishErrors as $err)
                                        <li>{{ $err }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </section>
                @endif

                @if ($type === 'question' && $model)
                    <section class="acx-card acx-card--white">
                        <h2><x-lucide name="pen-line" /> Nội dung đề bài</h2>
                        {{-- SỬA 8/9 (3) ("phân loại kho câu hỏi theo môn") — hiện Môn/Khối ngay dòng
                             thông tin để trang chi tiết khớp với cột mới ở danh sách; sửa lại ở nút
                             Sửa (2 ô Môn học/Khối lớp). --}}
                        <p class="akx-src" style="margin:0 0 10px">Mã: {{ $model->code }} · Môn: {{ $model->subjectLabel() }} · Khối: {{ $model->gradeLabel() }} · Điểm: {{ $model->points }} · Phiên bản: v{{ $model->version }}</p>
                        <div class="rich-content acx-rich">{!! $model->body ?: '<span class="text-slate-400">Chưa có nội dung.</span>' !!}</div>
                    </section>
                @elseif ($type === 'assessment' && $model && $model->isPdfMode())
                    {{-- SỬA 18/8 (đề PDF + phiếu đáp án, 16/8 mục 1.2): content_mode=pdf_answer_sheet
                         không có Question nào để liệt kê — thay bằng tóm tắt PDF/mã đề/đáp án/bài
                         lập trình + nút sang màn cấu hình riêng (admin.content.assessments.pdf.edit). --}}
                    <section class="acx-card acx-card--white">
                        <div class="akx-src" style="margin:0 0 12px;line-height:1.7">
                            <p>Loại: {{ $model->type->value }} · Tổng điểm: {{ $model->total_points }}</p>
                            <p>Thời gian làm bài: {{ $model->duration_minutes ? $model->duration_minutes.' phút' : 'Không giới hạn' }}</p>
                            <p>Mã đề: {{ $model->exam_code ?: '— chưa đặt' }}</p>
                        </div>

                        <div class="apx-cardtop">
                            <h2><x-lucide name="scroll-text" /> Đề PDF + phiếu đáp án</h2>
                            <a href="{{ route('admin.content.assessments.pdf.edit', $model->id) }}" class="akx-lnk">Quản lý đề PDF ›</a>
                        </div>

                        <div class="acx-stats" style="grid-template-columns:repeat(auto-fit,minmax(130px,1fr))">
                            <div class="acx-stat"><p>File PDF đề</p><strong style="font-size:15px;margin-top:8px" class="{{ $model->pdf_path ? 'apx-ok' : 'apx-warn' }}">{{ $model->pdf_path ? 'Đã tải' : 'Chưa tải' }}</strong></div>
                            <div class="acx-stat"><p>PDF lời giải</p><strong style="font-size:15px;margin-top:8px" class="{{ $model->solution_pdf_path ? 'apx-ok' : 'apx-muted' }}">{{ $model->solution_pdf_path ? 'Đã tải' : 'Chưa có' }}</strong></div>
                            <div class="acx-stat"><p>Câu đáp án</p><strong>{{ $model->answerKeys->count() }}</strong></div>
                            <div class="acx-stat"><p>Bài lập trình</p><strong>{{ $model->codingItems->count() }}</strong></div>
                        </div>

                        @if ($model->pdf_path && (!is_null($model->preview_page_from) || !is_null($model->preview_page_to)))
                            <p class="akx-src" style="margin-top:12px">Xem thử: trang {{ $model->preview_page_from ?? 1 }} – {{ $model->preview_page_to ?? '?' }}</p>
                        @endif
                    </section>
                @elseif ($type === 'assessment' && $model)
                    @php $assessmentTypeIcons = ['mcq' => '🔤', 'fill_blank' => '✏️', 'coding' => '💻']; @endphp
                    <section class="acx-card acx-card--white">
                        <div class="akx-src" style="margin:0;line-height:1.7">
                            <p>Loại: {{ $model->type->value }} · Tổng điểm: {{ $model->total_points }}</p>
                            <p>Thời gian làm bài: {{ $model->duration_minutes ? $model->duration_minutes.' phút' : 'Không giới hạn' }}</p>
                        </div>
                    </section>

                    {{-- SỬA 18/8: trước đây chỗ này chỉ có 1 dòng TODO, click "Xem" không thấy câu hỏi
                         nào trong đề — nay hiện đúng danh sách câu hỏi thật ($model->items, đã eager-load
                         items.question ở ContentService::showData()) + nút sang màn "Chọn câu hỏi". --}}
                    <section class="acx-card acx-card--white" id="assessment-items">
                        <div class="apx-cardtop">
                            <h2><x-lucide name="clipboard-list" /> Câu hỏi trong đề ({{ $model->items->count() }})</h2>
                            <a href="{{ route('admin.content.assessments.items.edit', $model->id) }}" class="akx-lnk">Quản lý câu hỏi ›</a>
                        </div>

                        @if ($model->items->isEmpty())
                            <x-ws.empty-state title="Đề này chưa có câu hỏi nào" description="Bấm 'Quản lý câu hỏi' để chọn câu hỏi cho đề." actionLabel="Chọn câu hỏi" :actionHref="route('admin.content.assessments.items.edit', $model->id)" />
                        @else
                            <div class="apx-list">
                                {{-- SỬA 9/10 (khách: "có nút đưa lên trước … cho từng câu hỏi để tôi thay đổi thứ
                                     tự") — số thứ tự = "Câu N" học sinh thấy; ▲ ▼ dời một bậc, ⤒ đưa lên đầu. --}}
                                <style>
                                    .ai-no { display:inline-grid; place-items:center; flex:none; width:26px; height:26px; border-radius:999px; background:#EAF5F8; color:#126F91; font-size:11px; font-weight:800; }
                                    .ai-mv { display:flex; align-items:center; gap:4px; flex:none; margin-left:10px; }
                                    .ai-mv form { margin:0; }
                                    .ai-btn { display:grid; place-items:center; width:30px; height:30px; border:1px solid #DDEAF0; border-radius:8px; background:#fff; color:#45657D; font-size:13px; line-height:1; cursor:pointer; }
                                    .ai-btn:hover:not(:disabled) { border-color:#126F91; color:#126F91; background:#EAF5F8; }
                                    .ai-btn:disabled { opacity:.35; cursor:not-allowed; }
                                    .ai-pt { display:flex; align-items:center; gap:6px; flex:none; margin:0 0 0 10px; font-size:12px; font-weight:700; color:#607A90; }
                                    .ai-pt input { width:76px; height:32px; box-sizing:border-box; border:1px solid #BFD9E4; border-radius:9px; background:#fff; padding:0 8px; text-align:center; font-size:13px; font-weight:700; color:#123B68; }
                                    .ai-pt input:focus { outline:none; border-color:#126F91; box-shadow:0 0 0 3px rgba(18,111,145,.15); }
                                    .ai-save { width:32px; height:32px; color:#126F91; border-color:#BFD9E4; }
                                </style>
                                @php $itemCount = $model->items->count(); @endphp
                                @foreach ($model->items as $it)
                                    <div class="apx-item" style="padding:10px 2px">
                                        <div class="apx-item__main" style="display:flex;align-items:center;gap:8px">
                                            <span class="ai-no">{{ $loop->iteration }}</span>
                                            <span class="shrink-0">{{ $assessmentTypeIcons[$it->question?->type?->value] ?? '❓' }}</span>
                                            <p class="apx-item__title" style="font-weight:500">{{ $it->question->title ?? '(Câu hỏi đã bị xoá)' }}</p>
                                        </div>
                                        {{-- SỬA 11/10 (khách: "điểm từng câu hỏi trong đề cho nhập chứ k lấy từ độ khó") —
                                             ô NHẬP điểm của câu (số nguyên hoặc thập phân), bấm ✓ để lưu; tổng điểm
                                             đề tự cộng lại. --}}
                                        <form method="POST" action="{{ route('admin.content.assessments.items.points', [$model->id, $it->id]) }}" class="ai-pt">
                                            @csrf
                                            @method('PATCH')
                                            <input type="number" name="points" value="{{ rtrim(rtrim(number_format($it->effectivePoints(), 2, '.', ''), '0'), '.') }}" min="0" max="1000" step="any" required inputmode="decimal" aria-label="Điểm câu {{ $loop->iteration }}">
                                            <span>điểm</span>
                                            <button type="submit" class="ai-btn ai-save" title="Lưu điểm" aria-label="Lưu điểm">✓</button>
                                        </form>
                                        <div class="ai-mv">
                                            @foreach ([['top', '⤒', 'Đưa lên đầu', $loop->first], ['up', '▲', 'Đưa lên trước', $loop->first], ['down', '▼', 'Đưa xuống sau', $loop->last]] as [$dir, $icon, $label, $off])
                                                <form method="POST" action="{{ route('admin.content.assessments.items.move', [$model->id, $it->id]) }}">
                                                    @csrf
                                                    <input type="hidden" name="direction" value="{{ $dir }}">
                                                    <button type="submit" class="ai-btn" title="{{ $label }}" aria-label="{{ $label }}" @disabled($off)>{{ $icon }}</button>
                                                </form>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </section>
                @elseif ($type === 'material' && $model)
                    <section class="acx-card acx-card--white">
                        <div class="akx-src" style="margin:0;line-height:1.7">
                            <p>Thuộc sản phẩm: {{ $model->product->title ?? '—' }}</p>
                            <p>Loại: {{ ['chapter' => 'Chương', 'section' => 'Bài/Mục', 'assessment_ref' => 'Tham chiếu đề/bộ bài'][$model->type] ?? $model->type }}</p>
                        </div>
                    </section>
                @else
                    <section class="acx-card acx-card--white">
                        <p class="acx-note">Không tìm thấy nội dung phù hợp.</p>
                    </section>
                @endif
            </div>

            @if ($type)
                <aside class="acx-card acx-card--mint">
                    <h3><x-lucide name="settings" /> Hành động</h3>
                    <div class="apx-fields">
                        @if ($statusValue !== 'published')
                            <form method="POST" action="{{ $publishRoute }}">
                                @csrf
                                <button type="submit" @disabled(!empty($publishErrors))
                                        class="acx-btn acx-btn--primary" style="width:100%{{ !empty($publishErrors) ? ';opacity:.4;cursor:not-allowed' : '' }}">
                                    Phát hành
                                </button>
                            </form>
                        @endif

                        @if ($statusValue === 'published')
                            <div x-data="{ reason: '' }" class="space-y-2 pt-1 border-t border-slate-100">
                                <p class="apx-note">Trả về nháp (bắt buộc nêu lý do, 10.4):</p>
                                <form method="POST" action="{{ $rejectRoute }}" class="space-y-2">
                                    @csrf
                                    <textarea name="reason" x-model="reason" rows="2" required class="admin-input" placeholder="Lý do..."></textarea>
                                    <button type="submit" :disabled="reason.trim().length === 0" class="acx-btn" style="width:100%;margin-top:8px" :style="reason.trim().length === 0 ? 'opacity:.4;cursor:not-allowed' : ''">Trả về nháp</button>
                                </form>
                            </div>
                        @endif

                        @if ($statusValue !== 'archived')
                            <div x-data="{ reason: '' }" class="space-y-2 pt-2 border-t border-slate-100">
                                <p class="apx-note">Lưu trữ (bắt buộc nêu lý do, 10.4):</p>
                                <form method="POST" action="{{ $archiveRoute }}" class="space-y-2">
                                    @csrf
                                    <textarea name="reason" x-model="reason" rows="2" required class="admin-input" placeholder="Lý do..."></textarea>
                                    <button type="submit" :disabled="reason.trim().length === 0" class="acx-btn" style="width:100%;margin-top:8px" :style="reason.trim().length === 0 ? 'opacity:.4;cursor:not-allowed' : ''">Lưu trữ</button>
                                </form>
                            </div>
                        @endif
                    </div>
                </aside>
            @endif
        </div>
    </div>

    @push('scripts')
        <style>
            .rich-content ul { list-style: disc; padding-left: 1.25rem; margin-bottom: 0.5rem; }
            .rich-content ol { list-style: decimal; padding-left: 1.25rem; margin-bottom: 0.5rem; }
            .rich-content p { margin-bottom: 0.5rem; }
        </style>
    @endpush
@endsection

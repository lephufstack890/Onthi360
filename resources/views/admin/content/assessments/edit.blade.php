@extends('layouts.admin')

@section('title', 'Sửa đề/bộ bài')
@section('page-title', 'Sửa đề/bộ bài')

@section('content')
    @php $types = $types ?? []; $publishAnswerRules = $publishAnswerRules ?? []; @endphp

    <a href="{{ route('admin.content.show', ['content' => $assessment->id, 'kind' => 'assessment']) }}" class="text-[13px] text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-blue-600">‹ Quay lại chi tiết</a>

    <x-ws.page-header title="Sửa đề/bộ bài" icon="pencil" :subtitle="$assessment->title" />

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    {{-- SỬA 17/11 (khách: "nhập đề bằng ZIP xong nhảy ra trang Sửa để check, thiết kế nút Quản lý câu hỏi cho đẹp, click
         là qua trang chi tiết đề") — báo nhập ZIP xong + thẻ "Câu hỏi trong đề" có nút đi thẳng tới mục câu hỏi ở trang chi tiết. --}}
    @php
        $aqShowUrl = route('admin.content.show', ['content' => $assessment->id, 'kind' => 'assessment']).'#assessment-items';
        $aqItems = $assessment->items()->with('question:id,title,type,status')->get();
        $aqPreview = $aqItems->take(6);
        $aqDraft = $aqItems->filter(fn ($it) => $it->question && (($it->question->status->value ?? $it->question->status) !== 'published'))->count();
    @endphp
    @if (session('status') === 'assessment-zip-imported')
        <div class="aq-zip-done">
            <span class="aq-zip-ico"><x-lucide name="check-circle-2" /></span>
            <div class="min-w-0">
                <p class="aq-zip-title">Nhập đề từ gói ZIP thành công</p>
                <p class="aq-zip-text">{{ session('zipImportSummary') ?: 'Đã tạo đề và các câu hỏi.' }} Hãy rà lại thông tin bên dưới, rồi bấm <strong>Quản lý câu hỏi</strong> để kiểm tra / phát hành từng câu.</p>
            </div>
        </div>
    @endif

    <div class="aq-card">
        <div class="aq-head">
            <span class="aq-ico"><x-lucide name="clipboard-list" /></span>
            <div class="min-w-0 flex-1">
                <p class="aq-title">Câu hỏi trong đề <span class="aq-count">{{ $aqItems->count() }}</span></p>
                <p class="aq-sub">
                    {{ rtrim(rtrim(number_format((float) $assessment->total_points, 2, ',', '.'), '0'), ',') }} điểm
                    @if ($aqDraft > 0)
                        · <span class="aq-warn">{{ $aqDraft }} câu chưa phát hành</span>
                    @elseif ($aqItems->isNotEmpty())
                        · <span class="aq-ok">tất cả đã phát hành</span>
                    @endif
                </p>
            </div>
            <div class="aq-actions">
                <a href="{{ $aqShowUrl }}" class="aq-btn aq-btn-primary">
                    <x-lucide name="settings" />Quản lý câu hỏi<x-lucide name="chevron-right" />
                </a>
            </div>
        </div>
        @if ($aqPreview->isNotEmpty())
            <ol class="aq-list">
                @foreach ($aqPreview as $i => $it)
                    @php($aqPub = $it->question && (($it->question->status->value ?? $it->question->status) === 'published'))
                    <li>
                        <a href="{{ $aqShowUrl }}" class="aq-item" title="{{ $it->question?->title }}">
                            <span class="aq-num">{{ $i + 1 }}</span>
                            <span class="aq-name">{{ $it->question?->title ?? 'Câu đã bị xoá' }}</span>
                            <span class="aq-pill {{ $aqPub ? 'is-pub' : 'is-draft' }}">{{ $aqPub ? 'Đã phát hành' : 'Nháp' }}</span>
                        </a>
                    </li>
                @endforeach
            </ol>
            @if ($aqItems->count() > $aqPreview->count())
                <a href="{{ $aqShowUrl }}" class="aq-more">Xem tất cả {{ $aqItems->count() }} câu ›</a>
            @endif
        @else
            <p class="aq-empty">Đề chưa có câu hỏi nào — bấm <strong>Quản lý câu hỏi</strong> để thêm.</p>
        @endif
    </div>

    <style>
        .aq-zip-done { display: flex; gap: 12px; align-items: flex-start; margin-bottom: 16px; padding: 14px 16px; border-radius: 16px; border: 1px solid #bbf7d0; background: linear-gradient(90deg, #f0fdf4, #ecfeff); }
        .aq-zip-ico { display: grid; place-items: center; width: 34px; height: 34px; flex-shrink: 0; border-radius: 10px; background: #16a34a; color: #fff; }
        .aq-zip-ico svg { width: 18px; height: 18px; }
        .aq-zip-title { font-size: 14px; font-weight: 800; color: #14532d; }
        .aq-zip-text { margin-top: 2px; font-size: 13px; line-height: 1.5; color: #166534; }
        .aq-card { margin-bottom: 20px; padding: 18px 20px; border-radius: 24px; border: 1px solid #dbeafe; background: linear-gradient(180deg, #f8fbff, #fff); box-shadow: 0 2px 8px rgba(0,90,180,.04); }
        .aq-head { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; }
        .aq-ico { display: grid; place-items: center; width: 42px; height: 42px; flex-shrink: 0; border-radius: 12px; background: #eff6ff; color: #2563eb; }
        .aq-ico svg { width: 20px; height: 20px; }
        .aq-title { display: flex; align-items: center; gap: 8px; font-size: 16px; font-weight: 800; color: #0f2b4d; }
        .aq-count { display: inline-grid; place-items: center; min-width: 24px; height: 22px; padding: 0 7px; border-radius: 999px; background: #2563eb; color: #fff; font-size: 12px; font-weight: 800; }
        .aq-sub { margin-top: 2px; font-size: 13px; color: #64748b; }
        .aq-warn { color: #b45309; font-weight: 700; }
        .aq-ok { color: #15803d; font-weight: 700; }
        .aq-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-left: auto; }
        .aq-btn { display: inline-flex; align-items: center; gap: 6px; min-height: 40px; padding: 0 14px; border-radius: 12px; font-size: 13px; font-weight: 800; transition: background .15s, box-shadow .15s, transform .15s; }
        .aq-btn svg { width: 16px; height: 16px; }
        .aq-btn-primary { background: #2563eb; color: #fff; box-shadow: 0 6px 16px rgba(37,99,235,.25); }
        .aq-btn-primary:hover { background: #1d4ed8; transform: translateY(-1px); }
        .aq-btn-ghost { border: 1px solid #dbeafe; background: #fff; color: #1e40af; }
        .aq-btn-ghost:hover { background: #eff6ff; }
        .aq-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 8px; margin-top: 14px; }
        .aq-item { display: flex; align-items: center; gap: 10px; padding: 9px 12px; border-radius: 12px; border: 1px solid #e2ecf7; background: #fff; transition: border-color .15s, background .15s; }
        .aq-item:hover { border-color: #93c5fd; background: #f8fbff; }
        .aq-num { display: grid; place-items: center; width: 24px; height: 24px; flex-shrink: 0; border-radius: 999px; background: #e0f2fe; color: #0369a1; font-size: 12px; font-weight: 800; }
        .aq-name { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 13px; font-weight: 600; color: #1e293b; }
        .aq-pill { flex-shrink: 0; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 700; }
        .aq-pill.is-pub { background: #dcfce7; color: #15803d; }
        .aq-pill.is-draft { background: #fef3c7; color: #b45309; }
        .aq-more { display: inline-block; margin-top: 10px; font-size: 13px; font-weight: 700; color: #2563eb; }
        .aq-more:hover { text-decoration: underline; }
        .aq-empty { margin-top: 12px; font-size: 13px; color: #64748b; }
        @media (max-width: 640px) { .aq-actions { width: 100%; } .aq-btn { flex: 1; justify-content: center; } }
    </style>

    @if ($assessment->isPdfMode())
        <div class="rounded-xl border border-sky-200 bg-sky-50 p-4 mb-5 flex items-center justify-between gap-3 flex-wrap">
            <p class="text-[13px] text-sky-800">Đề này dùng chế độ <strong>PDF + phiếu đáp án</strong> — tải file PDF, nhập đáp án và thêm bài lập trình ở màn riêng, không phải ở đây.</p>
            <a href="{{ route('admin.content.assessments.pdf.edit', $assessment->id) }}" class="px-4 py-2 rounded-xl bg-sky-600 text-white text-[13px] font-medium shadow-sm shrink-0">📄 Quản lý đề PDF ›</a>
        </div>
    @endif

    <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-5 sm:p-6">
        {{-- enctype: ô Ảnh bìa trong partial dưới là <input type="file">. --}}
        <form method="POST" action="{{ route('admin.content.assessments.update', $assessment->id) }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            @method('PUT')
            {{-- SỬA 4/10 — xem ghi chú cùng tên ở admin/content/questions/edit. --}}
            <input type="hidden" name="return_to" value="{{ old('return_to', $returnTo ?? '') }}">
            <div>
                <label class="block text-[13px] font-medium text-slate-600 mb-1" for="title">Tên đề/bộ bài</label>
                <input id="title" name="title" type="text" value="{{ old('title', $assessment->title) }}" required maxlength="255"
                       class="admin-input">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[13px] font-medium text-slate-600 mb-1" for="type">Loại</label>
                    {{-- SỬA 8/9 (5) (khách: "ẩn loại Tự luyện ở đề/bộ bài bên admin") — $types đã
                         bị lọc bỏ 'practice' theo cờ config('features.assessment_type_practice').
                         Nếu đề NÀY đang là "Tự luyện": KHÔNG đổ dropdown (loại đó không còn trong
                         danh sách, chọn bừa option đầu sẽ ÂM THẦM đổi loại đề + đổi luôn
                         content_mode sang PDF, xem ContentService::contentModeForType()) — hiện
                         loại hiện tại dạng chữ và gửi lại đúng giá trị cũ qua input ẩn. --}}
                    @if (! array_key_exists($assessment->type->value, $types))
                        <input type="hidden" name="type" value="{{ $assessment->type->value }}">
                        <p class="text-[13px] text-slate-500 py-2.5 px-1">
                            {{ $assessment->type->label() }}
                            <span class="text-xs text-slate-400">(loại này đang ẩn — giữ nguyên, không đổi được ở đây)</span>
                        </p>
                    @else
                        <x-ws.select id="type" name="type" required>
                            @foreach ($types as $value => $label)
                                <option value="{{ $value }}" @selected(old('type', $assessment->type->value) === $value)>{{ $label }}</option>
                            @endforeach
                        </x-ws.select>
                    @endif
                </div>
                {{-- SỬA 23/9 (khách: "thay vì nhập tổng điểm thì nhập điểm cho từng câu") — BỎ HẲN
                     ô "Tổng điểm" ở đây. Điểm đặt ở màn "Chọn câu hỏi", tổng là số cộng lại. --}}
                <div>
                    @php($itemCount = $assessment->items()->count())
                    <span class="block text-[13px] font-medium text-slate-600 mb-1">Tổng điểm</span>
                    <div class="flex flex-wrap items-center gap-2 rounded-xl border border-dashed border-sky-100 bg-sky-50/50 px-3 py-2.5">
                        <span class="text-[15px] font-bold text-blue-700">{{ $assessment->total_points }} điểm</span>
                        <span class="text-xs text-slate-500">({{ $itemCount }} câu)</span>
                        <a href="{{ route('admin.content.assessments.items.edit', $assessment->id) }}"
                           class="ml-auto inline-flex items-center gap-1 text-xs font-bold text-blue-600 hover:underline">
                            Sửa điểm từng câu <x-lucide name="chevron-right" class="h-3 w-3" />
                        </a>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[13px] font-medium text-slate-600 mb-1" for="duration_minutes">Thời gian làm bài (phút)</label>
                    <input id="duration_minutes" name="duration_minutes" type="number" min="0" value="{{ old('duration_minutes', $assessment->duration_minutes) }}"
                           class="admin-input">
                </div>
                <div>
                    <label class="block text-[13px] font-medium text-slate-600 mb-1" for="publish_answer_rule">Hiện đáp án</label>
                    <x-ws.select id="publish_answer_rule" name="publish_answer_rule" required>
                        @foreach ($publishAnswerRules as $value => $label)
                            <option value="{{ $value }}" @selected(old('publish_answer_rule', $assessment->publish_answer_rule->value) === $value)>{{ $label }}</option>
                        @endforeach
                    </x-ws.select>
                </div>
            </div>

            {{-- SỬA 2/10 — 6 ô mô tả đề cho bản mẫu UI mới (thẻ đề + màn chi tiết đề ngoài trang
                 Luyện tập), dùng chung partial với form bên giáo viên. --}}
            @include('partials.assessment-detail-fields')


            <div class="flex gap-3 pt-2">
                <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2.5 text-[13px] font-bold text-white shadow-sm shadow-blue-200 transition-colors hover:bg-blue-700 shadow-sm hover:bg-blue-700 transition">Lưu thay đổi</button>
                <a href="{{ route('admin.content.show', ['content' => $assessment->id, 'kind' => 'assessment']) }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-sky-100 bg-white px-4 py-2 text-xs font-bold text-slate-700 transition-colors hover:border-sky-200 hover:bg-sky-50">Huỷ</a>
            </div>
        </form>
    </div>
@endsection

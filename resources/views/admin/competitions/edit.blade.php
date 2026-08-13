{{--
  Route: admin.competitions.edit / .update / .archive
  Dữ liệu thật ($competition, $types, $statuses, $assessmentOptions) do
  CompetitionController::edit() truyền vào qua CompetitionService::editFormData().
  Slug KHÔNG cho sửa (giữ SEO/link công khai, giống Course/Product).
--}}
@extends('layouts.admin')

@section('title', 'Sửa cuộc thi')
@section('page-title', 'Sửa cuộc thi')

@section('content')
    @php
        $types = $types ?? []; $statuses = $statuses ?? []; $assessmentOptions = $assessmentOptions ?? [];
        $rankingRule = $competition->ranking_rule ?? [];
        $fmt = fn ($d) => $d ? $d->format('Y-m-d\TH:i') : '';
    @endphp

    <a href="{{ route('admin.competitions.show', $competition->id) }}" class="text-sm text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-rose-600">‹ Quay lại chi tiết</a>

    <x-page-header title="✏️ Sửa cuộc thi" :subtitle="$competition->title" />

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 p-6">
            <form method="POST" action="{{ route('admin.competitions.update', $competition->id) }}" class="space-y-4">
                @csrf
                @method('PUT')
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="title">Tên cuộc thi</label>
                        <input id="title" name="title" type="text" value="{{ old('title', $competition->title) }}" required maxlength="255"
                               class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="type">Loại</label>
                        <x-select id="type" name="type" required>
                            @foreach ($types as $value => $label)
                                <option value="{{ $value }}" @selected(old('type', $competition->type->value) === $value)>{{ $label }}</option>
                            @endforeach
                        </x-select>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="assessment_id">Đề/bộ bài tham chiếu (tùy chọn)</label>
                    <x-select id="assessment_id" name="assessment_id">
                        <option value="">— Không gắn đề —</option>
                        @foreach ($assessmentOptions as $a)
                            <option value="{{ $a->id }}" @selected((string) old('assessment_id', $competition->assessment_id) === (string) $a->id)>{{ $a->title }}</option>
                        @endforeach
                    </x-select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="rules">Thể lệ</label>
                    <textarea id="rules" name="rules" rows="4" maxlength="5000"
                              class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">{{ old('rules', $competition->rules) }}</textarea>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="starts_at">Bắt đầu</label>
                        <input id="starts_at" name="starts_at" type="datetime-local" value="{{ old('starts_at', $fmt($competition->starts_at)) }}"
                               class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="ends_at">Kết thúc</label>
                        <input id="ends_at" name="ends_at" type="datetime-local" value="{{ old('ends_at', $fmt($competition->ends_at)) }}"
                               class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="publish_result_at">Công bố kết quả</label>
                        <input id="publish_result_at" name="publish_result_at" type="datetime-local" value="{{ old('publish_result_at', $fmt($competition->publish_result_at)) }}"
                               class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="status">Trạng thái</label>
                    <x-select id="status" name="status" required>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $competition->status->value) === $value)>{{ $label }}</option>
                        @endforeach
                    </x-select>
                </div>

                <div class="rounded-lg bg-sky-50 border border-sky-100 p-4 space-y-3">
                    <p class="text-sm font-medium text-sky-700">Quy tắc bảng xếp hạng (11.2)</p>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1" for="scoring_note">Công thức điểm / kỳ tính</label>
                        <input id="scoring_note" name="scoring_note" type="text" value="{{ old('scoring_note', $rankingRule['scoring_note'] ?? '') }}" maxlength="500"
                               class="w-full rounded-lg border border-slate-200 text-sm p-2.5">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1" for="penalty_note">Penalty</label>
                        <input id="penalty_note" name="penalty_note" type="text" value="{{ old('penalty_note', $rankingRule['penalty_note'] ?? '') }}" maxlength="500"
                               class="w-full rounded-lg border border-slate-200 text-sm p-2.5">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1" for="tie_break_note">Quy tắc đồng điểm</label>
                        <input id="tie_break_note" name="tie_break_note" type="text" value="{{ old('tie_break_note', $rankingRule['tie_break_note'] ?? '') }}" maxlength="500"
                               class="w-full rounded-lg border border-slate-200 text-sm p-2.5">
                    </div>
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="submit" class="px-5 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium shadow-sm hover:bg-rose-700 transition">Lưu thay đổi</button>
                    <a href="{{ route('admin.competitions.show', $competition->id) }}" class="px-5 py-2.5 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium hover:border-rose-200 hover:text-rose-600 transition">Huỷ</a>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-2xl border border-rose-200 p-6 space-y-3">
            <h3 class="font-medium text-rose-700 flex items-center gap-2"><span>🗄️</span> Lưu trữ cuộc thi</h3>
            <p class="text-sm text-slate-500">Không xóa dữ liệu — chỉ chuyển trạng thái "Lưu trữ" (11.1), khớp bước cuối vòng đời cuộc thi.</p>
            <form method="POST" action="{{ route('admin.competitions.archive', $competition->id) }}" onsubmit="return confirm('Xác nhận lưu trữ cuộc thi này?');">
                @csrf
                <button type="submit" class="w-full px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium">Lưu trữ cuộc thi</button>
            </form>
        </div>
    </div>
@endsection

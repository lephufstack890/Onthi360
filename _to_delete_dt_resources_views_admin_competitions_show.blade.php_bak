{{--
  Route: admin.competitions.show
  Spec: 11.1 (vòng đời cuộc thi) + 11.2 (bảng xếp hạng — phạm vi rõ, không trộn số liệu khác phạm vi)
  + note họp 13/8, mục 1 (đơn vị tổ chức + cố vấn/đồng hành cho cuộc thi bên ngoài).
  $competition (Eloquent thật, withCount('leaderboardEntries'), with(['assessment', 'advisors'])) +
  $exams/$assessmentOptions (kỳ thi bên trong cuộc thi — App\Models\CompetitionExam) do
  CompetitionController::show() truyền vào qua CompetitionService::showData()/examSittingsData().
  Mỗi kỳ thi có bảng xếp hạng riêng (scope=competition_exam); nút "Tính tổng từ các kỳ thi"
  cộng điểm mọi kỳ thi theo user_id thành bảng TỔNG (scope=competition) — xem
  CompetitionService::recomputeAggregateFromExams().
--}}
@extends('layouts.admin')

@section('title', $competition->title)
@section('page-title', 'Chi tiết cuộc thi')

@section('content')
    @php
        $statusMeta = [
            'upcoming' => ['label' => 'Sắp diễn ra', 'tone' => 'info'],
            'ongoing' => ['label' => 'Đang diễn ra', 'tone' => 'warning'],
            'pending_publish' => ['label' => 'Chờ công bố', 'tone' => 'neutral'],
            'published' => ['label' => 'Đã công bố', 'tone' => 'success'],
            'archived' => ['label' => 'Lưu trữ', 'tone' => 'neutral'],
        ];
        $statusValue = $competition->status->value;
        $meta = $statusMeta[$statusValue] ?? ['label' => $statusValue, 'tone' => 'neutral'];
        $rankingRule = $competition->ranking_rule ?? [];
        $exams = $exams ?? [];
        $assessmentOptions = $assessmentOptions ?? [];
        $competitionStatusMessage = match (session('status')) {
            'competition-created' => 'Đã tạo cuộc thi mới.',
            'competition-updated' => 'Đã lưu thay đổi.',
            'competition-archived' => 'Đã lưu trữ cuộc thi.',
            'exam-added' => 'Đã thêm kỳ thi.',
            'exam-updated' => 'Đã cập nhật kỳ thi.',
            'exam-deleted' => 'Đã xoá kỳ thi.',
            'aggregate-recomputed' => 'Đã tính lại bảng xếp hạng tổng từ các kỳ thi.',
            default => null,
        };
    @endphp
    @if ($competitionStatusMessage)
        @include('partials.toast-flash', ['type' => 'success', 'message' => $competitionStatusMessage])
    @endif
    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <a href="{{ route('admin.competitions.index') }}" class="text-sm text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-rose-600">‹ Quay lại Cuộc thi</a>

    <div class="rounded-3xl bg-gradient-to-br from-sky-100 via-white to-rose-50 p-6 lg:p-8 mb-6 flex items-start justify-between gap-4 flex-wrap">
        <div class="flex items-start gap-4">
            <x-icon-tile emoji="🏆" tone="rose" />
            <div>
                <div class="flex items-center gap-2 flex-wrap mb-1">
                    <h1 class="text-xl lg:text-2xl font-semibold text-slate-800">{{ $competition->title }}</h1>
                    <x-status-badge :tone="$meta['tone']">{{ $meta['label'] }}</x-status-badge>
                    @if ($competition->isExternallyOrganized())
                        <x-status-badge tone="warning">Bên ngoài tổ chức</x-status-badge>
                    @endif
                </div>
                <p class="text-sm text-slate-500">{{ $competition->type->value === 'contest' ? 'Cuộc thi' : 'Khảo sát' }}</p>
            </div>
        </div>
        <a href="{{ route('admin.competitions.edit', $competition->id) }}" class="px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium shadow-sm hover:bg-rose-700 transition">Sửa cuộc thi</a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <h2 class="font-medium text-slate-700 mb-2">Thể lệ</h2>
                <p class="text-sm text-slate-600 whitespace-pre-line">{{ $competition->rules ?: '— Chưa nhập —' }}</p>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <h2 class="font-medium text-slate-700 mb-2">Đề/bộ bài tham chiếu (11.1)</h2>
                <p class="text-sm text-slate-600">{{ $competition->assessment->title ?? '— Không gắn đề —' }}</p>
            </div>

            {{-- Kỳ thi (vòng) bên trong cuộc thi — 1 cuộc thi có thể gồm nhiều kỳ thi, mỗi
                 kỳ thi tham chiếu 1 đề riêng và có bảng xếp hạng riêng. --}}
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <div class="flex items-center justify-between mb-3 flex-wrap gap-2">
                    <h2 class="font-medium text-slate-700">Kỳ thi ({{ count($exams) }})</h2>
                    @if (count($exams) > 0)
                        <form method="POST" action="{{ route('admin.competitions.recompute-aggregate', $competition->id) }}"
                              onsubmit="return confirm('Tính lại bảng xếp hạng tổng từ toàn bộ kỳ thi hiện có? Bảng tổng cũ sẽ bị ghi đè.');">
                            @csrf
                            <button type="submit" class="text-xs px-3 py-1.5 rounded-lg bg-amber-50 border border-amber-200 text-amber-700 font-medium hover:bg-amber-100 transition">🔄 Tính tổng từ các kỳ thi</button>
                        </form>
                    @endif
                </div>

                <div class="divide-y divide-slate-100 mb-4">
                    @forelse ($exams as $exam)
                        <details class="py-3 group">
                            <summary class="flex items-center justify-between gap-2 cursor-pointer list-none">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-slate-700 truncate">{{ $exam['title'] }}</p>
                                    <p class="text-xs text-slate-400">{{ $exam['assessmentTitle'] }} · {{ $exam['entriesCount'] }} lượt xếp hạng</p>
                                </div>
                                <span class="text-xs text-slate-400 shrink-0">Sửa ▾</span>
                            </summary>
                            <div class="mt-3 pl-0 space-y-3">
                                <form method="POST" action="{{ route('admin.competitions.exams.update', $exam['id']) }}" class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    @csrf
                                    @method('PUT')
                                    <div class="sm:col-span-2">
                                        <label class="block text-xs text-slate-500 mb-1">Tên kỳ thi (để trống sẽ dùng tên đề)</label>
                                        <input type="text" name="title" value="{{ $exam['hasCustomTitle'] ? $exam['title'] : '' }}" maxlength="255"
                                               class="w-full rounded-lg border border-slate-200 text-sm p-2">
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label class="block text-xs text-slate-500 mb-1">Đề tham chiếu</label>
                                        <x-select name="assessment_id" required>
                                            @foreach ($assessmentOptions as $opt)
                                                <option value="{{ $opt->id }}" @selected($opt->id === $exam['assessmentId'])>{{ $opt->title }}</option>
                                            @endforeach
                                        </x-select>
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-500 mb-1">Bắt đầu</label>
                                        <input type="datetime-local" name="starts_at" value="{{ $exam['startsAt']?->format('Y-m-d\TH:i') }}"
                                               class="w-full rounded-lg border border-slate-200 text-sm p-2">
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-500 mb-1">Kết thúc</label>
                                        <input type="datetime-local" name="ends_at" value="{{ $exam['endsAt']?->format('Y-m-d\TH:i') }}"
                                               class="w-full rounded-lg border border-slate-200 text-sm p-2">
                                    </div>
                                    <div class="sm:col-span-2 flex items-center gap-2">
                                        <button type="submit" class="px-3 py-1.5 rounded-lg bg-rose-600 text-white text-xs font-medium">Lưu</button>
                                        <a href="{{ route('admin.ranking.show', ['scope' => 'exam', 'id' => $exam['id']]) }}" class="text-xs text-slate-500 hover:text-rose-600">Xem bảng xếp hạng riêng ›</a>
                                    </div>
                                </form>
                                @if ($exam['entriesCount'] === 0)
                                    <form method="POST" action="{{ route('admin.competitions.exams.destroy', $exam['id']) }}"
                                          onsubmit="return confirm('Xoá kỳ thi này?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs text-rose-500 hover:text-rose-700">Xoá kỳ thi</button>
                                    </form>
                                @else
                                    <p class="text-xs text-slate-400">Đã có dữ liệu xếp hạng — không thể xoá trực tiếp.</p>
                                @endif
                            </div>
                        </details>
                    @empty
                        <p class="text-sm text-slate-400 py-2">Chưa có kỳ thi nào — thêm kỳ thi đầu tiên bên dưới.</p>
                    @endforelse
                </div>

                <form method="POST" action="{{ route('admin.competitions.exams.store', $competition->id) }}" class="rounded-xl bg-slate-50 border border-slate-200 p-4 space-y-2">
                    @csrf
                    <p class="text-xs font-medium text-slate-500 mb-1">+ Thêm kỳ thi mới</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <div class="sm:col-span-2">
                            <label class="block text-xs text-slate-500 mb-1">Tên kỳ thi (để trống sẽ dùng tên đề)</label>
                            <input type="text" name="title" maxlength="255" placeholder="VD: Vòng 1"
                                   class="w-full rounded-lg border border-slate-200 text-sm p-2 bg-white">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs text-slate-500 mb-1">Đề tham chiếu</label>
                            <x-select name="assessment_id" required>
                                <option value="">— Chọn đề —</option>
                                @foreach ($assessmentOptions as $opt)
                                    <option value="{{ $opt->id }}">{{ $opt->title }}</option>
                                @endforeach
                            </x-select>
                        </div>
                        <div>
                            <label class="block text-xs text-slate-500 mb-1">Bắt đầu</label>
                            <input type="datetime-local" name="starts_at" class="w-full rounded-lg border border-slate-200 text-sm p-2 bg-white">
                        </div>
                        <div>
                            <label class="block text-xs text-slate-500 mb-1">Kết thúc</label>
                            <input type="datetime-local" name="ends_at" class="w-full rounded-lg border border-slate-200 text-sm p-2 bg-white">
                        </div>
                    </div>
                    <button type="submit" class="px-3 py-1.5 rounded-lg bg-rose-600 text-white text-xs font-medium">+ Thêm kỳ thi</button>
                </form>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <h2 class="font-medium text-slate-700 mb-2">Đơn vị tổ chức</h2>
                @if ($competition->isExternallyOrganized())
                    <p class="text-sm text-slate-600 mb-3"><span class="text-slate-400">Tổ chức bởi:</span> {{ $competition->organizer_name ?: '— Chưa nêu —' }}</p>
                    <p class="text-xs text-slate-400 mb-1">Giáo viên cố vấn/đồng hành (tăng uy tín):</p>
                    @if ($competition->advisors->isNotEmpty())
                        <ul class="flex flex-wrap gap-2">
                            @foreach ($competition->advisors as $advisor)
                                <li class="px-2.5 py-1 rounded-full bg-amber-50 border border-amber-100 text-xs text-amber-700">{{ $advisor->name }}</li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-sm text-slate-400">— Chưa có giáo viên cố vấn —</p>
                    @endif
                @else
                    <p class="text-sm text-slate-600">Nội bộ (nền tảng tự tổ chức) — không bắt buộc cố vấn.</p>
                @endif
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <h2 class="font-medium text-slate-700 mb-2">Quy tắc bảng xếp hạng (11.2)</h2>
                <div class="space-y-1 text-sm text-slate-600">
                    <p><span class="text-slate-400">Công thức điểm / kỳ tính:</span> {{ $rankingRule['scoring_note'] ?? '— Chưa nêu —' }}</p>
                    <p><span class="text-slate-400">Penalty:</span> {{ $rankingRule['penalty_note'] ?? '— Chưa nêu —' }}</p>
                    <p><span class="text-slate-400">Đồng điểm:</span> {{ $rankingRule['tie_break_note'] ?? '— Chưa nêu —' }}</p>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="bg-white rounded-2xl border border-slate-200 p-5 space-y-2 text-sm">
                <h2 class="font-medium text-slate-700 mb-2">Thời gian</h2>
                <p><span class="text-slate-400">Bắt đầu:</span> {{ $competition->starts_at?->format('d/m/Y H:i') ?: '— Chưa đặt —' }}</p>
                <p><span class="text-slate-400">Kết thúc:</span> {{ $competition->ends_at?->format('d/m/Y H:i') ?: '— Chưa đặt —' }}</p>
                <p><span class="text-slate-400">Công bố kết quả:</span> {{ $competition->publish_result_at?->format('d/m/Y H:i') ?: '— Chưa đặt —' }}</p>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 p-5 text-sm">
                <h2 class="font-medium text-slate-700 mb-2">Bảng xếp hạng</h2>
                <p class="text-slate-600">{{ $competition->leaderboard_entries_count }} lượt xếp hạng tổng đã ghi nhận.</p>
                <a href="{{ route('admin.ranking.index') }}" class="text-rose-600 font-medium mt-2 inline-block">Xem Bảng xếp hạng ›</a>
            </div>
        </div>
    </div>
@endsection

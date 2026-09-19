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
        $statusValue = $competition->computedStatus()->value;
        $meta = $statusMeta[$statusValue] ?? ['label' => $statusValue, 'tone' => 'neutral'];
        $rankingRule = $competition->ranking_rule ?? [];
        $exams = $exams ?? [];
        $assessmentOptions = $assessmentOptions ?? [];
        $competitionStatusMessage = match (session('status')) {
            'competition-created' => 'Đã tạo cuộc thi mới.',
            'competition-updated' => 'Đã lưu thay đổi.',
            'competition-archived' => 'Đã lưu trữ cuộc thi.',
            'competition-unarchived' => 'Đã bỏ lưu trữ — trạng thái tính lại theo giờ hiện tại.',
            'exam-added' => 'Đã thêm kỳ thi.',
            'exam-updated' => 'Đã cập nhật kỳ thi.',
            'exam-deleted' => 'Đã xoá kỳ thi.',
            'aggregate-recomputed' => 'Đã tính lại bảng xếp hạng tổng từ các kỳ thi.',
            // SỬA 19/9 — duyệt đơn đăng ký cuộc thi.
            'registration-approved' => 'Đã duyệt đơn — học sinh vào được không gian thi.',
            'registration-rejected' => 'Đã từ chối đơn, đã ghi lý do và báo cho học sinh.',
            default => null,
        };

        // SỬA 19/9 — dữ liệu khối "Đơn đăng ký", xem Admin\CompetitionService::registrationsData().
        $registrationsReady = $registrationsReady ?? false;
        $pendingRegistrations = $pendingRegistrations ?? [];
        $decidedRegistrations = $decidedRegistrations ?? [];
        $approvedCount = $approvedCount ?? 0;
    @endphp
    @if ($competitionStatusMessage)
        @include('partials.toast-flash', ['type' => 'success', 'message' => $competitionStatusMessage])
    @endif
    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <a href="{{ route('admin.competitions.index') }}" class="text-[13px] text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-blue-600">‹ Quay lại Cuộc thi</a>

    <div class="rounded-3xl border border-sky-100 bg-gradient-to-br from-sky-50 via-white to-blue-50 p-5 lg:p-6 mb-4 shadow-[0_2px_8px_rgba(0,90,180,.04)] flex items-start justify-between gap-4 flex-wrap">
        <div class="flex items-start gap-4">
            <x-ws.icon-tile emoji="🏆" tone="rose" />
            <div>
                <div class="flex items-center gap-2 flex-wrap mb-1">
                    <h1 class="text-xl lg:text-2xl font-semibold text-slate-800">{{ $competition->title }}</h1>
                    {{-- TẠM ẨN 24/8: Khách hiện không cần hiện Trạng thái ở đây (đang thừa) —
                         comment lại (KHÔNG xoá), $meta vẫn tính ở phần khai báo đầu file, chỉ không hiện.
                    <x-ws.badge :tone="$meta['tone']">{{ $meta['label'] }}</x-ws.badge>
                    --}}
                    @if ($competition->isExternallyOrganized())
                        <x-ws.badge tone="warning">Bên ngoài tổ chức</x-ws.badge>
                    @endif
                </div>
                <p class="text-[13px] text-slate-500">{{ $competition->type->value === 'contest' ? 'Cuộc thi' : 'Khảo sát' }}</p>
            </div>
        </div>
        <a href="{{ route('admin.competitions.edit', $competition->id) }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white shadow-sm shadow-blue-200 transition-colors hover:bg-blue-700 shadow-sm hover:bg-blue-700 transition">Sửa cuộc thi</a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-4 sm:p-5">
                <h2 class="font-medium text-slate-700 mb-2">Thể lệ</h2>
                <p class="text-[13px] text-slate-600 whitespace-pre-line">{{ $competition->rules ?: '— Chưa nhập —' }}</p>
            </div>

            <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-4 sm:p-5">
                <h2 class="font-medium text-slate-700 mb-2">Đề/bộ bài tham chiếu (11.1)</h2>
                <p class="text-[13px] text-slate-600">{{ $competition->assessment->title ?? '— Không gắn đề —' }}</p>
            </div>

            {{-- Kỳ thi (vòng) bên trong cuộc thi — 1 cuộc thi có thể gồm nhiều kỳ thi, mỗi
                 kỳ thi tham chiếu 1 đề riêng và có bảng xếp hạng riêng. --}}
            <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-4 sm:p-5">
                <div class="flex items-center justify-between mb-3 flex-wrap gap-2">
                    <h2 class="font-medium text-slate-700">Kỳ thi ({{ count($exams) }})</h2>
                    @if (count($exams) > 0)
                        <form method="POST" action="{{ route('admin.competitions.recompute-aggregate', $competition->id) }}"
                              onsubmit="return confirm('Tính lại bảng xếp hạng tổng từ toàn bộ kỳ thi hiện có? Bảng tổng cũ sẽ bị ghi đè.');">
                            @csrf
                            <button type="submit" class="text-xs px-3 py-1.5 rounded-xl bg-amber-50 border border-amber-200 text-amber-700 font-medium hover:bg-amber-100 transition">🔄 Tính tổng từ các kỳ thi</button>
                        </form>
                    @endif
                </div>

                <div class="divide-y divide-slate-100 mb-4">
                    @forelse ($exams as $exam)
                        <details class="py-3 group">
                            <summary class="flex items-center justify-between gap-2 cursor-pointer list-none">
                                <div class="min-w-0">
                                    <p class="text-[13px] font-medium text-slate-700 truncate">{{ $exam['title'] }}</p>
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
                                               class="admin-input">
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label class="block text-xs text-slate-500 mb-1">Đề tham chiếu</label>
                                        <x-ws.select name="assessment_id" required>
                                            @foreach ($assessmentOptions as $opt)
                                                <option value="{{ $opt->id }}" @selected($opt->id === $exam['assessmentId'])>{{ $opt->title }}</option>
                                            @endforeach
                                        </x-ws.select>
                                    </div>
                                    <x-date-time-fields name="starts_at" label="Bắt đầu"
                                                         :dayValue="$exam['startsAt']?->format('d')"
                                                         :monthValue="$exam['startsAt']?->format('m')"
                                                         :yearValue="$exam['startsAt']?->format('Y')"
                                                         :hourValue="$exam['startsAt']?->format('H')"
                                                         :minuteValue="$exam['startsAt']?->format('i')" />
                                    <x-date-time-fields name="ends_at" label="Kết thúc"
                                                         :dayValue="$exam['endsAt']?->format('d')"
                                                         :monthValue="$exam['endsAt']?->format('m')"
                                                         :yearValue="$exam['endsAt']?->format('Y')"
                                                         :hourValue="$exam['endsAt']?->format('H')"
                                                         :minuteValue="$exam['endsAt']?->format('i')" />
                                    <div class="sm:col-span-2 flex items-center gap-2">
                                        <button type="submit" class="px-3 py-1.5 rounded-xl bg-blue-600 text-white text-xs font-medium">Lưu</button>
                                        <a href="{{ route('admin.ranking.show', ['scope' => 'exam', 'id' => $exam['id']]) }}" class="text-xs text-slate-500 hover:text-blue-600">Xem bảng xếp hạng riêng ›</a>
                                    </div>
                                </form>
                                @if ($exam['entriesCount'] === 0)
                                    <form method="POST" action="{{ route('admin.competitions.exams.destroy', $exam['id']) }}"
                                          onsubmit="return confirm('Xoá kỳ thi này?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs font-bold text-blue-600 transition-colors hover:text-blue-700">Xoá kỳ thi</button>
                                    </form>
                                @else
                                    <p class="text-xs text-slate-400">Đã có dữ liệu xếp hạng — không thể xoá trực tiếp.</p>
                                @endif
                            </div>
                        </details>
                    @empty
                        <p class="text-[13px] text-slate-400 py-2">Chưa có kỳ thi nào — thêm kỳ thi đầu tiên bên dưới.</p>
                    @endforelse
                </div>

                <form method="POST" action="{{ route('admin.competitions.exams.store', $competition->id) }}" class="rounded-xl bg-slate-50 border border-sky-100 p-4 space-y-2">
                    @csrf
                    <p class="text-xs font-medium text-slate-500 mb-1">+ Thêm kỳ thi mới</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <div class="sm:col-span-2">
                            <label class="block text-xs text-slate-500 mb-1">Tên kỳ thi (để trống sẽ dùng tên đề)</label>
                            <input type="text" name="title" maxlength="255" placeholder="VD: Vòng 1"
                                   class="w-full rounded-xl border border-sky-100 text-[13px] p-2 bg-white">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs text-slate-500 mb-1">Đề tham chiếu</label>
                            <x-ws.select name="assessment_id" required>
                                <option value="">— Chọn đề —</option>
                                @foreach ($assessmentOptions as $opt)
                                    <option value="{{ $opt->id }}">{{ $opt->title }}</option>
                                @endforeach
                            </x-ws.select>
                        </div>
                        <x-date-time-fields name="starts_at" label="Bắt đầu" />
                        <x-date-time-fields name="ends_at" label="Kết thúc" />
                    </div>
                    <button type="submit" class="px-3 py-1.5 rounded-xl bg-blue-600 text-white text-xs font-medium">+ Thêm kỳ thi</button>
                </form>
            </div>

            <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-4 sm:p-5">
                <h2 class="font-medium text-slate-700 mb-2">Đơn vị tổ chức</h2>
                @if ($competition->isExternallyOrganized())
                    <p class="text-[13px] text-slate-600 mb-3"><span class="text-slate-400">Tổ chức bởi:</span> {{ $competition->organizer_name ?: '— Chưa nêu —' }}</p>
                    <p class="text-xs text-slate-400 mb-1">Giáo viên cố vấn/đồng hành (tăng uy tín):</p>
                    @if ($competition->advisors->isNotEmpty())
                        <ul class="flex flex-wrap gap-2">
                            @foreach ($competition->advisors as $advisor)
                                <li class="px-2.5 py-1 rounded-full bg-amber-50 border border-amber-100 text-xs text-amber-700">{{ $advisor->name }}</li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-[13px] text-slate-400">— Chưa có giáo viên cố vấn —</p>
                    @endif
                @else
                    <p class="text-[13px] text-slate-600">Nội bộ (nền tảng tự tổ chức) — không bắt buộc cố vấn.</p>
                @endif
            </div>

            <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-4 sm:p-5">
                <h2 class="font-medium text-slate-700 mb-2">Quy tắc bảng xếp hạng (11.2)</h2>
                <div class="space-y-1 text-[13px] text-slate-600">
                    <p><span class="text-slate-400">Công thức điểm / kỳ tính:</span> {{ $rankingRule['scoring_note'] ?? '— Chưa nêu —' }}</p>
                    <p><span class="text-slate-400">Penalty:</span> {{ $rankingRule['penalty_note'] ?? '— Chưa nêu —' }}</p>
                    <p><span class="text-slate-400">Đồng điểm:</span> {{ $rankingRule['tie_break_note'] ?? '— Chưa nêu —' }}</p>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            {{--
              TẠM ẨN 24/8: Khách hiện không cần hiện Bắt đầu/Kết thúc/Công bố kết quả ở trang
              chi tiết cuộc thi (đang thừa) — comment lại (KHÔNG xoá) để sau này cần dùng lại
              thì chỉ cần bỏ comment, không phải viết lại từ đầu.

            <div class="bg-white rounded-3xl border border-sky-100 p-5 space-y-2 text-[13px]">
                <h2 class="font-medium text-slate-700 mb-2">Thời gian</h2>
                <p><span class="text-slate-400">Bắt đầu:</span> {{ $competition->starts_at?->format('d/m/Y H:i') ?: '— Chưa đặt —' }}</p>
                <p><span class="text-slate-400">Kết thúc:</span> {{ $competition->ends_at?->format('d/m/Y H:i') ?: '— Chưa đặt —' }}</p>
                <p><span class="text-slate-400">Công bố kết quả:</span> {{ $competition->publish_result_at?->format('d/m/Y H:i') ?: '— Chưa đặt —' }}</p>
            </div>
            --}}
            {{-- ══════ SỬA 19/9 — ĐƠN ĐĂNG KÝ THAM GIA (khách: "click đăng ký tham gia thì
                 admin sẽ duyệt") — dựng theo khuôn khối "Yêu cầu vào lớp chờ duyệt" ở
                 teacher/classes/show.blade.php để hai màn duyệt trong hệ thống giống nhau. ══════ --}}
            <div class="bg-white rounded-3xl border border-sky-100 p-5 text-[13px]">
                <div class="flex items-center justify-between gap-2 mb-3">
                    <h2 class="font-medium text-slate-700">Đơn đăng ký tham gia</h2>
                    <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700">{{ $approvedCount }} đã duyệt</span>
                </div>

                @if (! $registrationsReady)
                    {{-- Chưa chạy migration thì nói thẳng việc cần làm, đừng để bảng trống khiến
                         admin tưởng chưa ai đăng ký. --}}
                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
                        <p class="font-bold">Chưa chạy migration cho tính năng đăng ký cuộc thi</p>
                        <p class="mt-1">Chạy <code class="rounded bg-amber-100 px-1.5 py-0.5 font-mono">php artisan migrate</code> trên máy chủ rồi tải lại trang.</p>
                    </div>
                @else
                    @if (count($pendingRegistrations) === 0)
                        <p class="text-slate-400">Chưa có đơn nào đang chờ duyệt.</p>
                    @else
                        <div class="space-y-2">
                            @foreach ($pendingRegistrations as $r)
                                <div class="rounded-xl border border-amber-200 bg-amber-50/60 p-3" x-data="{ open: false, reason: '' }">
                                    <p class="font-semibold text-slate-700">{{ $r['student'] }}</p>
                                    <p class="text-xs text-slate-500">{{ $r['email'] }} · gửi {{ $r['requestedAt'] }}</p>

                                    <div class="mt-2 flex flex-wrap items-center gap-2">
                                        <form method="POST" action="{{ route('admin.competitions.registrations.approve', [$competition->id, $r['id']]) }}">
                                            @csrf
                                            <button type="submit" class="inline-flex min-h-9 items-center gap-1.5 rounded-xl bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white transition hover:bg-emerald-700">Duyệt</button>
                                        </form>
                                        <button type="button" @click="open = ! open" class="text-xs font-bold text-blue-600" x-text="open ? 'Đóng' : 'Từ chối'"></button>
                                    </div>

                                    <form x-show="open" x-cloak method="POST" action="{{ route('admin.competitions.registrations.reject', [$competition->id, $r['id']]) }}" class="mt-2 space-y-2">
                                        @csrf
                                        <textarea name="reason" x-model="reason" rows="2" maxlength="255" class="admin-input" placeholder="Lý do từ chối (không bắt buộc, học sinh sẽ đọc được)"></textarea>
                                        <button type="submit" class="w-full rounded-xl bg-blue-600 px-3 py-1.5 text-xs font-bold text-white transition hover:bg-blue-700">Xác nhận từ chối</button>
                                    </form>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if (count($decidedRegistrations) > 0)
                        <details class="mt-3">
                            <summary class="cursor-pointer text-xs font-bold text-slate-500">Đã xử lý ({{ count($decidedRegistrations) }})</summary>
                            <div class="mt-2 space-y-1.5">
                                @foreach ($decidedRegistrations as $r)
                                    <div class="flex items-start justify-between gap-2 rounded-xl border border-sky-100 px-3 py-2">
                                        <div class="min-w-0">
                                            <p class="truncate font-medium text-slate-700">{{ $r['student'] }}</p>
                                            <p class="text-xs text-slate-400">{{ $r['decidedAt'] }} · {{ $r['decidedBy'] }}</p>
                                            @if ($r['rejectReason'])
                                                <p class="text-xs text-blue-600">Lý do: {{ $r['rejectReason'] }}</p>
                                            @endif
                                        </div>
                                        <x-ws.badge :tone="$r['tone']">{{ $r['statusLabel'] }}</x-ws.badge>
                                    </div>
                                @endforeach
                            </div>
                        </details>
                    @endif
                @endif
            </div>

            <div class="bg-white rounded-3xl border border-sky-100 p-5 text-[13px]">
                <h2 class="font-medium text-slate-700 mb-2">Bảng xếp hạng</h2>
                <p class="text-slate-600">{{ $competition->leaderboard_entries_count }} lượt xếp hạng tổng đã ghi nhận.</p>
                <a href="{{ route('admin.ranking.index') }}" class="text-blue-600 font-medium mt-2 inline-block">Xem Bảng xếp hạng ›</a>
            </div>
        </div>
    </div>
@endsection

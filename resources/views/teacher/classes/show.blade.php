@extends('layouts.teacher')

@section('title', 'Chi tiết lớp')
@section('page-title', 'Chi tiết lớp')

@section('content')
    @php
        $tab = $tab ?? 'overview';
        $materials = $materials ?? [];
        $members = $members ?? collect();
        $studentsCount = $studentsCount ?? 0;
        $courseTitle = $classRoom->course->title ?? '';
        $className = $classRoom->name ?? '';
        $nextSessionLabel = isset($nextSession) && $nextSession ? 'Buổi tới: '.$nextSession->starts_at->format('d/m H:i') : 'Chưa có buổi học sắp tới';
        $ratingAverage = $ratingSummary->avg_rating ?? 0;
        $ratingCount = $ratingSummary->review_count ?? 0;
    @endphp

    <a href="{{ route('teacher.classes.index') }}" class="text-sm text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-rose-600">‹ Quay lại Lớp học</a>

    @if (session('status') === 'class-created')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã tạo lớp thành công — bạn là giáo viên chính của lớp này.'])
    @endif

    <div class="rounded-3xl bg-gradient-to-br from-sky-50 via-white to-emerald-50 border border-slate-200 p-6 lg:p-8 mb-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-start gap-4">
                <div class="w-14 h-14 rounded-2xl bg-white flex items-center justify-center text-3xl shrink-0 shadow-sm">🏫</div>
                <div>
                    <p class="text-xs font-medium text-sky-600 uppercase tracking-wide">{{ $courseTitle }}</p>
                    <h1 class="text-xl lg:text-2xl font-semibold text-slate-800 mt-1">{{ $className }}</h1>
                    <p class="text-sm text-slate-500 mt-1">👥 {{ $studentsCount }} học sinh · 🗓 {{ $nextSessionLabel }}</p>
                </div>
            </div>
            <div class="w-40">
                <x-progress-bar :percent="$completion ?? 0" label="Hoàn thành chung (theo buổi học)" tone="info" />
                @if (($completionTotalSessions ?? 0) > 0)
                    <p class="text-[11px] text-slate-400 mt-1">{{ $completionEndedSessions }}/{{ $completionTotalSessions }} buổi đã học</p>
                @endif
            </div>
        </div>
    </div>

    <x-tabs :tabs="$tabsData" />

    @if ($tab === 'materials')
        {{-- SỬA 31/8 (khách yêu cầu — "thêm học liệu thì thêm cả cuốn sách/chuyên đề/bộ đề,
             có 3 loại để chọn, chọn xong list ra để giáo viên chọn"): mỗi lần "Thêm vào lớp"
             giờ gắn NGUYÊN 1 sản phẩm (không còn gắn 1 chương/mục lẻ như trước) — dữ liệu
             $attachableProducts đã nhóm sẵn theo 3 loại ở
             Teacher\ClassRoomService::attachableProducts(). Bước chọn: bấm loại (Alpine
             x-data, không cần route/logic mới) rồi chọn đúng sản phẩm trong danh sách hiện
             ra, y hệt cách "Tài liệu của tôi" đã làm cho lưới thẻ (không phải điều hướng
             trang khác). --}}
        @php
            $attachableProducts = $attachableProducts ?? [];
            $hasAnyAttachable = collect($attachableProducts)->sum(fn ($g) => count($g['products'])) > 0;
            $defaultType = null;
            foreach ($attachableProducts as $typeKey => $group) {
                if (count($group['products']) > 0) { $defaultType = $typeKey; break; }
            }
            $defaultType = $defaultType ?? array_key_first($attachableProducts);
        @endphp

        @if (session('status') === 'material-attached')
            @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã thêm học liệu vào lớp — học sinh trong lớp xem được ngay, không cần tự mua riêng.'])
        @elseif (session('status') === 'material-detached')
            @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã gỡ học liệu — lịch sử bài làm cũ vẫn giữ nguyên (8.2).'])
        @endif
        @if ($errors->any())
            @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
        @endif

        <div class="space-y-3">
            @forelse ($materials as $m)
                <div class="bg-white rounded-2xl border border-slate-200 p-4 flex items-center justify-between flex-wrap gap-3">
                    <div class="flex items-center gap-3">
                        <x-icon-tile emoji="📚" tone="sky" />
                        <div>
                            <p class="font-medium text-slate-700">{{ $m['title'] }}</p>
                            <p class="text-xs mt-1 flex items-center gap-2">
                                @if ($m['typeLabel'])
                                    <span class="text-slate-400">{{ $m['typeLabel'] }}</span>
                                @endif
                                <x-status-badge :tone="$m['tone']">{{ $m['scope'] }}</x-status-badge>
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 text-sm">
                        <x-status-badge tone="success">{{ $m['linkedStatus'] }}</x-status-badge>
                        {{-- Xem NGUYÊN sản phẩm qua đúng route học sinh/giáo viên đang tải PDF
                             nội dung (access.resource, kind=content) — giáo viên đã có quyền
                             dạy sản phẩm này nên không bị chặn. Mở tab mới cùng kiểu với "Xem
                             đề bài ↗" ở mục Bài tập bên dưới, tiện xem sách/chuyên đề/bộ đề
                             nhiều cuốn cùng lúc. --}}
                        @if ($m['hasContent'] ?? false)
                            <a href="{{ route('access.resource', ['product' => $m['productId'], 'kind' => 'content']) }}" target="_blank" rel="noopener" class="text-rose-600 font-medium">Xem ↗</a>
                        @endif
                        <form method="POST" action="{{ route('teacher.classes.materials.detach', ['class' => $classRoom->id, 'classMaterial' => $m['id']]) }}" class="inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-rose-500">Gỡ</button>
                        </form>
                    </div>
                </div>
            @empty
                <x-empty-state title="Lớp chưa gắn học liệu nào" />
            @endforelse

            @if (empty($attachableProducts))
                <div class="rounded-2xl border-2 border-dashed border-slate-200 text-slate-400 text-sm py-4 text-center">
                    Không có sách/chuyên đề/bộ đề nào bạn còn quyền dạy để thêm — quyền dạy (teacher_teaching) đã hết hạn hoặc chưa được cấp (7.2).
                </div>
            @else
                <div class="rounded-2xl border-2 border-dashed border-slate-200 p-4" x-data="{ type: '{{ $defaultType }}' }">
                    <p class="text-sm text-slate-600 mb-3">+ Thêm học liệu vào lớp — chọn loại rồi chọn đúng cuốn (chỉ hiện sách/chuyên đề/bộ đề bạn còn quyền dạy còn hạn, 8.2):</p>

                    <div class="flex flex-wrap gap-2 mb-3">
                        @foreach ($attachableProducts as $typeKey => $group)
                            <button type="button" @click="type = '{{ $typeKey }}'"
                                    :class="type === '{{ $typeKey }}' ? 'bg-rose-600 text-white' : 'bg-white text-slate-600 border border-slate-200'"
                                    class="px-3 py-1.5 rounded-lg text-sm font-medium transition">
                                {{ $group['label'] }} ({{ count($group['products']) }})
                            </button>
                        @endforeach
                    </div>

                    @if (! $hasAnyAttachable)
                        <p class="text-sm text-slate-400 text-center py-3">Mọi sách/chuyên đề/bộ đề bạn còn quyền dạy đã gắn hết vào lớp này rồi.</p>
                    @endif

                    @foreach ($attachableProducts as $typeKey => $group)
                        <div x-show="type === '{{ $typeKey }}'" x-cloak class="space-y-2">
                            @forelse ($group['products'] as $p)
                                <form method="POST" action="{{ route('teacher.classes.materials.attach', $classRoom->id) }}" class="flex items-center justify-between gap-3 bg-slate-50 rounded-lg px-3 py-2">
                                    @csrf
                                    <input type="hidden" name="product_id" value="{{ $p['id'] }}">
                                    <div class="min-w-0">
                                        <p class="text-sm text-slate-700 truncate">{{ $p['title'] }}</p>
                                        <p class="text-xs text-slate-400">Dùng được ở mọi lớp phụ trách đến {{ $p['expiresAtLabel'] }}</p>
                                    </div>
                                    <button type="submit" class="px-3 py-1.5 rounded-lg bg-rose-600 text-white text-xs font-medium shrink-0">Thêm vào lớp</button>
                                </form>
                            @empty
                                <p class="text-sm text-slate-400 text-center py-3">Không còn {{ mb_strtolower($group['label']) }} nào để thêm.</p>
                            @endforelse
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @elseif ($tab === 'schedule')
        @php $sessions = $sessions ?? []; @endphp

        @if (session('status') === 'session-created')
            @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã tạo buổi học mới.'])
        @endif
        @if ($errors->any())
            @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
        @endif

        <div class="bg-white rounded-2xl border border-slate-200 p-5 mb-4">
            <p class="text-sm font-medium text-slate-600 mb-3">+ Tạo buổi học mới</p>
            <form method="POST" action="{{ route('teacher.schedule.store') }}" x-data="{ startsDate: '{{ old('starts_date', '') }}', endsDate: '{{ old('ends_date', '') }}' }" class="space-y-4">
                @csrf
                <input type="hidden" name="class_room_id" value="{{ $classRoom->id }}">
                <input type="hidden" name="back_to_class" value="1">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1" for="topic">Chủ đề buổi học</label>
                        <input id="topic" type="text" name="topic" maxlength="255" value="{{ old('topic') }}" placeholder="Ví dụ: Ôn tập chương 3" class="w-full rounded-lg border border-slate-200 text-sm p-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1" for="location">Địa điểm/link</label>
                        <input id="location" type="text" name="location" maxlength="255" value="{{ old('location') }}" placeholder="Phòng học hoặc link online" class="w-full rounded-lg border border-slate-200 text-sm p-2.5">
                    </div>
                </div>

                @include('partials.session-datetime-fields')

                <div>
                    <button type="submit" class="px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium">Tạo buổi học</button>
                </div>
            </form>
        </div>

        <x-data-table :columns="['Thời gian (bắt đầu - kết thúc)', 'Chủ đề', 'Địa điểm', 'Trạng thái', 'Điểm danh', '']">
            @forelse ($sessions as $s)
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3 text-slate-700">{{ $s['timeRangeLabel'] }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $s['topic'] ?? '—' }}</td>
                    <td class="px-4 py-3 text-slate-400">{{ $s['location'] ?? '—' }}</td>
                    <td class="px-4 py-3"><x-status-badge :tone="$s['timeStatusTone']">{{ $s['timeStatusLabel'] }}</x-status-badge></td>
                    <td class="px-4 py-3"><x-status-badge :tone="$s['attendanceTaken'] ? 'success' : 'warning'">{{ $s['attendanceSummary'] }}</x-status-badge></td>
                    <td class="px-4 py-3 text-right"><a href="{{ route('teacher.schedule.attendance', $s['id']) }}" class="text-rose-600 font-medium">Điểm danh</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-6 text-center text-slate-400">Lớp chưa có buổi học nào — tạo buổi học đầu tiên ở trên.</td></tr>
            @endforelse
        </x-data-table>
    @elseif ($tab === 'assign')
        @php
            $assignments = $assignments ?? [];
            $assignableAssessments = $assignableAssessments ?? [];
        @endphp

        {{-- SỬA 24/8 — khách yêu cầu: giao đề ngay tại đây, chỉ cần chọn đề có sẵn (lớp đã
             biết trước qua trang này) — không còn giao được từ trang "Bài tập & Đề" nữa. --}}
        @if (session('status') === 'class-exam-assigned')
            @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã giao đề cho lớp này.'])
        @endif
        @if ($errors->any())
            @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
        @endif

        <div class="bg-white rounded-2xl border border-slate-200 p-5 mb-4 space-y-3">
            <p class="text-sm text-slate-500">Giao đề dùng cho kiểm tra có thời điểm mở-đóng, hạn nộp riêng (8.4). Chưa có đề? Tạo ở trang <a href="{{ route('teacher.assessments.create') }}" class="text-rose-600 font-medium">Bài tập & Đề</a> trước, rồi quay lại đây để chọn và giao.</p>

            @if (empty($assignableAssessments))
                <p class="text-sm text-slate-400">Bạn chưa có đề nào — tạo đề ở "Bài tập & Đề" trước khi giao cho lớp này.</p>
            @else
                <form method="POST" action="{{ route('teacher.classes.assign', $classRoom->id) }}" class="rounded-xl bg-slate-50 border border-slate-200 p-4 space-y-2">
                    @csrf
                    <p class="text-xs font-medium text-slate-500 mb-1">+ Giao đề có sẵn cho lớp này</p>
                    <x-select name="assessment_id" required>
                        <option value="">— Chọn đề —</option>
                        @foreach ($assignableAssessments as $ass)
                            <option value="{{ $ass['id'] }}" @selected((string) old('assessment_id') === (string) $ass['id'])>{{ $ass['title'] }}{{ $ass['status'] === 'Nháp' ? ' (Nháp — tự phát hành khi giao)' : '' }}</option>
                        @endforeach
                    </x-select>
                    <div class="space-y-2">
                        @include('partials.optional-date-hour-minute-fields', ['prefix' => 'opens', 'label' => 'Mở lúc (tùy chọn)'])
                        @include('partials.optional-date-hour-minute-fields', ['prefix' => 'closes', 'label' => 'Đóng lúc (tùy chọn)'])
                        <p class="text-[11px] text-slate-400">Để trống Ngày nếu không giới hạn mốc thời gian đó.</p>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-600 mb-1" for="shift_count">Chia ca thi (tùy chọn — chống nghẽn khi đông thí sinh)</label>
                        <input id="shift_count" name="shift_count" type="number" min="1" max="20" value="{{ old('shift_count') }}"
                               class="w-full rounded-lg border border-slate-200 text-xs p-2" placeholder="VD: 3 (cần đủ cả Mở lúc + Đóng lúc)">
                    </div>
                    <textarea name="instructions" rows="2" class="w-full rounded-lg border border-slate-200 text-xs p-2" placeholder="Hướng dẫn làm bài (tùy chọn)...">{{ old('instructions') }}</textarea>
                    <p class="text-xs text-slate-400">Đề sẽ tự động phát hành nếu mọi câu đã đủ điều kiện (6.2), không hỗ trợ ngoại lệ từng học sinh (8.4).</p>
                    <button type="submit" class="px-3 py-1.5 rounded-lg bg-rose-600 text-white text-xs font-medium">Giao đề cho lớp</button>
                </form>
            @endif
        </div>

        <x-data-table :columns="['Tên đề', 'Mở lúc', 'Đóng lúc', 'Trạng thái', '']">
            @forelse ($assignments as $ag)
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3 font-medium text-slate-700">
                        {{ $ag['title'] }}
                        @if ($ag['instructions'])
                            <p class="text-xs text-slate-400 font-normal mt-0.5">{{ $ag['instructions'] }}</p>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-slate-500">{{ $ag['opensAtLabel'] }}</td>
                    <td class="px-4 py-3 text-slate-500">{{ $ag['closesAtLabel'] }}</td>
                    <td class="px-4 py-3"><x-status-badge :tone="$ag['statusTone']">{{ $ag['statusLabel'] }}</x-status-badge></td>
                    <td class="px-4 py-3 text-right"><a href="{{ route('teacher.results.index', ['class' => $classRoom->id, 'assessment' => $ag['id']]) }}" class="text-rose-600 font-medium">Xem kết quả</a></td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-6 text-center text-slate-400">Lớp chưa có đề nào được giao — chọn đề ở khung trên để giao.</td></tr>
            @endforelse
        </x-data-table>
    @elseif ($tab === 'results')
        <div class="bg-white rounded-2xl border border-slate-200 p-6 flex items-start gap-4">
            <x-icon-tile emoji="📈" tone="emerald" />
            <a href="{{ route('teacher.results.index') }}" class="text-sm text-rose-600 font-medium self-center">Xem kết quả chi tiết theo lớp này ›</a>
        </div>
    @elseif ($tab === 'members')
        <div class="rounded-2xl bg-rose-50/60 border border-rose-100 p-5 mb-4 flex items-center justify-between gap-4 flex-wrap">
            <div>
                <p class="text-sm font-medium text-slate-700">Mã lớp để học sinh tự tham gia</p>
                <p class="text-xs text-slate-400 mt-0.5">Chia sẻ mã này cho học sinh — các em tự nhập ở trang Khóa học của tôi để vào lớp.</p>
            </div>
            <span class="text-base font-mono font-semibold px-3 py-1.5 rounded-lg bg-white border border-rose-200 text-rose-600 shrink-0">{{ $classRoom->code }}</span>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <p class="text-xs text-slate-400 mb-3">{{ $members->count() }} học sinh</p>
            <div class="space-y-2 max-h-96 overflow-y-auto">
                @foreach ($members as $m)
                    <div class="flex items-center gap-3 py-1.5">
                        <img src="https://ui-avatars.com/api/?name={{ urlencode($m->name) }}&background=e0f2fe&color=0369a1&size=64&bold=true"
                             alt="{{ $m->name }}" class="w-8 h-8 rounded-full shrink-0">
                        <p class="text-sm text-slate-600">{{ $m->name }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    @else
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="bg-white rounded-2xl border border-slate-200 p-5 flex items-start gap-3">
                <x-icon-tile emoji="⭐" tone="amber" />
                <div>
                    <h3 class="font-medium text-slate-700 mb-2">Rating summary nội bộ</h3>
                    <x-rating-summary :average="$ratingAverage" :count="$ratingCount" />
                </div>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 p-5 flex items-start gap-3">
                <x-icon-tile emoji="🗓️" tone="sky" />
                <div>
                    <h3 class="font-medium text-slate-700 mb-2">Buổi học gần nhất</h3>
                    <p class="text-sm text-slate-500">{{ $nextSessionLabel }}</p>
                </div>
            </div>
        </div>
    @endif

    @push('scripts')
        {{-- SỬA 31/8 — cần cho x-show/x-cloak ở khối "chọn loại rồi chọn sản phẩm" (tab
             "Học liệu") phía trên: ẩn nội dung Alpine ngay từ đầu (trước khi Alpine kịp
             chạy), tránh chớp nháy hiện cả 3 loại cùng lúc rồi mới ẩn 2 loại còn lại. --}}
        <style>
            [x-cloak] { display: none !important; }
        </style>
    @endpush
@endsection

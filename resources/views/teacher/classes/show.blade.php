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

    <a href="{{ route('teacher.classes.index') }}" class="text-[13px] text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-blue-600">‹ Quay lại Lớp học</a>

    @if (session('status') === 'class-created')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã tạo lớp thành công — bạn là giáo viên chính của lớp này.'])
    @endif

    <div class="rounded-3xl border border-sky-100 bg-gradient-to-br from-sky-50 via-white to-blue-50 shadow-[0_2px_8px_rgba(0,90,180,.04)] p-5 lg:p-6 mb-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-start gap-4">
                <div class="w-14 h-14 rounded-3xl bg-white flex items-center justify-center text-3xl shrink-0 shadow-sm">🏫</div>
                <div>
                    <p class="text-xs font-medium text-sky-600 uppercase tracking-wide">{{ $courseTitle }}</p>
                    <h1 class="text-xl lg:text-2xl font-semibold text-slate-800 mt-1">{{ $className }}</h1>
                    <p class="text-[13px] text-slate-500 mt-1"><x-lucide name="users" class="inline h-3.5 w-3.5 shrink-0 align-[-2px]" /> {{ $studentsCount }} học sinh · <x-lucide name="calendar-days" class="inline h-3.5 w-3.5 shrink-0 align-[-2px]" /> {{ $nextSessionLabel }}</p>
                </div>
            </div>
            <div class="w-40">
                <x-ws.progress-bar :percent="$completion ?? 0" label="Hoàn thành chung (theo buổi học)" tone="info" />
                @if (($completionTotalSessions ?? 0) > 0)
                    <p class="text-[11px] text-slate-400 mt-1">{{ $completionEndedSessions }}/{{ $completionTotalSessions }} buổi đã học</p>
                @endif
            </div>
        </div>
    </div>

    <x-ws.tabs :tabs="$tabsData" />

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
                <div class="bg-white rounded-3xl border border-sky-100 p-4 flex items-center justify-between flex-wrap gap-3">
                    <div class="flex items-center gap-3">
                        <x-ws.icon-tile icon="book-open" tone="sky" />
                        <div>
                            <p class="font-medium text-slate-700">{{ $m['title'] }}</p>
                            <p class="text-xs mt-1 flex items-center gap-2">
                                @if ($m['typeLabel'])
                                    <span class="text-slate-400">{{ $m['typeLabel'] }}</span>
                                @endif
                                <x-ws.badge :tone="$m['tone']">{{ $m['scope'] }}</x-ws.badge>
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 text-[13px]">
                        <x-ws.badge tone="success">{{ $m['linkedStatus'] }}</x-ws.badge>
                        {{-- Xem NGUYÊN sản phẩm qua đúng route học sinh/giáo viên đang tải PDF
                             nội dung (access.resource, kind=content) — giáo viên đã có quyền
                             dạy sản phẩm này nên không bị chặn. Mở tab mới cùng kiểu với "Xem
                             đề bài ↗" ở mục Bài tập bên dưới, tiện xem sách/chuyên đề/bộ đề
                             nhiều cuốn cùng lúc. --}}
                        @if ($m['hasContent'] ?? false)
                            <a href="{{ route('access.resource', ['product' => $m['productId'], 'kind' => 'content']) }}" target="_blank" rel="noopener" class="text-blue-600 font-medium">Xem ↗</a>
                        @endif
                        <form method="POST" action="{{ route('teacher.classes.materials.detach', ['class' => $classRoom->id, 'classMaterial' => $m['id']]) }}" class="inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-blue-500">Gỡ</button>
                        </form>
                    </div>
                </div>
            @empty
                <x-ws.empty-state title="Lớp chưa gắn học liệu nào" />
            @endforelse

            @if (empty($attachableProducts))
                <div class="rounded-3xl border-2 border-dashed border-sky-100 text-slate-400 text-[13px] py-4 text-center">
                    Không có sách/chuyên đề/bộ đề nào bạn còn quyền dạy để thêm — quyền dạy (teacher_teaching) đã hết hạn hoặc chưa được cấp (7.2).
                </div>
            @else
                <div class="rounded-3xl border-2 border-dashed border-sky-100 p-4" x-data="{ type: '{{ $defaultType }}' }">
                    <p class="text-[13px] text-slate-600 mb-3">+ Thêm học liệu vào lớp — chọn loại rồi chọn đúng cuốn (chỉ hiện sách/chuyên đề/bộ đề bạn còn quyền dạy còn hạn, 8.2):</p>

                    <div class="flex flex-wrap gap-2 mb-3">
                        @foreach ($attachableProducts as $typeKey => $group)
                            <button type="button" @click="type = '{{ $typeKey }}'"
                                    :class="type === '{{ $typeKey }}' ? 'bg-blue-600 text-white' : 'bg-white text-slate-600 border border-sky-100'"
                                    class="px-3 py-1.5 rounded-xl text-[13px] font-medium transition">
                                {{ $group['label'] }} ({{ count($group['products']) }})
                            </button>
                        @endforeach
                    </div>

                    @if (! $hasAnyAttachable)
                        <p class="text-[13px] text-slate-400 text-center py-3">Mọi sách/chuyên đề/bộ đề bạn còn quyền dạy đã gắn hết vào lớp này rồi.</p>
                    @endif

                    @foreach ($attachableProducts as $typeKey => $group)
                        <div x-show="type === '{{ $typeKey }}'" x-cloak class="space-y-2">
                            @forelse ($group['products'] as $p)
                                <form method="POST" action="{{ route('teacher.classes.materials.attach', $classRoom->id) }}" class="flex items-center justify-between gap-3 bg-slate-50 rounded-xl px-3 py-2">
                                    @csrf
                                    <input type="hidden" name="product_id" value="{{ $p['id'] }}">
                                    <div class="min-w-0">
                                        <p class="text-[13px] text-slate-700 truncate">{{ $p['title'] }}</p>
                                        <p class="text-xs text-slate-400">Dùng được ở mọi lớp phụ trách đến {{ $p['expiresAtLabel'] }}</p>
                                    </div>
                                    <button type="submit" class="inline-flex min-h-9 items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-3 py-1.5 text-xs font-bold text-white shadow-sm shadow-blue-200 transition-colors hover:bg-blue-700 shrink-0">Thêm vào lớp</button>
                                </form>
                            @empty
                                <p class="text-[13px] text-slate-400 text-center py-3">Không còn {{ mb_strtolower($group['label']) }} nào để thêm.</p>
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

        <div class="bg-white rounded-3xl border border-sky-100 p-5 mb-4">
            <p class="text-[13px] font-medium text-slate-600 mb-3">+ Tạo buổi học mới</p>
            <form method="POST" action="{{ route('teacher.schedule.store') }}" x-data="{ startsDate: '{{ old('starts_date', '') }}', endsDate: '{{ old('ends_date', '') }}' }" class="space-y-4">
                @csrf
                <input type="hidden" name="class_room_id" value="{{ $classRoom->id }}">
                <input type="hidden" name="back_to_class" value="1">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1" for="topic">Chủ đề buổi học</label>
                        <input id="topic" type="text" name="topic" maxlength="255" value="{{ old('topic') }}" placeholder="Ví dụ: Ôn tập chương 3" class="admin-input">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1" for="location">Địa điểm/link</label>
                        <input id="location" type="text" name="location" maxlength="255" value="{{ old('location') }}" placeholder="Phòng học hoặc link online" class="admin-input">
                    </div>
                </div>

                @include('partials.session-datetime-fields')

                <div>
                    <button type="submit" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white shadow-sm shadow-blue-200 transition-colors hover:bg-blue-700">Tạo buổi học</button>
                </div>
            </form>
        </div>

        <x-ws.table :columns="['Thời gian (bắt đầu - kết thúc)', 'Chủ đề', 'Địa điểm', 'Trạng thái', 'Điểm danh', '']">
            @forelse ($sessions as $s)
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3 text-slate-700">{{ $s['timeRangeLabel'] }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $s['topic'] ?? '—' }}</td>
                    <td class="px-4 py-3 text-slate-400">{{ $s['location'] ?? '—' }}</td>
                    <td class="px-4 py-3"><x-ws.badge :tone="$s['timeStatusTone']">{{ $s['timeStatusLabel'] }}</x-ws.badge></td>
                    <td class="px-4 py-3"><x-ws.badge :tone="$s['attendanceTaken'] ? 'success' : 'warning'">{{ $s['attendanceSummary'] }}</x-ws.badge></td>
                    <td class="px-4 py-3 text-right"><a href="{{ route('teacher.schedule.attendance', $s['id']) }}" class="text-blue-600 font-medium">Điểm danh</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-6 text-center text-slate-400">Lớp chưa có buổi học nào — tạo buổi học đầu tiên ở trên.</td></tr>
            @endforelse
        </x-ws.table>
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

        <div class="bg-white rounded-3xl border border-sky-100 p-5 mb-4 space-y-3">
            {{-- SỬA 8/9 (5) — màn "Luyện tập" có thể đang ẩn (config/features.php); khi ẩn thì
                 không trỏ người dùng sang đó nữa mà chỉ sang "Đề PDF của tôi". Giao đề vẫn chạy
                 bình thường với các đề đã có. --}}
            @if (config('features.teacher_practice_screen', false))
                <p class="text-[13px] text-slate-500">Giao đề dùng cho kiểm tra có thời điểm mở-đóng, hạn nộp riêng (8.4). Chưa có đề? Tạo ở trang <a href="{{ route('teacher.assessments.create') }}" class="text-blue-600 font-medium">Luyện tập</a> trước, rồi quay lại đây để chọn và giao.</p>
            @else
                <p class="text-[13px] text-slate-500">Giao đề dùng cho kiểm tra có thời điểm mở-đóng, hạn nộp riêng (8.4). Chưa có đề? Tạo ở trang <a href="{{ route('teacher.papers.index') }}" class="text-blue-600 font-medium">Đề PDF của tôi</a> trước, rồi quay lại đây để chọn và giao.</p>
            @endif

            @if (empty($assignableAssessments))
                <p class="text-[13px] text-slate-400">Bạn chưa có đề nào — tạo đề ở "{{ config('features.teacher_practice_screen', false) ? 'Luyện tập' : 'Đề PDF của tôi' }}" trước khi giao cho lớp này.</p>
            @else
                <form method="POST" action="{{ route('teacher.classes.assign', $classRoom->id) }}" class="rounded-xl bg-slate-50 border border-sky-100 p-4 space-y-2">
                    @csrf
                    <p class="text-xs font-medium text-slate-500 mb-1">+ Giao đề có sẵn cho lớp này</p>
                    <x-ws.select name="assessment_id" required>
                        <option value="">— Chọn đề —</option>
                        @foreach ($assignableAssessments as $ass)
                            <option value="{{ $ass['id'] }}" @selected((string) old('assessment_id') === (string) $ass['id'])>{{ $ass['title'] }}{{ $ass['status'] === 'Nháp' ? ' (Nháp — tự phát hành khi giao)' : '' }}</option>
                        @endforeach
                    </x-ws.select>
                    <div class="space-y-2">
                        @include('partials.optional-date-hour-minute-fields', ['prefix' => 'opens', 'label' => 'Mở lúc (tùy chọn)'])
                        @include('partials.optional-date-hour-minute-fields', ['prefix' => 'closes', 'label' => 'Đóng lúc (tùy chọn)'])
                        <p class="text-[11px] text-slate-400">Để trống Ngày nếu không giới hạn mốc thời gian đó.</p>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-600 mb-1" for="shift_count">Chia ca thi (tùy chọn — chống nghẽn khi đông thí sinh)</label>
                        <input id="shift_count" name="shift_count" type="number" min="1" max="20" value="{{ old('shift_count') }}"
                               class="admin-input" placeholder="VD: 3 (cần đủ cả Mở lúc + Đóng lúc)">
                    </div>
                    <textarea name="instructions" rows="2" class="admin-input" placeholder="Hướng dẫn làm bài (tùy chọn)...">{{ old('instructions') }}</textarea>
                    <p class="text-xs text-slate-400">Đề sẽ tự động phát hành nếu mọi câu đã đủ điều kiện (6.2), không hỗ trợ ngoại lệ từng học sinh (8.4).</p>
                    <button type="submit" class="inline-flex min-h-9 items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-3 py-1.5 text-xs font-bold text-white shadow-sm shadow-blue-200 transition-colors hover:bg-blue-700">Giao đề cho lớp</button>
                </form>
            @endif
        </div>

        <x-ws.table :columns="['Tên đề', 'Mở lúc', 'Đóng lúc', 'Trạng thái', '']">
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
                    <td class="px-4 py-3"><x-ws.badge :tone="$ag['statusTone']">{{ $ag['statusLabel'] }}</x-ws.badge></td>
                    <td class="px-4 py-3 text-right"><a href="{{ route('teacher.results.index', ['class' => $classRoom->id, 'assessment' => $ag['id']]) }}" class="text-blue-600 font-medium">Xem kết quả</a></td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-6 text-center text-slate-400">Lớp chưa có đề nào được giao — chọn đề ở khung trên để giao.</td></tr>
            @endforelse
        </x-ws.table>
    @elseif ($tab === 'results')
        <div class="bg-white rounded-3xl border border-sky-100 p-6 flex items-start gap-4">
            <x-ws.icon-tile icon="trending-up" tone="emerald" />
            <a href="{{ route('teacher.results.index') }}" class="text-[13px] text-blue-600 font-medium self-center">Xem kết quả chi tiết theo lớp này ›</a>
        </div>
    @elseif ($tab === 'members')
        <div class="rounded-3xl bg-sky-50/60 border border-blue-100 p-5 mb-4 flex items-center justify-between gap-4 flex-wrap">
            <div>
                <p class="text-[13px] font-medium text-slate-700">Mã lớp để học sinh tự tham gia</p>
                <p class="text-xs text-slate-400 mt-0.5">Chia sẻ mã này cho học sinh — các em tự nhập ở trang Khóa học của tôi để vào lớp.</p>
            </div>
            <span class="text-base font-mono font-semibold px-3 py-1.5 rounded-xl bg-white border border-blue-200 text-blue-600 shrink-0">{{ $classRoom->code }}</span>
        </div>
        <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-4 sm:p-5">
            <p class="text-xs text-slate-400 mb-3">{{ $members->count() }} học sinh</p>
            <div class="space-y-2 max-h-96 overflow-y-auto">
                @foreach ($members as $m)
                    <div class="flex items-center gap-3 py-1.5">
                        <x-ws.avatar :name="$m->name" size="sm" />
                        <p class="text-[13px] text-slate-600">{{ $m->name }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    @else
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="bg-white rounded-3xl border border-sky-100 p-5 flex items-start gap-3">
                <x-ws.icon-tile icon="star" tone="amber" />
                <div>
                    <h3 class="font-medium text-slate-700 mb-2">Rating summary nội bộ</h3>
                    <x-rating-summary :average="$ratingAverage" :count="$ratingCount" />
                </div>
            </div>
            <div class="bg-white rounded-3xl border border-sky-100 p-5 flex items-start gap-3">
                <x-ws.icon-tile icon="calendar-days" tone="sky" />
                <div>
                    <h3 class="font-medium text-slate-700 mb-2">Buổi học gần nhất</h3>
                    <p class="text-[13px] text-slate-500">{{ $nextSessionLabel }}</p>
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

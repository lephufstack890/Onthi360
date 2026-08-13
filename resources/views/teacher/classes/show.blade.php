{{--
  Route: teacher.classes.show | Frame: TEA-02 (chi tiết) + TEA-06 (học liệu)
  Spec: 8.2/8.3 (gắn học liệu, lộ trình mở theo chương/mục/mã bài) +
  7.2 (quyền dạy đa lớp — nhãn "Dùng được ở mọi lớp phụ trách đến DD/MM/YYYY").
  TODO controller: truyền $classRoom thật; tab "materials" cần
  App\Services\AccessGateService/TeacherAttachMaterialAction để biết học
  liệu nào giáo viên còn quyền dạy.
--}}
@extends('layouts.teacher')

@section('title', 'Chi tiết lớp')
@section('page-title', 'Chi tiết lớp')

@section('content')
    {{-- Dữ liệu thật do App\Http\Controllers\Teacher\ClassRoomController truyền vào. --}}
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
            <div class="w-40"><x-progress-bar :percent="0" label="Hoàn thành chung" tone="info" /></div>
        </div>
    </div>

    <x-tabs :tabs="$tabsData" />

    @if ($tab === 'materials')
        <div class="flex flex-wrap gap-2 mb-4 text-sm">
            <button type="button" class="px-3 py-1.5 rounded-full bg-rose-50 text-rose-600 font-medium">Tất cả</button>
            <button type="button" class="px-3 py-1.5 rounded-full border border-slate-200 text-slate-500">Có thể dùng ở mọi lớp phụ trách</button>
            <button type="button" class="px-3 py-1.5 rounded-full border border-slate-200 text-slate-500">Sắp hết hạn</button>
            <button type="button" class="px-3 py-1.5 rounded-full border border-slate-200 text-slate-500">Đã hết hạn</button>
        </div>

        <div class="space-y-3">
            @forelse ($materials as $m)
                <div class="bg-white rounded-2xl border border-slate-200 p-4 flex items-center justify-between flex-wrap gap-3">
                    <div class="flex items-center gap-3">
                        <x-icon-tile emoji="📚" tone="sky" />
                        <div>
                            <p class="font-medium text-slate-700">{{ $m['title'] }}</p>
                            <p class="text-xs mt-1"><x-status-badge :tone="$m['tone']">{{ $m['scope'] }}</x-status-badge></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 text-sm">
                        <x-status-badge tone="success">{{ $m['linkedStatus'] }}</x-status-badge>
                        <button type="button" class="text-slate-500">Xem trước như học sinh</button>
                        <button type="button" class="text-slate-500">Mở theo chương/mục ▾</button>
                        <button type="button" class="text-rose-500">Gỡ</button>
                    </div>
                </div>
            @empty
                <x-empty-state title="Lớp chưa gắn học liệu nào" />
            @endforelse

            <button type="button" class="w-full rounded-2xl border-2 border-dashed border-slate-200 text-slate-400 text-sm py-4 hover:border-rose-300 hover:text-rose-500">
                + Thêm học liệu vào lớp (chỉ hiện học liệu bạn còn quyền dạy)
            </button>
        </div>
    @elseif ($tab === 'schedule')
        <div class="bg-white rounded-2xl border border-slate-200 p-8 text-center">
            <div class="text-4xl mb-3">🗓️</div>
            <p class="text-sm text-slate-500">TODO: lịch buổi học + bảng điểm danh từng buổi (có/vắng/xin phép).</p>
        </div>
    @elseif ($tab === 'assign')
        <div class="bg-white rounded-2xl border border-slate-200 p-6 flex items-start gap-4">
            <x-icon-tile emoji="🧾" tone="violet" />
            <div>
                <p class="text-sm text-slate-500 mb-3">Giao đề dùng cho kiểm tra có thời điểm mở-đóng, hạn nộp riêng (8.4) — không phải cách hiển thị bài thường nhật.</p>
                <a href="{{ route('teacher.assessments.create') }}" class="text-sm text-rose-600 font-medium">+ Tạo bài giao đánh giá mới ›</a>
            </div>
        </div>
    @elseif ($tab === 'results')
        <div class="bg-white rounded-2xl border border-slate-200 p-6 flex items-start gap-4">
            <x-icon-tile emoji="📈" tone="emerald" />
            <a href="{{ route('teacher.results.index') }}" class="text-sm text-rose-600 font-medium self-center">Xem kết quả chi tiết theo lớp này ›</a>
        </div>
    @elseif ($tab === 'members')
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <p class="text-xs text-slate-400 mb-3">{{ $members->count() }} học sinh · TODO: trạng thái quyền cá nhân từng em (7.3).</p>
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
@endsection

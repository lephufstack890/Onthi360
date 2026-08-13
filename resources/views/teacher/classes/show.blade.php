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
        @php $attachableMaterials = $attachableMaterials ?? []; @endphp

        @if (session('status') === 'material-attached')
            @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã thêm học liệu vào lớp.'])
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
                            <p class="text-xs mt-1"><x-status-badge :tone="$m['tone']">{{ $m['scope'] }}</x-status-badge></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 text-sm">
                        <x-status-badge tone="success">{{ $m['linkedStatus'] }}</x-status-badge>
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

            @if (empty($attachableMaterials))
                <div class="rounded-2xl border-2 border-dashed border-slate-200 text-slate-400 text-sm py-4 text-center">
                    Không có học liệu nào bạn còn quyền dạy để thêm — quyền dạy (teacher_teaching) đã hết hạn hoặc chưa được cấp (7.2).
                </div>
            @else
                <div class="rounded-2xl border-2 border-dashed border-slate-200 p-4">
                    <p class="text-sm text-slate-600 mb-3">+ Thêm học liệu vào lớp (chỉ hiện học liệu bạn còn quyền dạy còn hạn, 8.2):</p>
                    <div class="space-y-2">
                        @foreach ($attachableMaterials as $am)
                            <form method="POST" action="{{ route('teacher.classes.materials.attach', $classRoom->id) }}" class="flex items-center justify-between gap-3 bg-slate-50 rounded-lg px-3 py-2">
                                @csrf
                                <input type="hidden" name="material_id" value="{{ $am['id'] }}">
                                <div class="min-w-0">
                                    <p class="text-sm text-slate-700 truncate">{{ $am['title'] }}</p>
                                    <p class="text-xs text-slate-400">{{ $am['product'] }} · Dùng được ở mọi lớp phụ trách đến {{ $am['expiresAtLabel'] }}</p>
                                </div>
                                <button type="submit" class="px-3 py-1.5 rounded-lg bg-rose-600 text-white text-xs font-medium shrink-0">Thêm vào lớp</button>
                            </form>
                        @endforeach
                    </div>
                </div>
            @endif
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

{{--
  Route: competitions.show | Frame: PUB-08
  Spec: 11.1 (banner, thời gian, đối tượng, thể lệ, cấu trúc đề, countdown,
  CTA theo trạng thái, kết quả) + note họp 13/8 mục 1 (đơn vị tổ chức/cố vấn).
  Dữ liệu thật do App\Http\Controllers\Public\CompetitionController truyền vào qua
  App\Services\Public\CompetitionService::showData() — countdown tính bằng Carbon từ thời
  điểm request (không cần JS), ảnh bìa dùng picsum.photos tạm.
--}}
@extends('layouts.guest')

@section('title', 'Chi tiết cuộc thi')

@section('content')
    @php
        $rankingRule = $rankingRule ?? [];
        $daysUntilStart = $daysUntilStart ?? null;
        $canJoinDirectly = $canJoinDirectly ?? false;
    @endphp

    <div class="max-w-5xl mx-auto px-4 py-10">
        <a href="{{ route('competitions.index') }}" class="text-sm text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-rose-600">‹ Quay lại Cuộc thi</a>

        <div class="rounded-3xl overflow-hidden relative mb-8 shadow-sm">
            <img src="https://picsum.photos/seed/{{ \Illuminate\Support\Str::slug($competition->title) }}/1200/480" alt="" class="w-full h-56 lg:h-72 object-cover">
            <div class="absolute inset-0 bg-gradient-to-t from-slate-900/80 via-slate-900/30 to-transparent"></div>
            <div class="absolute inset-x-0 bottom-0 p-6 lg:p-8 text-white">
                <div class="flex flex-wrap gap-2">
                    <x-status-badge :tone="$statusTone">{{ $statusLabel }}</x-status-badge>
                    @if ($competition->isExternallyOrganized())
                        <x-status-badge tone="warning">Tổ chức bởi {{ $competition->organizer_name }}</x-status-badge>
                    @endif
                </div>
                <h1 class="text-2xl lg:text-3xl font-semibold mt-2">{{ $competition->title }}</h1>
                <p class="text-slate-200 mt-1">
                    🗓
                    @if ($competition->starts_at && $competition->ends_at)
                        {{ $competition->starts_at->format('d/m/Y H:i') }} – {{ $competition->ends_at->format('d/m/Y H:i') }}
                    @else
                        Chưa đặt lịch
                    @endif
                    · {{ $competition->type->value === 'contest' ? 'Cuộc thi' : 'Khảo sát' }}
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
            <div class="rounded-2xl bg-white border border-slate-200 p-5 text-center">
                <p class="text-2xl font-semibold text-rose-600">{{ $daysUntilStart !== null && $daysUntilStart > 0 ? $daysUntilStart : 0 }}</p>
                <p class="text-xs text-slate-400 mt-1">ngày nữa bắt đầu</p>
            </div>
            <div class="rounded-2xl bg-white border border-slate-200 p-5 text-center">
                <p class="text-2xl font-semibold text-slate-800">{{ number_format($competition->leaderboard_entries_count) }}</p>
                <p class="text-xs text-slate-400 mt-1">đã có trên bảng xếp hạng</p>
            </div>
            <div class="rounded-2xl bg-white border border-slate-200 p-5 text-center">
                <p class="text-2xl font-semibold text-slate-800">{{ $competition->assessment?->duration_minutes ? $competition->assessment->duration_minutes."'" : '—' }}</p>
                <p class="text-xs text-slate-400 mt-1">thời gian mỗi lượt thi</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-5">
                <div class="bg-white rounded-2xl border border-slate-200 p-5">
                    <h2 class="font-medium text-slate-700 mb-3 flex items-center gap-2"><span>📋</span> Thể lệ</h2>
                    <p class="text-sm text-slate-500 whitespace-pre-line">{{ $competition->rules ?: 'Chưa nhập thể lệ.' }}</p>
                </div>

                <div class="bg-white rounded-2xl border border-slate-200 p-5">
                    <h2 class="font-medium text-slate-700 mb-3 flex items-center gap-2"><span>🧾</span> Đề tham chiếu</h2>
                    <p class="text-sm text-slate-500">{{ $competition->assessment->title ?? '— Chưa gắn đề —' }}</p>
                    <p class="text-xs text-slate-400 mt-3">Đề thi thuộc kho Tài liệu chung — cuộc thi chỉ tham chiếu để tổ chức thành sự kiện (4.3).</p>
                </div>

                @if (($rankingRule['scoring_note'] ?? '') !== '' || ($rankingRule['penalty_note'] ?? '') !== '' || ($rankingRule['tie_break_note'] ?? '') !== '')
                    <div class="bg-white rounded-2xl border border-slate-200 p-5">
                        <h2 class="font-medium text-slate-700 mb-3 flex items-center gap-2"><span>🥇</span> Quy tắc bảng xếp hạng</h2>
                        <ul class="space-y-2 text-sm text-slate-600">
                            @if ($rankingRule['scoring_note'] ?? null)
                                <li><span class="text-slate-400">Công thức điểm:</span> {{ $rankingRule['scoring_note'] }}</li>
                            @endif
                            @if ($rankingRule['penalty_note'] ?? null)
                                <li><span class="text-slate-400">Penalty:</span> {{ $rankingRule['penalty_note'] }}</li>
                            @endif
                            @if ($rankingRule['tie_break_note'] ?? null)
                                <li><span class="text-slate-400">Đồng điểm:</span> {{ $rankingRule['tie_break_note'] }}</li>
                            @endif
                        </ul>
                    </div>
                @endif

                @if ($competition->isExternallyOrganized() && $competition->advisors->isNotEmpty())
                    <div class="bg-white rounded-2xl border border-slate-200 p-5">
                        <h2 class="font-medium text-slate-700 mb-3 flex items-center gap-2"><span>🎓</span> Giáo viên cố vấn/đồng hành</h2>
                        <ul class="flex flex-wrap gap-2">
                            @foreach ($competition->advisors as $advisor)
                                <li class="px-2.5 py-1 rounded-full bg-amber-50 border border-amber-100 text-xs text-amber-700">{{ $advisor->name }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-5 h-fit sticky top-6">
                <h2 class="font-medium text-slate-700 mb-2">Sẵn sàng tham gia?</h2>
                @if ($canJoinDirectly)
                    <p class="text-sm text-slate-500 mb-4">Vào làm đề tham chiếu của cuộc thi — kết quả sẽ được ghi nhận vào hồ sơ của bạn.</p>
                    <a href="{{ route('student.assessment.take', $competition->assessment_id) }}" class="block text-center px-4 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium">
                        Vào thi ngay
                    </a>
                @else
                    <p class="text-sm text-slate-500 mb-4">Đăng nhập để tham gia — kết quả và bảng xếp hạng sẽ tự cập nhật vào hồ sơ của bạn.</p>
                    <a href="{{ auth()->check() ? route('dashboard') : route('login') }}" class="block text-center px-4 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium">
                        {{ auth()->check() ? 'Về trang của tôi' : 'Đăng nhập để tham gia' }}
                    </a>
                @endif
                <a href="{{ route('leaderboard.index', ['competition' => $competition->id]) }}" class="block text-center mt-2 px-4 py-2.5 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium">
                    Xem bảng xếp hạng
                </a>
            </div>
        </div>
    </div>
@endsection

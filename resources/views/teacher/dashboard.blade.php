@extends('layouts.teacher')

@section('title', 'Tổng quan')
@section('page-title', 'Tổng quan')

@section('content')
    @php
        $name = $name ?? (auth()->user()->name ?? 'thầy/cô');
        $upcoming = $upcoming ?? [];
        $toOpen = $toOpen ?? [];
        $attentionStudents = $attentionStudents ?? [];
        $accessExpiring = $accessExpiring ?? null;
        $isTeacherApproved = auth()->user()->isTeacherApproved();
    @endphp

    @if (session('warning'))
        @include('partials.toast-flash', ['type' => 'warning', 'message' => session('warning')])
    @endif

    @unless ($isTeacherApproved)
        <div class="rounded-3xl border border-amber-100 bg-amber-50 p-4 mb-4 flex items-center gap-4 flex-wrap">
            <x-ws.icon-tile icon="clock-3" tone="amber" />
            <p class="text-[13px] text-amber-800 flex-1">
                Hồ sơ giáo viên của bạn đang <strong>chờ Admin duyệt</strong> (3.3). Sau khi được duyệt, bạn mới có thể
                tạo lớp học và giao bài kiểm tra thật cho học sinh — trong lúc chờ, bạn vẫn có thể chuẩn bị câu hỏi/đề.
            </p>
        </div>
    @endunless

    <div class="rounded-3xl border border-sky-100 bg-gradient-to-br from-sky-50 via-white to-blue-50 shadow-[0_2px_8px_rgba(0,90,180,.04)] p-5 lg:p-6 mb-4 flex items-center justify-between flex-wrap gap-4">
        <div>
            <p class="text-[13px] text-sky-600 font-medium">Chào thầy/cô 👋</p>
            <h2 class="text-xl lg:text-2xl font-semibold text-slate-800 mt-1">{{ $name }}, hôm nay có {{ count($upcoming) }} buổi dạy</h2>
            <p class="text-[13px] text-slate-500 mt-1">{{ count($toOpen) }} bài đang chờ mở tiến độ · {{ count($attentionStudents) }} học sinh cần chú ý</p>
        </div>
        <div class="w-16 h-16 rounded-3xl bg-white/70 flex items-center justify-center text-4xl shrink-0 shadow-sm">🍎</div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <x-ws.stat label="Buổi dạy sắp tới" :value="count($upcoming)" hint="Lịch dạy gần nhất" tone="{{ count($upcoming) > 0 ? 'success' : 'neutral' }}" />
        <x-ws.stat label="Bài chờ mở tiến độ" :value="count($toOpen)" hint="Cần bạn xử lý" tone="{{ count($toOpen) > 0 ? 'warning' : 'neutral' }}" />
        <x-ws.stat label="Học sinh cần chú ý" :value="count($attentionStudents)" hint="Theo dõi sát hơn" tone="{{ count($attentionStudents) > 0 ? 'danger' : 'neutral' }}" />
    </div>

    @if ($accessExpiring)
        <div class="rounded-3xl bg-amber-50 border border-amber-100 p-4 mb-6 flex items-center gap-4 flex-wrap">
            <x-ws.icon-tile emoji="⏳" tone="amber" />
            <p class="text-[13px] text-amber-800 flex-1">
                Quyền dạy "<strong>{{ $accessExpiring['product'] }}</strong>" sắp hết hạn — còn {{ $accessExpiring['daysLeft'] }} ngày. Hết hạn sẽ không gắn/mở mới được học liệu này ở bất kỳ lớp nào (7.2).
            </p>
            <a href="{{ route('access.myAccess') }}" class="px-4 py-2 rounded-xl bg-amber-500 text-white text-[13px] font-medium shrink-0">Xem quyền của tôi</a>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-4 sm:p-5">
                <h3 class="font-medium text-slate-700 mb-4 flex items-center gap-2"><span><x-lucide name="calendar-days" class="h-4 w-4" /></span> Lịch sắp dạy</h3>
                <ul class="space-y-2">
                    @forelse ($upcoming as $u)
                        <li class="flex items-center gap-3 text-[13px] rounded-xl px-3 py-2.5 hover:bg-slate-50">
                            <div class="w-20 shrink-0 rounded-xl bg-sky-50 text-sky-600 text-xs font-semibold text-center py-1.5">{{ $u['time'] }}</div>
                            <div>
                                <p class="text-slate-700 font-medium">{{ $u['class'] }}</p>
                                <p class="text-xs text-slate-400">{{ $u['topic'] }}</p>
                            </div>
                        </li>
                    @empty
                        <li class="text-[13px] text-slate-400 px-3 py-2">Chưa có buổi dạy nào sắp tới.</li>
                    @endforelse
                </ul>
            </div>

            <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-4 sm:p-5">
                <h3 class="font-medium text-slate-700 mb-4 flex items-center gap-2"><span><x-lucide name="book-open" class="h-4 w-4" /></span> Bài cần mở tiến độ</h3>
                <ul class="divide-y divide-slate-100">
                    @forelse ($toOpen as $t)
                        <li class="flex items-center justify-between gap-3 py-3 text-[13px]">
                            <div class="flex items-center gap-3">
                                <x-ws.icon-tile icon="book-open" tone="violet" />
                                <div>
                                    <p class="text-slate-700 font-medium">{{ $t['title'] }}</p>
                                    <p class="text-xs text-slate-400">{{ $t['class'] }}{{ $t['chapter'] ? ' · '.$t['chapter'] : '' }}</p>
                                </div>
                            </div>
                            <button type="button" class="text-blue-600 font-medium text-[13px] shrink-0">Mở ngay ›</button>
                        </li>
                    @empty
                        <li class="text-[13px] text-slate-400 py-3">Không có bài nào đang chờ mở.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-4 sm:p-5">
            <h3 class="font-medium text-slate-700 mb-4 flex items-center gap-2"><span>🧑‍🎓</span> Học sinh cần chú ý</h3>
            <ul class="space-y-3">
                @forelse ($attentionStudents as $s)
                    <li class="flex items-center gap-3 text-[13px]">
                        <x-ws.avatar :name="$s['name']" size="md" />
                        <div>
                            <p class="text-slate-700 font-medium">{{ $s['name'] }}</p>
                            <p class="text-xs text-slate-400">{{ $s['class'] }} · {{ $s['reason'] }}</p>
                        </div>
                    </li>
                @empty
                    <li class="text-[13px] text-slate-400">Chưa có học sinh nào cần chú ý đặc biệt.</li>
                @endforelse
            </ul>
        </div>
    </div>
@endsection

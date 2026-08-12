{{--
  Route: competitions.show
  Spec: 11.1 (banner, thời gian, đối tượng, thể lệ, cấu trúc đề, countdown,
  CTA theo trạng thái, kết quả).
  TODO controller: truyền $competition thật; countdown hiện tính bằng
  Carbon từ thời điểm request (không cần JS) — đủ dùng cho P0, thay bằng
  đồng hồ JS thật nếu cần cập nhật theo giây.
--}}
@extends('layouts.guest')

@section('title', 'Chi tiết cuộc thi')

@section('content')
    @php
        $competition = [
            'title' => 'Cuộc thi Tin học trẻ 2026',
            'type' => 'Cá nhân',
            'status' => 'Sắp diễn ra',
            'tone' => 'info',
            'starts' => now()->addDays(9)->setTime(8, 0),
            'ends' => now()->addDays(14)->setTime(17, 0),
            'audience' => 'Học sinh khối 8-9, đã có tài khoản đã xác thực trên Ôn Thi 360.',
            'format' => 'Thi trực tuyến, 90 phút/lượt, tối đa 1 lượt thi chính thức.',
            'structure' => ['15 câu trắc nghiệm (6đ)', '2 câu điền đáp án (2đ)', '1 câu lập trình (2đ)'],
            'prizes' => ['Giải Nhất: 1 · Giải Nhì: 2 · Giải Ba: 3', 'Giấy chứng nhận điện tử cho Top 20'],
            'participants' => 482,
        ];
        $daysLeft = now()->diffInDays($competition['starts'], false);
    @endphp

    <div class="max-w-5xl mx-auto px-4 py-10">
        <a href="{{ route('competitions.index') }}" class="text-sm text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-rose-600">‹ Quay lại Cuộc thi</a>

        <div class="rounded-3xl overflow-hidden relative mb-8 shadow-sm">
            <img src="https://picsum.photos/seed/{{ \Illuminate\Support\Str::slug($competition['title']) }}/1200/480" alt="" class="w-full h-56 lg:h-72 object-cover">
            <div class="absolute inset-0 bg-gradient-to-t from-slate-900/80 via-slate-900/30 to-transparent"></div>
            <div class="absolute inset-x-0 bottom-0 p-6 lg:p-8 text-white">
                <x-status-badge :tone="$competition['tone']">{{ $competition['status'] }}</x-status-badge>
                <h1 class="text-2xl lg:text-3xl font-semibold mt-2">{{ $competition['title'] }}</h1>
                <p class="text-slate-200 mt-1">🗓 {{ $competition['starts']->format('d/m/Y H:i') }} – {{ $competition['ends']->format('d/m/Y H:i') }} · {{ $competition['type'] }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
            <div class="rounded-2xl bg-white border border-slate-200 p-5 text-center">
                <p class="text-2xl font-semibold text-rose-600">{{ $daysLeft > 0 ? $daysLeft : 0 }}</p>
                <p class="text-xs text-slate-400 mt-1">ngày nữa bắt đầu</p>
            </div>
            <div class="rounded-2xl bg-white border border-slate-200 p-5 text-center">
                <p class="text-2xl font-semibold text-slate-800">{{ number_format($competition['participants']) }}</p>
                <p class="text-xs text-slate-400 mt-1">đã đăng ký tham gia</p>
            </div>
            <div class="rounded-2xl bg-white border border-slate-200 p-5 text-center">
                <p class="text-2xl font-semibold text-slate-800">90'</p>
                <p class="text-xs text-slate-400 mt-1">thời gian mỗi lượt thi</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-5">
                <div class="bg-white rounded-2xl border border-slate-200 p-5">
                    <h2 class="font-medium text-slate-700 mb-3 flex items-center gap-2"><span>🎯</span> Đối tượng & hình thức</h2>
                    <p class="text-sm text-slate-500">{{ $competition['audience'] }}</p>
                    <p class="text-sm text-slate-500 mt-2">{{ $competition['format'] }}</p>
                </div>

                <div class="bg-white rounded-2xl border border-slate-200 p-5">
                    <h2 class="font-medium text-slate-700 mb-3 flex items-center gap-2"><span>🧾</span> Cấu trúc đề thi</h2>
                    <ul class="space-y-2">
                        @foreach ($competition['structure'] as $s)
                            <li class="flex items-center gap-2 text-sm text-slate-600"><span class="w-1.5 h-1.5 rounded-full bg-rose-400 shrink-0"></span>{{ $s }}</li>
                        @endforeach
                    </ul>
                    <p class="text-xs text-slate-400 mt-3">Đề thi thuộc kho Tài liệu chung — cuộc thi chỉ tham chiếu để tổ chức thành sự kiện (4.3).</p>
                </div>

                <div class="bg-white rounded-2xl border border-slate-200 p-5">
                    <h2 class="font-medium text-slate-700 mb-3 flex items-center gap-2"><span>🥇</span> Giải thưởng</h2>
                    <ul class="space-y-2">
                        @foreach ($competition['prizes'] as $p)
                            <li class="flex items-center gap-2 text-sm text-slate-600"><span class="w-1.5 h-1.5 rounded-full bg-amber-400 shrink-0"></span>{{ $p }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-5 h-fit sticky top-6">
                <h2 class="font-medium text-slate-700 mb-2">Sẵn sàng tham gia?</h2>
                <p class="text-sm text-slate-500 mb-4">Đăng nhập để đăng ký — kết quả và bảng xếp hạng sẽ tự cập nhật vào hồ sơ của bạn.</p>
                <a href="{{ route('login') }}" class="block text-center px-4 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium">
                    Đăng nhập để đăng ký
                </a>
                <a href="{{ route('leaderboard.index') }}" class="block text-center mt-2 px-4 py-2.5 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium">
                    Xem bảng xếp hạng
                </a>
            </div>
        </div>
    </div>
@endsection

{{--
  Route: leaderboard.index | Frame: PUB-09
  Spec: 11.2 (phạm vi rõ, công thức điểm, "Chờ công bố" không lộ rank
  tạm, bảo vệ dữ liệu trẻ em — mặc định ẩn danh, không dùng ảnh/tên thật).
  TODO controller: truyền $entries theo phạm vi đã chọn — hiện là dữ liệu
  minh họa để dựng UI. Avatar dùng màu cố định theo hạng, không suy ra từ
  tên thật (11.2, bảo vệ dữ liệu trẻ em).
--}}
@extends('layouts.guest')

@section('title', 'Bảng xếp hạng')

@section('content')
    @php
        $scope = 'Cuộc thi Tin học trẻ 2026';
        $formula = 'Tổng điểm các bài · phạt nộp muộn 5%/giờ';
        $entries = [
            ['rank' => 1, 'name' => 'Học sinh đã xác thực', 'score' => 980, 'color' => 'fde68a'],
            ['rank' => 2, 'name' => 'Học sinh đã xác thực', 'score' => 945, 'color' => 'e2e8f0'],
            ['rank' => 3, 'name' => 'Học sinh đã xác thực', 'score' => 920, 'color' => 'fed7aa'],
            ['rank' => 4, 'name' => 'Học sinh đã xác thực', 'score' => 885, 'color' => 'e0f2fe'],
            ['rank' => 5, 'name' => 'Học sinh đã xác thực', 'score' => 860, 'color' => 'e0f2fe'],
            ['rank' => 6, 'name' => 'Học sinh đã xác thực', 'score' => 840, 'color' => 'e0f2fe'],
        ];
        $medals = [1 => '🥇', 2 => '🥈', 3 => '🥉'];
        $top3 = array_slice($entries, 0, 3);
        $rest = array_slice($entries, 3);
    @endphp

    <div class="bg-gradient-to-br from-slate-900 via-slate-800 to-amber-900 text-white">
        <div class="max-w-5xl mx-auto px-4 py-14 lg:py-16 text-center">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/10 text-amber-200 text-xs font-medium mb-4">📊 Bảng xếp hạng</span>
            <h1 class="text-2xl lg:text-3xl font-semibold">{{ $scope }}</h1>
            <p class="text-slate-300 mt-2">Công thức: {{ $formula }}</p>
        </div>
    </div>

    <div class="max-w-5xl mx-auto px-4 py-10 lg:py-14">
        {{-- Podium top 3 --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-10 items-end">
            @foreach ($top3 as $e)
                <div class="rounded-2xl bg-white border border-slate-200 p-6 text-center {{ $e['rank'] === 1 ? 'sm:order-2 sm:-translate-y-3 shadow-lg border-amber-200' : ($e['rank'] === 2 ? 'sm:order-1' : 'sm:order-3') }}">
                    <div class="w-16 h-16 rounded-full mx-auto mb-3 flex items-center justify-center text-2xl" style="background-color:#{{ $e['color'] }}">👤</div>
                    <p class="text-2xl mb-1">{{ $medals[$e['rank']] }}</p>
                    <p class="font-medium text-slate-700 text-sm">{{ $e['name'] }}</p>
                    <p class="text-lg font-semibold text-slate-800 mt-1">{{ $e['score'] }}<span class="text-xs text-slate-400 font-normal"> điểm</span></p>
                </div>
            @endforeach
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-slate-500">
                    <tr><th class="px-4 py-3">#</th><th class="px-4 py-3">Người tham gia</th><th class="px-4 py-3 text-right">Điểm</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($rest as $e)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-medium text-slate-700">{{ $e['rank'] }}</td>
                            <td class="px-4 py-3 text-slate-600">
                                <div class="flex items-center gap-3">
                                    <span class="w-7 h-7 rounded-full flex items-center justify-center text-sm shrink-0" style="background-color:#{{ $e['color'] }}">👤</span>
                                    {{ $e['name'] }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right font-medium text-slate-700">{{ $e['score'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-4 py-6 text-center text-slate-400">Chưa có dữ liệu xếp hạng.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="text-xs text-slate-400 mt-3 flex items-center gap-1.5">🔒 Tên/ảnh đại diện hiển thị theo quyền riêng tư; mặc định bảo vệ dữ liệu trẻ em (11.2).</p>
    </div>
@endsection

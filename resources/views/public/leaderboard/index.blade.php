{{--
  Route: leaderboard.index | Frame: PUB-09
  Spec: 11.2 (phạm vi rõ, công thức điểm, "Chờ công bố" không lộ rank tạm, bảo vệ dữ liệu trẻ em).
  TODO controller: truyền $entries theo phạm vi đã chọn.
--}}
@extends('layouts.guest')

@section('title', 'Bảng xếp hạng')

@section('content')
    @php
        $entries = [
            ['rank' => 1, 'name' => 'Học sinh đã xác thực', 'score' => 980],
            ['rank' => 2, 'name' => 'Học sinh đã xác thực', 'score' => 945],
            ['rank' => 3, 'name' => 'Học sinh đã xác thực', 'score' => 920],
        ];
    @endphp

    <div class="max-w-3xl mx-auto px-4 py-10">
        <x-page-header title="Bảng xếp hạng" subtitle="Phạm vi: Cuộc thi Tin học trẻ 2026 — công thức: tổng điểm các bài, phạt nộp muộn 5%/giờ." />

        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-slate-500">
                    <tr><th class="px-4 py-3">#</th><th class="px-4 py-3">Người tham gia</th><th class="px-4 py-3 text-right">Điểm</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($entries as $e)
                        <tr>
                            <td class="px-4 py-3 font-medium text-slate-700">{{ $e['rank'] }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $e['name'] }}</td>
                            <td class="px-4 py-3 text-right text-slate-600">{{ $e['score'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="text-xs text-slate-400 mt-3">Tên/ảnh đại diện hiển thị theo quyền riêng tư; mặc định bảo vệ dữ liệu trẻ em (11.2).</p>
    </div>
@endsection

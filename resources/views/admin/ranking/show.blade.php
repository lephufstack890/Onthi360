{{--
  Route: admin.ranking.show
  Spec: 11.2 — chi tiết 1 bảng xếp hạng theo phạm vi cụ thể; "Chờ công bố"
  không lộ rank tạm nếu quy chế cấm ($ranksArePublic === false → danh sách rỗng).
  Dữ liệu thật do App\Services\Admin\RankingService::showBoard() truyền vào.
--}}
@extends('layouts.admin')

@section('title', 'Chi tiết bảng xếp hạng')
@section('page-title', 'Chi tiết bảng xếp hạng')

@section('content')
    @php
        $entries = $entries ?? [];
        $ranksArePublic = $ranksArePublic ?? false;
    @endphp

    <a href="{{ route('admin.ranking.index') }}" class="text-sm text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-rose-600">‹ Quay lại Bảng xếp hạng</a>

    <x-page-header :title="'📊 '.$scopeLabel" subtitle="Không trộn số liệu giữa các phạm vi khác nhau; tách biệt hoàn toàn với sao/rating (11.2, 9.1)." />

    @if ($competitionId)
        <div class="mb-4">
            <a href="{{ route('admin.competitions.edit', $competitionId) }}" class="text-sm text-rose-600 font-medium">⚙️ Cấu hình quy tắc xếp hạng cuộc thi này</a>
        </div>
    @endif

    @if (! $ranksArePublic)
        <div class="rounded-lg bg-amber-50 border border-amber-100 p-4 text-sm text-amber-700 mb-4">
            Cuộc thi đang ở trạng thái "Chờ công bố" — rank tạm không được hiển thị theo quy chế (11.2).
        </div>
    @else
        <x-data-table :columns="['Hạng', 'Người dự thi', 'Điểm', 'Chuyên đề']">
            @forelse ($entries as $e)
                <tr>
                    <td class="px-4 py-3 font-semibold text-slate-700">#{{ $e['rank'] }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $e['user'] }}</td>
                    <td class="px-4 py-3 text-slate-500">{{ $e['score'] }}</td>
                    <td class="px-4 py-3 text-slate-400">{{ $e['topic'] ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-4 py-6 text-center text-slate-400">Chưa có người xếp hạng nào.</td></tr>
            @endforelse
        </x-data-table>
    @endif
@endsection

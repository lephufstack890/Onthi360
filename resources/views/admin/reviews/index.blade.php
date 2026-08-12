{{--
  Route: admin.reviews.index | Frame: ADM-06
  Spec: 9.4 (kiểm duyệt: spam/xúc phạm/quảng cáo/dữ liệu cá nhân...; không xóa chỉ vì tiêu cực).
  TODO controller: truyền $reviews (paginate) trạng thái "Đang kiểm duyệt"/"Đã báo cáo".
--}}
@extends('layouts.admin')

@section('title', 'Đánh giá')
@section('page-title', 'Kiểm duyệt đánh giá')

@section('content')
    {{-- Dữ liệu thật do App\Http\Controllers\Admin\ReviewController truyền vào. --}}
    @php
        $tab = $tab ?? 'pending';
        $tabs = $tabs ?? [];
        $reviews = $reviews ?? [];
    @endphp

    <x-page-header title="⭐ Kiểm duyệt đánh giá" subtitle="Chỉ công bố review có sao tổng; có thể ẩn nhận xét không phù hợp mà vẫn công bố sao (9.4)." />

    <x-tabs :tabs="$tabs" />

    <x-data-table :columns="['Đối tượng', 'Người viết', 'Sao', 'Trích đoạn', 'Trạng thái', '']">
        @forelse ($reviews as $r)
            <tr>
                <td class="px-4 py-3 font-medium text-slate-700">{{ $r['target'] }}</td>
                <td class="px-4 py-3 text-slate-500">
                    <div class="flex items-center gap-2.5">
                        <img src="https://ui-avatars.com/api/?name={{ urlencode($r['author']) }}&background=e0f2fe&color=0369a1&size=64&bold=true"
                             alt="{{ $r['author'] }}" class="w-6 h-6 rounded-full shrink-0">
                        <span>{{ $r['author'] }}</span>
                    </div>
                </td>
                <td class="px-4 py-3 text-amber-500">{{ str_repeat('★', $r['rating']) }}</td>
                <td class="px-4 py-3 text-slate-500 max-w-xs truncate">{{ $r['excerpt'] }}</td>
                <td class="px-4 py-3"><x-status-badge :tone="$r['tone']">{{ $r['status'] }}</x-status-badge></td>
                <td class="px-4 py-3 text-right">
                    <a href="{{ route('admin.reviews.show', $r['id']) }}" class="text-rose-600 font-medium">Xem</a>
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-4 py-6 text-center text-slate-400">Không có đánh giá nào ở trạng thái này.</td></tr>
        @endforelse
    </x-data-table>
@endsection

{{--
  Route: admin.reviews.index | Frame: ADM-06
  Spec: 9.4 (kiểm duyệt: spam/xúc phạm/quảng cáo/dữ liệu cá nhân...; không xóa chỉ vì tiêu cực).
  TODO controller: truyền $reviews (paginate) trạng thái "Đang kiểm duyệt"/"Đã báo cáo".
--}}
@extends('layouts.admin')

@section('title', 'Đánh giá')
@section('page-title', 'Kiểm duyệt đánh giá')

@section('content')
    @php
        $tab = request('tab', 'pending');
        $tabs = [
            ['label' => 'Chờ kiểm duyệt', 'href' => route('admin.reviews.index'), 'active' => $tab === 'pending', 'count' => 12],
            ['label' => 'Đã báo cáo', 'href' => route('admin.reviews.index', ['tab' => 'reported']), 'active' => $tab === 'reported', 'count' => 3],
            ['label' => 'Đã công bố', 'href' => route('admin.reviews.index', ['tab' => 'published']), 'active' => $tab === 'published', 'count' => 861],
        ];
        $reviews = [
            ['id' => 1, 'target' => 'Lớp 10CT-2026', 'author' => 'Học viên đã xác thực', 'rating' => 5, 'excerpt' => 'Giáo viên nhiệt tình, lịch học rõ ràng...', 'status' => 'Đang kiểm duyệt', 'tone' => 'warning'],
            ['id' => 2, 'target' => 'Sách: Ôn thi Tin học 10', 'author' => 'Học viên đã xác thực', 'rating' => 4, 'excerpt' => 'Bài tập bám sát đề thi thật...', 'status' => 'Đang kiểm duyệt', 'tone' => 'warning'],
        ];
    @endphp

    <x-page-header title="Kiểm duyệt đánh giá" subtitle="Chỉ công bố review có sao tổng; có thể ẩn nhận xét không phù hợp mà vẫn công bố sao (9.4)." />

    <x-tabs :tabs="$tabs" />

    <x-data-table :columns="['Đối tượng', 'Người viết', 'Sao', 'Trích đoạn', 'Trạng thái', '']">
        @foreach ($reviews as $r)
            <tr>
                <td class="px-4 py-3 font-medium text-slate-700">{{ $r['target'] }}</td>
                <td class="px-4 py-3 text-slate-500">{{ $r['author'] }}</td>
                <td class="px-4 py-3 text-amber-500">{{ str_repeat('★', $r['rating']) }}</td>
                <td class="px-4 py-3 text-slate-500 max-w-xs truncate">{{ $r['excerpt'] }}</td>
                <td class="px-4 py-3"><x-status-badge :tone="$r['tone']">{{ $r['status'] }}</x-status-badge></td>
                <td class="px-4 py-3 text-right">
                    <a href="{{ route('admin.reviews.show', $r['id']) }}" class="text-rose-600 font-medium">Xem</a>
                </td>
            </tr>
        @endforeach
    </x-data-table>
@endsection

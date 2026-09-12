@extends('layouts.admin')

@section('title', 'Đánh giá')
@section('page-title', 'Kiểm duyệt đánh giá')

@section('content')
    @php
        $tab = $tab ?? 'pending';
        $tabs = $tabs ?? [];
        $reviews = $reviews ?? [];
    @endphp

    <x-ws.page-header title="Kiểm duyệt đánh giá" icon="star" subtitle="Chỉ công bố review có sao tổng; có thể ẩn nhận xét không phù hợp mà vẫn công bố sao (9.4)." />

    <x-ws.tabs :tabs="$tabs" />

    <x-ws.table :columns="['Đối tượng', 'Người viết', 'Sao', 'Trích đoạn', 'Trạng thái', '']">
        @forelse ($reviews as $r)
            <tr>
                <td class="px-4 py-3 font-medium text-slate-700">{{ $r['target'] }}</td>
                <td class="px-4 py-3 text-slate-500">
                    <div class="flex items-center gap-2.5">
                        <x-ws.avatar :name="$r['author']" size="sm" />
                        <span>{{ $r['author'] }}</span>
                    </div>
                </td>
                <td class="px-4 py-3 text-amber-500">{{ str_repeat('★', $r['rating']) }}</td>
                <td class="px-4 py-3 text-slate-500 max-w-xs truncate">{{ $r['excerpt'] }}</td>
                <td class="px-4 py-3"><x-ws.badge :tone="$r['tone']">{{ $r['status'] }}</x-ws.badge></td>
                <td class="px-4 py-3 text-right">
                    <a href="{{ route('admin.reviews.show', $r['id']) }}" class="text-blue-600 font-medium">Xem</a>
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-4 py-6 text-center text-slate-400">Không có đánh giá nào ở trạng thái này.</td></tr>
        @endforelse
    </x-ws.table>
@endsection

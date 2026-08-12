{{--
  Route: admin.content.index | Frame: ADM-03
  Spec: 6.2 (điều kiện phát hành), 6.4 (nhập đề OCR), 6.5 (kho chung/kho riêng).
  TODO controller: truyền $items theo tab đang chọn (materials/questionBanks/assessments/draftQuestions).
--}}
@extends('layouts.admin')

@section('title', 'Nội dung')
@section('page-title', 'Nội dung')

@section('content')
    {{-- Dữ liệu thật do App\Http\Controllers\Admin\ContentController truyền vào. --}}
    @php
        $tab = $tab ?? 'materials';
        $tabs = $tabs ?? [];
        $rows = $rows ?? [];
        $total = $total ?? count($rows);
    @endphp

    <x-page-header title="Nội dung" subtitle="Không sửa âm thầm câu/đề đã có người làm — mọi thay đổi tạo version mới (6.2, 16 mục 2).">
        <x-slot:actions>
            <a href="{{ route('teacher.assessments.import') }}" class="px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium">+ Nhập đề (Word/PDF/OCR)</a>
        </x-slot:actions>
    </x-page-header>

    <x-tabs :tabs="$tabs" />

    @if ($tab === 'drafts')
        <x-empty-state
            title="{{ $total }} câu hỏi đang chờ rà soát"
            description="Kết quả OCR không tự phát hành — vào từng đề để rà soát trước khi chuyển vào kho (6.4)."
            actionLabel="Mở màn rà soát"
            :actionHref="route('teacher.assessments.reviewDraft')" />
    @else
        <x-data-table :columns="['Tên', 'Loại', 'Chủ sở hữu', 'Trạng thái', '']">
            @forelse ($rows as $r)
                <tr>
                    <td class="px-4 py-3 font-medium text-slate-700">{{ $r['title'] }}</td>
                    <td class="px-4 py-3 text-slate-500">{{ $r['type'] }}</td>
                    <td class="px-4 py-3 text-slate-500">{{ $r['owner'] }}</td>
                    <td class="px-4 py-3"><x-status-badge :tone="$r['tone']">{{ $r['status'] }}</x-status-badge></td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('admin.content.show', $r['id']) }}" class="text-rose-600 font-medium">Xem</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-6 text-center text-slate-400">Chưa có dữ liệu.</td></tr>
            @endforelse
        </x-data-table>
        <x-pagination-note :shown="count($rows)" :total="$total" />
    @endif
@endsection

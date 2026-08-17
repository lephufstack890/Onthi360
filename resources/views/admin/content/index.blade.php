{{--
  Route: admin.content.index | Frame: ADM-03
  Spec: 6.2 (điều kiện phát hành), 6.4 (nhập đề OCR), 6.5 (kho chung/kho riêng).
  Dữ liệu thật do App\Http\Controllers\Admin\ContentController truyền vào qua
  App\Services\Admin\ContentService::indexData(). Tab "drafts" hiển thị danh
  sách tài liệu OCR thật ($documents) — rà soát tại admin.content.questions.
  reviewDraft, chuyển vào Kho chung tại App\Services\Admin\DocumentImportService.
--}}
@extends('layouts.admin')

@section('title', 'Nội dung')
@section('page-title', 'Nội dung')

@section('content')
    @php
        $tab = $tab ?? 'materials';
        $tabs = $tabs ?? [];
        $rows = $rows ?? [];
        $documents = $documents ?? [];
        $total = $total ?? count($rows);
    @endphp

    <x-page-header title="🗂️ Nội dung" subtitle="Không sửa âm thầm câu/đề đã có người làm — mọi thay đổi tạo version mới (6.2, 16 mục 2).">
        <x-slot:actions>
            @if ($tab === 'materials')
                <a href="{{ route('admin.content.materials.create') }}" class="px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium">+ Tạo học liệu</a>
            @elseif ($tab === 'questions')
                <a href="{{ route('admin.content.questions.create') }}" class="px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium">+ Tạo câu hỏi</a>
            @elseif ($tab === 'assessments')
                <a href="{{ route('admin.content.assessments.create') }}" class="px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium">+ Tạo đề/bộ bài</a>
            @endif
            <a href="{{ route('admin.content.questions.import') }}" class="px-4 py-2 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium hover:border-rose-200 hover:text-rose-600 transition">+ Nhập đề (Word/PDF/OCR)</a>
        </x-slot:actions>
    </x-page-header>

    @if (session('status') === 'draft-promoted')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã chuyển '.session('promotedCount', 0).' câu vào Kho chung (dạng Nháp) — vào tab "Kho câu hỏi chung" để phát hành từng câu.'])
    @elseif (session('status'))
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã cập nhật nội dung.'])
    @endif

    <x-tabs :tabs="$tabs" />

    @if ($tab === 'drafts')
        <div class="space-y-3">
            @forelse ($documents as $d)
                <div class="bg-white rounded-2xl border border-slate-200 p-4">
                    <div class="flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <x-icon-tile emoji="📄" tone="sky" />
                            <div>
                                <p class="text-sm font-medium text-slate-700">{{ $d['name'] }}</p>
                                <p class="text-xs text-slate-400">Người tải lên: {{ $d['uploader'] }}</p>
                                <div class="w-48 mt-1"><x-progress-bar :percent="$d['progress']" tone="{{ $d['tone'] === 'warning' ? 'warning' : ($d['tone'] === 'danger' ? 'danger' : 'info') }}" /></div>
                            </div>
                        </div>
                        <div class="text-right">
                            <x-status-badge :tone="$d['tone']">{{ $d['status'] }}</x-status-badge>
                            @if ($d['reviewable'])
                                <a href="{{ route('admin.content.questions.reviewDraft', ['document' => $d['id']]) }}" class="block mt-1 text-sm text-rose-600 font-medium">Rà soát ngay ›</a>
                            @endif
                        </div>
                    </div>
                    @if ($d['errorLog'])
                        <p class="text-xs text-rose-600 bg-rose-50 rounded-lg px-3 py-2 mt-3">⚠ {{ $d['errorLog'] }}</p>
                    @endif
                </div>
            @empty
                <x-empty-state
                    title="Không có tài liệu nào đang chờ rà soát"
                    description="Kết quả OCR không tự phát hành — bấm &quot;+ Nhập đề (Word/PDF/OCR)&quot; ở trên để tải Word/PDF lên (6.4)."
                    actionLabel="+ Nhập đề (Word/PDF/OCR)"
                    :actionHref="route('admin.content.questions.import')" />
            @endforelse
        </div>
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

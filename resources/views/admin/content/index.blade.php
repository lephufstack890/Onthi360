@extends('layouts.admin')

@section('title', 'Nội dung')
@section('page-title', 'Nội dung')

@section('content')
    @php
        $tab = $tab ?? 'materials';
        $tabs = $tabs ?? [];
        $rows = $rows ?? [];
        $documents = $documents ?? [];
        $tags = $tags ?? [];
        $total = $total ?? count($rows);
    @endphp

    <x-page-header title="🗂️ Nội dung" subtitle="Không sửa âm thầm câu/đề đã có người làm — mọi thay đổi tạo version mới.">
        <x-slot:actions>
            @if ($tab === 'materials')
                <a href="{{ route('admin.content.materials.create') }}" class="px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium">+ Tạo học liệu</a>
            @elseif ($tab === 'questions')
                <a href="{{ route('admin.content.questions.create') }}" class="px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium">+ Tạo câu hỏi</a>
            @elseif ($tab === 'assessments')
                <a href="{{ route('admin.content.assessments.create') }}" class="px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium">+ Tạo đề/bộ bài</a>
                {{-- SỬA 19/8 (Giai đoạn 3 — "Bộ đề"): tạo nhiều đề PDF cùng lúc, khác hẳn nút
                     "+ Tạo đề/bộ bài" ở trên (tạo TỪNG đề trống 1 lần). --}}
                <a href="{{ route('admin.content.assessments.bulk.create') }}" class="px-4 py-2 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium hover:border-rose-200 hover:text-rose-600 transition">+ Tải bộ đề (nhiều đề PDF)</a>
            @endif
            <a href="{{ route('admin.content.questions.import') }}" class="px-4 py-2 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium hover:border-rose-200 hover:text-rose-600 transition">+ Nhập đề (Word/PDF/OCR)</a>
        </x-slot:actions>
    </x-page-header>

    @if (session('status') === 'assessments-bulk-created')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã tạo '.session('bulkCreatedCount').' đề PDF — vào từng đề để nhập đáp án.'])
    @elseif (session('status') === 'assessment-promoted-shared')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã đưa đề vào Kho chung.'])
    @elseif (session('status') === 'tag-created')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã tạo tag mới.'])
    @elseif (session('status') === 'tag-updated')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã đổi tên tag.'])
    @elseif (session('status') === 'tag-deleted')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã xoá tag.'])
    @elseif (session('status'))
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã cập nhật nội dung.'])
    @endif
    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
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
    @elseif ($tab === 'tags')
        {{-- SỬA 19/8 (Giai đoạn 6 — "Gắn tag/chủ đề cho câu hỏi"): CRUD gọn trong 1 khối,
             không cần trang riêng — xem ContentService::indexData()/tagStore()/tagUpdate()/
             tagDestroy(). Tag dùng để lọc ở màn "Luyện tập theo câu" của học sinh và ở form
             tạo/sửa câu hỏi (Admin + Giáo viên). --}}
        <div class="bg-white rounded-2xl border border-slate-200 p-5 mb-5">
            <h2 class="font-medium text-slate-700 mb-3">+ Thêm tag mới</h2>
            <form method="POST" action="{{ route('admin.content.tags.store') }}" class="flex flex-wrap items-center gap-3">
                @csrf
                <input type="text" name="name" required maxlength="120" placeholder="VD: Đại số, Hình học, Dao động cơ..."
                       class="flex-1 min-w-[220px] rounded-lg border border-slate-200 text-sm p-2.5">
                <button type="submit" class="px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium">Thêm tag</button>
            </form>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 divide-y divide-slate-100">
            @forelse ($tags as $t)
                <div class="flex items-center justify-between gap-3 px-5 py-3" x-data="{ editing: false }">
                    <form method="POST" action="{{ route('admin.content.tags.update', $t['id']) }}" class="flex-1 flex items-center gap-2" x-show="editing" x-cloak>
                        @csrf
                        @method('PUT')
                        <input type="text" name="name" value="{{ $t['name'] }}" required maxlength="120" class="flex-1 rounded-lg border border-slate-200 text-sm p-2">
                        <button type="submit" class="text-sm text-rose-600 font-medium">Lưu</button>
                        <button type="button" @click="editing = false" class="text-sm text-slate-400">Huỷ</button>
                    </form>
                    <div class="flex-1 flex items-center gap-2" x-show="!editing">
                        <span class="text-sm font-medium text-slate-700">{{ $t['name'] }}</span>
                        <span class="text-xs text-slate-400">{{ $t['questionsCount'] }} câu hỏi đang dùng</span>
                    </div>
                    <div class="flex items-center gap-3 shrink-0" x-show="!editing">
                        <button type="button" @click="editing = true" class="text-sm text-slate-500 hover:text-rose-600">Đổi tên</button>
                        <form method="POST" action="{{ route('admin.content.tags.destroy', $t['id']) }}" class="inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-sm text-rose-500 hover:text-rose-700">Xoá</button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="px-5 py-6 text-center text-slate-400 text-sm">Chưa có tag nào — thêm tag đầu tiên ở trên.</div>
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
                    <td class="px-4 py-3 text-right space-x-3 whitespace-nowrap">
                        <a href="{{ route('admin.content.show', $r['id']) }}" class="text-rose-600 font-medium">Xem</a>
                        {{-- SỬA 19/8 (Giai đoạn 4): chỉ đề của giáo viên (tab "Đề/bộ bài") mới có nút
                             này — xem ContentService::indexData()/assessmentPromoteToShared(). --}}
                        @if ($r['canPromoteToShared'] ?? false)
                            <form method="POST" action="{{ route('admin.content.assessments.promoteShared', $r['id']) }}" class="inline">
                                @csrf
                                <button type="submit" class="text-emerald-600 font-medium">Duyệt vào kho chung</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-6 text-center text-slate-400">Chưa có dữ liệu.</td></tr>
            @endforelse
        </x-data-table>
        <x-pagination-note :shown="count($rows)" :total="$total" />
    @endif
@endsection

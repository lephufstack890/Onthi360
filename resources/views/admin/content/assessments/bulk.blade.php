@extends('layouts.admin')

@section('title', 'Tải bộ đề PDF')
@section('page-title', 'Tải bộ đề PDF')

@section('content')
    @php $types = $types ?? []; @endphp

    <style>[x-cloak] { display: none !important; }</style>

    <a href="{{ route('admin.content.index', ['tab' => 'assessments']) }}" class="text-sm text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-rose-600">‹ Quay lại danh sách đề</a>

    <x-page-header title="📚 Tải bộ đề PDF" subtitle="Tạo nhiều đề PDF cùng lúc — đáp án vẫn cần nhập tay sau khi tạo ở từng đề." />

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <div x-data="{ tab: 'split' }">
        <div class="flex gap-2 mb-5 border-b border-slate-200">
            <button type="button" @click="tab = 'split'" :class="tab === 'split' ? 'border-rose-500 text-rose-600' : 'border-transparent text-slate-500'" class="px-4 py-2.5 text-sm font-medium border-b-2 -mb-px">
                Tách từ 1 file PDF lớn
            </button>
            <button type="button" @click="tab = 'multi'" :class="tab === 'multi' ? 'border-rose-500 text-rose-600' : 'border-transparent text-slate-500'" class="px-4 py-2.5 text-sm font-medium border-b-2 -mb-px">
                Tải nhiều file PDF riêng lẻ
            </button>
        </div>

        {{-- Tab 1: tách 1 file PDF lớn theo khoảng trang --}}
        <div x-show="tab === 'split'" x-cloak
             x-data="bulkSplitForm()"
             class="bg-white rounded-2xl border border-slate-200 p-6">
            <form method="POST" action="{{ route('admin.content.assessments.bulk.split') }}" enctype="multipart/form-data" class="space-y-5">
                @csrf

                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="source_pdf">File PDF gốc (gộp nhiều đề nối tiếp nhau)</label>
                    <input id="source_pdf" name="source_pdf" type="file" accept="application/pdf" required
                           class="w-full rounded-lg border border-slate-200 text-sm p-2 file:mr-3 file:rounded-lg file:border-0 file:bg-rose-50 file:text-rose-600 file:px-3 file:py-1.5">
                    <p class="text-xs text-slate-400 mt-1">Tối đa {{ number_format(\App\Services\Admin\ContentService::maxBulkSourcePdfKb() / 1024) }} MB. Hệ thống chỉ cắt đúng số trang bạn khai bên dưới — không đọc nội dung.</p>
                </div>

                <div class="border-t border-slate-100 pt-5">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="font-medium text-slate-700">Khai từng đề trong file</h2>
                        <button type="button" @click="addRow()" class="text-sm text-rose-600 font-medium">+ Thêm đề</button>
                    </div>

                    <div class="space-y-3">
                        <template x-for="(row, index) in rows" :key="index">
                            <div class="rounded-xl border border-slate-200 p-3 grid grid-cols-1 sm:grid-cols-12 gap-3 items-start">
                                <div class="sm:col-span-2">
                                    <label class="block text-xs text-slate-500 mb-1">Mã đề</label>
                                    <input type="text" maxlength="60" :name="`rows[${index}][exam_code]`" x-model="row.exam_code" class="w-full rounded-lg border border-slate-200 text-sm p-2">
                                </div>
                                <div class="sm:col-span-3">
                                    <label class="block text-xs text-slate-500 mb-1">Tên đề</label>
                                    <input type="text" maxlength="255" :name="`rows[${index}][title]`" x-model="row.title" required class="w-full rounded-lg border border-slate-200 text-sm p-2">
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block text-xs text-slate-500 mb-1">Loại</label>
                                    <select :name="`rows[${index}][type]`" x-model="row.type" required class="w-full rounded-lg border border-slate-200 text-sm p-2">
                                        @foreach ($types as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block text-xs text-slate-500 mb-1">Từ trang</label>
                                    <input type="number" min="1" :name="`rows[${index}][from_page]`" x-model="row.from_page" required class="w-full rounded-lg border border-slate-200 text-sm p-2">
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block text-xs text-slate-500 mb-1">Đến trang</label>
                                    <input type="number" min="1" :name="`rows[${index}][to_page]`" x-model="row.to_page" required class="w-full rounded-lg border border-slate-200 text-sm p-2">
                                </div>
                                <div class="sm:col-span-1 flex sm:justify-end pt-5">
                                    <button type="button" @click="rows.splice(index, 1)" class="text-xs text-rose-500 hover:text-rose-700">Xoá</button>
                                </div>
                            </div>
                        </template>
                    </div>
                    <p class="text-xs text-slate-400 mt-2">Ví dụ: file gốc 40 trang gồm 4 đề x 10 trang → khai 4 dòng: 1-10, 11-20, 21-30, 31-40.</p>
                </div>

                <div class="flex gap-3 pt-2 border-t border-slate-100">
                    <button type="submit" class="px-5 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium shadow-sm hover:bg-rose-700 transition">Tách + Tạo đề</button>
                </div>
            </form>
        </div>

        {{-- Tab 2: nhiều file PDF riêng lẻ cùng lúc --}}
        <div x-show="tab === 'multi'" x-cloak
             x-data="bulkMultiForm()"
             class="bg-white rounded-2xl border border-slate-200 p-6">
            <form method="POST" action="{{ route('admin.content.assessments.bulk.multi') }}" enctype="multipart/form-data" class="space-y-5">
                @csrf

                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="files_input">Chọn nhiều file PDF (mỗi file = 1 đề hoàn chỉnh)</label>
                    <input id="files_input" name="files[]" type="file" accept="application/pdf" multiple required @change="onFilesChosen($event)"
                           class="w-full rounded-lg border border-slate-200 text-sm p-2 file:mr-3 file:rounded-lg file:border-0 file:bg-rose-50 file:text-rose-600 file:px-3 file:py-1.5">
                    <p class="text-xs text-slate-400 mt-1">Tối đa {{ number_format(\App\Services\Admin\ContentService::maxPdfKb() / 1024) }} MB mỗi file. Chọn lại toàn bộ nếu cần đổi danh sách file (không xoá được từng file riêng lẻ).</p>
                </div>

                <template x-if="items.length > 0">
                    <div class="border-t border-slate-100 pt-5">
                        <h2 class="font-medium text-slate-700 mb-3">Đặt tên cho từng file (<span x-text="items.length"></span>)</h2>
                        <div class="space-y-3">
                            <template x-for="(item, index) in items" :key="index">
                                <div class="rounded-xl border border-slate-200 p-3 grid grid-cols-1 sm:grid-cols-12 gap-3 items-start">
                                    <div class="sm:col-span-3 text-xs text-slate-400 pt-2 truncate" x-text="item.fileName"></div>
                                    <div class="sm:col-span-2">
                                        <label class="block text-xs text-slate-500 mb-1">Mã đề</label>
                                        <input type="text" maxlength="60" :name="`meta[${index}][exam_code]`" x-model="item.exam_code" class="w-full rounded-lg border border-slate-200 text-sm p-2">
                                    </div>
                                    <div class="sm:col-span-4">
                                        <label class="block text-xs text-slate-500 mb-1">Tên đề</label>
                                        <input type="text" maxlength="255" :name="`meta[${index}][title]`" x-model="item.title" required class="w-full rounded-lg border border-slate-200 text-sm p-2">
                                    </div>
                                    <div class="sm:col-span-3">
                                        <label class="block text-xs text-slate-500 mb-1">Loại</label>
                                        <select :name="`meta[${index}][type]`" x-model="item.type" required class="w-full rounded-lg border border-slate-200 text-sm p-2">
                                            @foreach ($types as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>

                <div class="flex gap-3 pt-2 border-t border-slate-100">
                    <button type="submit" :disabled="items.length === 0" class="px-5 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium shadow-sm hover:bg-rose-700 transition disabled:opacity-50">Tạo <span x-text="items.length"></span> đề</button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
        <script>
            function bulkSplitForm() {
                return {
                    rows: [{ exam_code: '', title: '', type: 'exam', from_page: '', to_page: '' }],
                    addRow() {
                        this.rows.push({ exam_code: '', title: '', type: 'exam', from_page: '', to_page: '' });
                    },
                };
            }

            function bulkMultiForm() {
                return {
                    items: [],
                    onFilesChosen(event) {
                        // Input file KHÔNG cho JS gán lại FileList (chỉ đọc) — form vẫn submit
                        // trực tiếp từ chính #files_input, ở đây chỉ đọc tên file để hiển thị
                        // form đặt tên tương ứng theo ĐÚNG thứ tự trình duyệt sẽ gửi lên.
                        this.items = Array.from(event.target.files).map((file) => ({
                            fileName: file.name,
                            title: file.name.replace(/\.pdf$/i, ''),
                            exam_code: '',
                            type: 'exam',
                        }));
                    },
                };
            }
        </script>
    @endpush
@endsection

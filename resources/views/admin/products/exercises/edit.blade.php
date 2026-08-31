@extends('layouts.admin')

@section('title', 'Sửa bài tập')
@section('page-title', 'Sửa bài tập')

{{--
    SỬA 31/8 ("ZIP bài tập" gắn vào sản phẩm) — form riêng, ĐƠN GIẢN HƠN NHIỀU so với màn Sửa
    câu hỏi Kho chung (admin/content/questions/edit.blade.php): chỉ cho sửa Tiêu đề/Điểm/Tag —
    test case + tệp đính kèm hiện READ-ONLY ở cột bên phải (xem lý do ở
    ContentService::productExerciseSave()). CHỈ Admin vào được màn này (route cùng nhóm
    middleware role:admin,super_admin với admin.products.*).
--}}
@section('content')
    @php
        $allTags = $allTags ?? collect();
        $config = $exercise->grading_config ?? [];
        $testCasesCount = count($config['test_cases'] ?? []);
        $timeLimitMs = $config['time_limit_ms'] ?? 1000;
        $memoryLimitMb = $config['memory_limit_mb'] ?? 256;
        $zipAttachments = $exercise->metadata['attachments'] ?? [];
        $isDraft = $isDraft ?? false;
    @endphp

    <a href="{{ route('admin.products.show', $product->id) }}" class="text-sm text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-rose-600">‹ Quay lại {{ $product->title }}</a>

    <x-page-header title="🧪 Sửa bài tập" :subtitle="$product->title" />

    @if ($isDraft)
        <div class="rounded-xl border border-sky-200 bg-sky-50 p-4 mb-6 text-sm text-sky-800 flex items-start gap-2">
            <span class="shrink-0">ℹ️</span>
            <p>
                Bài tập này vừa đọc xong từ gói ZIP — kiểm tra lại thông tin bên dưới rồi bấm
                <strong>"Lưu bài tập"</strong> để hoàn tất. Nếu bạn rời trang này mà chưa bấm Lưu,
                bài tập sẽ <strong>tự động bị xoá</strong> — không cần thao tác gì thêm, bạn có
                thể chọn ZIP lại bất cứ lúc nào.
            </p>
        </div>
    @endif

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 p-5 space-y-4">
            <form method="POST" action="{{ route('admin.products.exercises.update', [$product->id, $exercise->id]) }}" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="title">Tiêu đề bài tập</label>
                    <input id="title" name="title" type="text" value="{{ old('title', $exercise->title) }}" required maxlength="255"
                           class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="points">Điểm</label>
                    <input id="points" name="points" type="number" min="0" value="{{ old('points', $exercise->points) }}"
                           class="w-40 rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-2">Tag/chuyên đề</label>
                    @if ($allTags->isNotEmpty())
                        <div class="flex flex-wrap gap-2 mb-2">
                            @foreach ($allTags as $tagOption)
                                <label class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full border border-slate-200 text-xs text-slate-600 has-[:checked]:bg-rose-50 has-[:checked]:border-rose-300 has-[:checked]:text-rose-600">
                                    <input type="checkbox" name="tag_ids[]" value="{{ $tagOption->id }}"
                                           @checked(collect(old('tag_ids', $exercise->tags->pluck('id')->all()))->contains((string) $tagOption->id))>
                                    {{ $tagOption->name }}
                                </label>
                            @endforeach
                        </div>
                    @endif
                    <input type="text" name="new_tags" value="{{ old('new_tags') }}" maxlength="500" placeholder="Tag mới, cách nhau bằng dấu phẩy"
                           class="w-full rounded-lg border border-slate-200 text-sm p-2">
                </div>

                <button type="submit" class="w-full px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium hover:bg-rose-700 transition">
                    💾 Lưu bài tập
                </button>
            </form>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 p-5 space-y-4">
            <h3 class="font-medium text-slate-700 flex items-center gap-2"><span>👀</span> Xem trước (chỉ đọc)</h3>
            <p class="text-xs text-slate-400">
                Đề bài, test case và giới hạn chạy đọc thẳng từ gói ZIP — không sửa tay ở đây để
                tránh làm hỏng dữ liệu test case nhiều dòng. Muốn đổi đề/test case, xoá bài này
                rồi thêm lại bằng gói ZIP đã sửa.
            </p>

            <div class="text-sm space-y-2">
                <div class="flex items-center justify-between"><span class="text-slate-500">Số test case</span><span class="font-medium text-slate-700">{{ $testCasesCount }}</span></div>
                <div class="flex items-center justify-between"><span class="text-slate-500">Giới hạn thời gian</span><span class="font-medium text-slate-700">{{ $timeLimitMs }} ms</span></div>
                <div class="flex items-center justify-between"><span class="text-slate-500">Giới hạn bộ nhớ</span><span class="font-medium text-slate-700">{{ $memoryLimitMb }} MB</span></div>
            </div>

            @if (! empty($zipAttachments))
                <div class="pt-3 border-t border-slate-100">
                    <p class="text-xs font-medium text-slate-400 uppercase tracking-wide mb-2">Tệp đính kèm (từ gói ZIP)</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($zipAttachments as $kind => $file)
                            <a href="{{ route('admin.products.exercises.attachment', [$product->id, $exercise->id, $kind]) }}"
                               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 text-xs text-slate-600 hover:border-rose-200 hover:text-rose-600">
                                {{ match ($kind) { 'statement' => '📄 Đề bài', 'solution' => '📄 Lời giải', 'reference' => '💻 Code mẫu', default => $kind } }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection

@extends('layouts.student')

@section('title', 'Luyện tập theo câu')
@section('page-title', 'Luyện tập theo câu')

@section('content')
    {{-- SỬA 9/9 (6) (khách: "hiển thị cho đầy đủ dạng câu hỏi, khi lọc dạng câu hỏi thì hiển thị
         chuyên đề thuộc dạng đó cho đúng... UI làm lại cho đẹp").

         Cách hoạt động: server gửi kèm SỐ CÂU luyện được của từng chuyên đề TÁCH THEO DẠNG
         (PracticeByQuestionService::setupData()). Chọn dạng nào thì Alpine chỉ hiện chuyên đề có
         câu ở dạng đó và bỏ tick những chuyên đề vừa bị ẩn — không còn cảnh mời chọn xong bấm
         vào lại báo "không tìm thấy câu hỏi phù hợp". --}}
    @php
        $practiceTypes = $practiceTypes ?? [];
        $practiceTags = $practiceTags ?? [];
        $practiceTotal = $practiceTotal ?? 0;
        $oldTagIds = array_map('intval', (array) old('tag_ids', []));
    @endphp

    <a href="{{ route('student.practice.index') }}" class="text-sm text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-rose-600">‹ Quay lại Luyện tập</a>

    <div class="rounded-3xl bg-gradient-to-br from-sky-100 via-cyan-50 to-emerald-50 p-6 lg:p-8 mb-6 flex items-center justify-between flex-wrap gap-4">
        <div>
            <p class="text-sm text-sky-600 font-semibold">🎯 Luyện tập theo câu</p>
            <h2 class="text-xl lg:text-2xl font-bold text-slate-800 mt-1">Luyện từng câu, biết đúng/sai ngay</h2>
            <p class="text-sm text-slate-500 mt-1 max-w-xl">
                Chọn dạng câu và chuyên đề muốn ôn — hệ thống trộn ngẫu nhiên câu hỏi rồi cho làm từng câu,
                trả lời xong là biết ngay đúng hay sai. Không tính vào lịch sử làm bài, luyện bao nhiêu lần cũng được.
            </p>
        </div>
        <div class="text-center shrink-0">
            <div class="text-5xl">🧠</div>
            <p class="mt-1 text-xs font-bold text-sky-700">{{ number_format($practiceTotal) }} câu sẵn sàng</p>
        </div>
    </div>

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    @if ($practiceTotal === 0)
        <div class="rounded-2xl border-2 border-dashed border-slate-200 bg-white py-12 text-center">
            <p class="text-4xl mb-2">📭</p>
            <p class="text-sm text-slate-500">Kho luyện tập chưa có câu hỏi nào được phát hành.</p>
            <p class="text-xs text-slate-400 mt-1">Khi thầy cô phát hành câu hỏi, em quay lại đây luyện nhé.</p>
        </div>
    @else
        <form method="POST" action="{{ route('student.practiceByQuestion.start') }}"
              x-data="practiceSetup(@js($practiceTags), '{{ old('type', '') }}', @js($oldTagIds))"
              class="bg-white rounded-2xl border border-slate-200 p-5 lg:p-6 space-y-6">
            @csrf

            {{-- ── Bước 1: dạng câu ── --}}
            <div>
                <div class="flex items-center gap-2 mb-3">
                    <span class="w-6 h-6 rounded-lg bg-rose-100 text-rose-600 text-xs font-bold flex items-center justify-center">1</span>
                    <p class="text-sm font-bold text-slate-800">Chọn dạng câu hỏi</p>
                </div>

                <input type="hidden" name="type" :value="type">

                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2.5">
                    <button type="button" @click="setType('')"
                            class="rounded-2xl border p-3 text-left transition"
                            :class="type === '' ? 'border-rose-400 bg-rose-50 ring-2 ring-rose-100' : 'border-slate-200 hover:border-rose-200'">
                        <span class="text-xl">🌈</span>
                        <span class="block text-[13px] font-bold text-slate-700 mt-1">Tất cả dạng</span>
                        <span class="block text-[11px] text-slate-400">{{ number_format($practiceTotal) }} câu</span>
                    </button>

                    @foreach ($practiceTypes as $t)
                        <button type="button" @click="setType('{{ $t['value'] }}')"
                                @disabled($t['count'] === 0)
                                class="rounded-2xl border p-3 text-left transition disabled:opacity-40 disabled:cursor-not-allowed"
                                :class="type === '{{ $t['value'] }}' ? 'border-rose-400 bg-rose-50 ring-2 ring-rose-100' : 'border-slate-200 hover:border-rose-200'">
                            <span class="text-xl">{{ $t['icon'] }}</span>
                            <span class="block text-[13px] font-bold text-slate-700 mt-1">{{ $t['label'] }}</span>
                            <span class="block text-[11px] text-slate-400">
                                {{ $t['count'] > 0 ? number_format($t['count']).' câu' : 'chưa có câu nào' }}
                            </span>
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- ── Bước 2: chuyên đề ── --}}
            <div class="border-t border-slate-100 pt-5">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                    <div class="flex items-center gap-2">
                        <span class="w-6 h-6 rounded-lg bg-sky-100 text-sky-600 text-xs font-bold flex items-center justify-center">2</span>
                        <p class="text-sm font-bold text-slate-800">Chọn chuyên đề</p>
                        <span class="text-xs text-slate-400">(bỏ trống = luyện tất cả)</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span x-show="selected.length > 0" x-cloak class="text-xs font-bold text-sky-600">
                            Đã chọn <span x-text="selected.length"></span>
                        </span>
                        <button type="button" x-show="selected.length > 0" x-cloak @click="selected = []"
                                class="text-xs text-slate-400 hover:text-rose-600 transition">Bỏ chọn hết</button>
                    </div>
                </div>

                <template x-if="visibleTags.length === 0">
                    <p class="text-sm text-slate-400 py-3">Dạng câu này chưa có chuyên đề nào — cứ bỏ trống, hệ thống sẽ lấy toàn bộ câu của dạng đang chọn.</p>
                </template>

                <div class="flex flex-wrap gap-2">
                    <template x-for="tag in visibleTags" :key="tag.id">
                        <button type="button" @click="toggle(tag.id)"
                                class="inline-flex items-center gap-2 px-3.5 py-2 rounded-full border text-sm transition"
                                :class="selected.includes(tag.id) ? 'border-sky-400 bg-sky-50 text-sky-700 font-semibold' : 'border-slate-200 text-slate-600 hover:border-sky-200'">
                            <span x-text="tag.name"></span>
                            <span class="text-[11px] px-1.5 py-0.5 rounded-full"
                                  :class="selected.includes(tag.id) ? 'bg-sky-200/70 text-sky-800' : 'bg-slate-100 text-slate-500'"
                                  x-text="countFor(tag)"></span>
                        </button>
                    </template>
                </div>

                {{-- Giá trị thật gửi lên server — dựng từ danh sách đang chọn, không phụ thuộc nút bấm. --}}
                <template x-for="id in selected" :key="'input-' + id">
                    <input type="hidden" name="tag_ids[]" :value="id">
                </template>
            </div>

            {{-- ── Tổng kết + nút bắt đầu ── --}}
            <div class="border-t border-slate-100 pt-5 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-slate-500">
                    Sẽ luyện
                    <span class="font-bold text-slate-800" x-text="matchCount"></span>
                    câu
                    <span x-show="type !== ''" x-cloak>dạng <span class="font-semibold text-slate-700" x-text="typeLabel"></span></span>
                    <span x-show="selected.length > 0" x-cloak>· <span x-text="selected.length"></span> chuyên đề</span>
                </p>
                <button type="submit" :disabled="matchCount === 0"
                        class="px-6 py-2.5 rounded-xl bg-rose-600 text-white text-sm font-semibold shadow-sm hover:bg-rose-700 disabled:opacity-50 disabled:cursor-not-allowed transition">
                    Bắt đầu luyện ›
                </button>
            </div>
        </form>
    @endif

    @push('scripts')
        @include('partials.practice-setup-script')
    @endpush
@endsection

@extends('layouts.guest')

@section('title', 'Luyện tập')

@section('content')
    @php
        $items = $items ?? [];
        $canTakeDirectly = $canTakeDirectly ?? false;
        $allTags = $allTags ?? collect();
        $practiceQuestionsCount = $practiceQuestionsCount ?? 0;
        $cardAccent = fn (bool $hasCoding) => $hasCoding
            ? ['tone' => 'amber', 'bar' => 'from-amber-400 to-amber-300']
            : ['tone' => 'emerald', 'bar' => 'from-emerald-400 to-emerald-300'];
        $selectedTagIds = collect(old('tag_ids', []))->map(fn ($v) => (string) $v);
    @endphp

    {{-- SỬA 24/8 — khách chốt: trang này không còn tập trung vào "làm theo đề gồm nhiều câu
         hỏi" nữa (phần đề bên dưới đã ẩn, xem ghi chú ở đó) — hero + toàn bộ nội dung chính đổi
         sang giới thiệu đúng lối "Luyện tập theo câu": chọn dạng câu hỏi + chuyên đề, bấm vào
         là ra trang làm từng câu, biết đúng/sai ngay, bấm "Câu tiếp theo ›".

         SỬA 24/8 (v2) — khách yêu cầu thiết kế lại "to ra vs đẹp", tham khảo cách các trang
         luyện tập/quiz phổ biến (Duolingo, Khan Academy, Quizlet) dựng màn "chọn trước khi
         luyện": thẻ lớn bấm chọn dạng câu hỏi (giống ô kỹ năng lớn của Duolingo) thay cho pill
         nhỏ, dải số liệu to kiểu Khan Academy, dải "3 bước" để người mới hiểu ngay luồng hoạt
         động, chip chuyên đề có đếm số đã chọn + nút xoá lọc bằng Alpine (không tạo route mới). --}}
    <div class="relative overflow-hidden bg-gradient-to-br from-emerald-50 via-white to-sky-50">
        <div class="pointer-events-none absolute -top-24 -right-24 w-96 h-96 rounded-full bg-emerald-200/40 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-32 -left-24 w-96 h-96 rounded-full bg-sky-200/40 blur-3xl"></div>

        <div class="relative max-w-7xl mx-auto px-4 py-14 lg:py-20">
            <div class="flex items-center justify-between flex-wrap gap-10">
                <div class="max-w-2xl">
                    <span class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full bg-white text-emerald-600 text-xs font-semibold mb-5 shadow-sm">🧠 Luyện tập theo câu</span>
                    <h1 class="text-3xl lg:text-5xl font-bold text-slate-800 leading-tight">Luyện theo dạng câu hỏi<br>&amp; chuyên đề bạn chọn</h1>
                    <p class="text-slate-500 mt-4 text-base lg:text-lg max-w-xl">Chọn dạng câu hỏi và chuyên đề muốn ôn ở bên dưới — hệ thống trộn ngẫu nhiên câu hỏi từ Kho chung, làm từng câu, biết ngay đúng/sai rồi qua câu tiếp theo. Ai cũng chọn được bộ lọc; đăng nhập để bắt đầu luyện.</p>
                </div>
                <div class="text-7xl lg:text-8xl hidden sm:block">🧠</div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 lg:gap-4 mt-10">
                <div class="rounded-2xl bg-white shadow-sm border border-white p-4 lg:p-5 flex items-center gap-3">
                    <x-icon-tile emoji="📚" tone="emerald" />
                    <div>
                        <p class="text-xl lg:text-2xl font-bold text-slate-800">{{ $practiceQuestionsCount }}+</p>
                        <p class="text-xs text-slate-400">câu hỏi có thể luyện</p>
                    </div>
                </div>
                <div class="rounded-2xl bg-white shadow-sm border border-white p-4 lg:p-5 flex items-center gap-3">
                    <x-icon-tile emoji="🏷️" tone="sky" />
                    <div>
                        <p class="text-xl lg:text-2xl font-bold text-slate-800">{{ $allTags->count() }}</p>
                        <p class="text-xs text-slate-400">chuyên đề để chọn</p>
                    </div>
                </div>
                <div class="rounded-2xl bg-white shadow-sm border border-white p-4 lg:p-5 flex items-center gap-3">
                    <x-icon-tile emoji="⚡" tone="amber" />
                    <div>
                        <p class="text-xl lg:text-2xl font-bold text-slate-800">Ngay</p>
                        <p class="text-xs text-slate-400">biết đúng/sai từng câu</p>
                    </div>
                </div>
                <div class="rounded-2xl bg-white shadow-sm border border-white p-4 lg:p-5 flex items-center gap-3">
                    <x-icon-tile emoji="♾️" tone="violet" />
                    <div>
                        <p class="text-xl lg:text-2xl font-bold text-slate-800">Không giới hạn</p>
                        <p class="text-xs text-slate-400">luyện lại bao nhiêu lần cũng được</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 py-12 lg:py-16">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 lg:gap-6 mb-12 lg:mb-16">
            <div class="flex items-start gap-4 rounded-2xl bg-white border border-slate-100 p-5">
                <div class="w-9 h-9 rounded-full bg-rose-600 text-white flex items-center justify-center font-bold shrink-0">1</div>
                <div>
                    <p class="font-semibold text-slate-800">Chọn dạng &amp; chuyên đề</p>
                    <p class="text-sm text-slate-500 mt-1">Chọn dạng câu hỏi muốn ôn và (không bắt buộc) một vài chuyên đề cụ thể.</p>
                </div>
            </div>
            <div class="flex items-start gap-4 rounded-2xl bg-white border border-slate-100 p-5">
                <div class="w-9 h-9 rounded-full bg-rose-600 text-white flex items-center justify-center font-bold shrink-0">2</div>
                <div>
                    <p class="font-semibold text-slate-800">Làm từng câu một</p>
                    <p class="text-sm text-slate-500 mt-1">Hệ thống trộn ngẫu nhiên câu hỏi phù hợp, đưa ra lần lượt từng câu.</p>
                </div>
            </div>
            <div class="flex items-start gap-4 rounded-2xl bg-white border border-slate-100 p-5">
                <div class="w-9 h-9 rounded-full bg-rose-600 text-white flex items-center justify-center font-bold shrink-0">3</div>
                <div>
                    <p class="font-semibold text-slate-800">Biết đúng/sai, bấm tiếp</p>
                    <p class="text-sm text-slate-500 mt-1">Trả lời xong biết ngay đúng hay sai, bấm "Câu tiếp theo ›" để luyện tiếp.</p>
                </div>
            </div>
        </div>

        <div id="bo-loc" class="max-w-7xl mx-auto">
            <div class="text-center mb-6">
                <h2 class="text-xl lg:text-2xl font-bold text-slate-800">Bắt đầu luyện ngay</h2>
                <p class="text-sm text-slate-500 mt-1">Chọn bộ lọc bên dưới — không chọn gì cũng luyện được, hệ thống lấy toàn bộ Kho chung.</p>
            </div>

            <form method="{{ $canTakeDirectly ? 'POST' : 'GET' }}"
                  action="{{ $canTakeDirectly ? route('student.practiceByQuestion.start') : route('student.practiceByQuestion.setup') }}"
                  x-data="{ tagCount: {{ $selectedTagIds->count() }} }"
                  class="bg-white rounded-3xl border border-slate-200 shadow-sm p-5 lg:p-8 space-y-8">
                @if ($canTakeDirectly)
                    @csrf
                @endif

                <div>
                    <p class="text-sm font-semibold text-slate-700 mb-3">Dạng câu hỏi</p>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 lg:gap-4">
                        @foreach ([
                            ['value' => '', 'label' => 'Cả 2 dạng', 'desc' => 'Trộn chung cả 2 dạng câu hỏi', 'icon' => '🌈', 'tone' => 'violet'],
                            ['value' => 'mcq', 'label' => 'Trắc nghiệm', 'desc' => 'Chọn 1 đáp án đúng trong các lựa chọn', 'icon' => '🔤', 'tone' => 'sky'],
                            ['value' => 'fill_blank', 'label' => 'Điền đáp án', 'desc' => 'Tự gõ câu trả lời của mình', 'icon' => '✏️', 'tone' => 'amber'],
                        ] as $tf)
                            <label class="group relative flex flex-col gap-3 rounded-2xl border-2 border-slate-200 p-4 lg:p-5 cursor-pointer transition-all hover:border-rose-200 hover:shadow-md has-[:checked]:border-rose-500 has-[:checked]:bg-rose-50 has-[:checked]:shadow-md">
                                <input type="radio" name="type" value="{{ $tf['value'] }}" class="hidden" @checked(old('type', '') === $tf['value'])>
                                <div class="flex items-center justify-between">
                                    <x-icon-tile :emoji="$tf['icon']" :tone="$tf['tone']" />
                                    <span class="w-5 h-5 rounded-full border-2 border-slate-300 group-has-[:checked]:border-rose-500 group-has-[:checked]:bg-rose-500 flex items-center justify-center text-white text-[10px] transition-colors">
                                        <span class="hidden group-has-[:checked]:inline">✓</span>
                                    </span>
                                </div>
                                <div>
                                    <p class="font-semibold text-slate-800">{{ $tf['label'] }}</p>
                                    <p class="text-xs text-slate-500 mt-0.5">{{ $tf['desc'] }}</p>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-sm font-semibold text-slate-700">Chuyên đề <span class="font-normal text-slate-400">(bỏ trống = tất cả)</span></p>
                        <template x-if="tagCount > 0">
                            <button type="button"
                                    @click="$el.closest('form').querySelectorAll('input[name=&quot;tag_ids[]&quot;]').forEach(el => el.checked = false); tagCount = 0"
                                    class="text-xs font-medium text-rose-600 hover:text-rose-700">
                                Đã chọn <span x-text="tagCount"></span> chuyên đề — Xoá lọc
                            </button>
                        </template>
                    </div>
                    @if ($allTags->isNotEmpty())
                        <div class="flex flex-wrap gap-2">
                            @foreach ($allTags as $tagOption)
                                <label class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-full text-sm border border-slate-200 text-slate-600 has-[:checked]:bg-sky-50 has-[:checked]:border-sky-300 has-[:checked]:text-sky-700 transition cursor-pointer">
                                    <input type="checkbox" name="tag_ids[]" value="{{ $tagOption->id }}"
                                           @checked($selectedTagIds->contains((string) $tagOption->id))
                                           @change="tagCount = $el.closest('form').querySelectorAll('input[name=&quot;tag_ids[]&quot;]:checked').length">
                                    {{ $tagOption->name }}
                                </label>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-slate-400">Chưa có chuyên đề nào — bỏ trống để luyện toàn bộ câu hỏi Kho chung.</p>
                    @endif
                </div>

                <div class="rounded-xl bg-sky-50 border border-sky-100 p-3.5 text-xs text-sky-700">
                    Chỉ luyện câu Trắc nghiệm/Điền đáp án đã phát hành (Kho chung + kho giáo viên) — không tính vào lịch sử làm bài, không giới hạn số lần luyện.
                </div>

                <div class="flex flex-col items-center gap-2 pt-1">
                    <button type="submit" class="w-full sm:w-auto px-8 py-3.5 rounded-xl bg-rose-600 text-white font-semibold text-base shadow-sm hover:bg-rose-700 transition-colors">
                        {{ $canTakeDirectly ? 'Bắt đầu luyện ›' : 'Đăng nhập để bắt đầu luyện ›' }}
                    </button>
                    @unless ($canTakeDirectly)
                        <p class="text-xs text-slate-400">Bộ lọc bạn vừa chọn sẽ được giữ lại — đăng nhập xong vào luyện ngay, không cần chọn lại.</p>
                    @endunless
                </div>
            </form>
        </div>

        {{-- Dải lợi ích ngắn — cùng bộ tone icon-tile (rose/sky/violet/amber/emerald) đang dùng
             xuyên suốt trang, để phần cuối trang không bị trống trải sau khi ẩn khối "đề" cũ. --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 lg:gap-6 mt-14 lg:mt-16 max-w-4xl mx-auto">
            <div class="flex items-center gap-3 rounded-2xl bg-white border border-slate-100 p-4">
                <x-icon-tile emoji="🆓" tone="emerald" />
                <p class="text-sm text-slate-600">Miễn phí, luyện bao nhiêu lần cũng được</p>
            </div>
            <div class="flex items-center gap-3 rounded-2xl bg-white border border-slate-100 p-4">
                <x-icon-tile emoji="✅" tone="sky" />
                <p class="text-sm text-slate-600">Biết đúng/sai ngay sau mỗi câu</p>
            </div>
            <div class="flex items-center gap-3 rounded-2xl bg-white border border-slate-100 p-4">
                <x-icon-tile emoji="🎯" tone="amber" />
                <p class="text-sm text-slate-600">Tự chọn đúng dạng &amp; chuyên đề cần ôn</p>
            </div>
        </div>

        {{-- SỬA 24/8 — khách chốt HIỆN TẠI KHÔNG muốn "làm theo đề gồm nhiều câu hỏi" ở trang
             công khai này nữa (thay bằng bộ lọc "Luyện tập theo câu" ở trên) — CHỈ ẨN, không
             xoá gì: $items/$assessments vẫn tính nguyên ở PracticeService::indexData() (dán lại
             khối dưới đây để hiện lại nếu khách đổi ý). LƯU Ý khi cân nhắc bật lại: đây là nơi
             DUY NHẤT ở trang công khai từng cho thấy đề có câu Lập trình (💻 Có lập trình) —
             lối "Luyện tập theo câu" mới KHÔNG hỗ trợ câu Lập trình (chưa có sandbox chấm code,
             xem PracticeByQuestionService) nên ẩn khối này đồng nghĩa đề có code không còn
             hiển thị ở đâu trên trang công khai nữa. --}}
        {{--
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            @forelse ($items as $it)
                @php $accent = $cardAccent($it['hasCoding'] ?? false); @endphp
                <a href="{{ $canTakeDirectly ? route('student.assessment.take', $it['id']) : route('login') }}"
                   class="group relative flex flex-col h-full rounded-2xl bg-white border border-slate-200 p-5 pt-6 overflow-hidden hover:shadow-lg hover:-translate-y-0.5 transition-all">
                    <span class="absolute inset-x-0 top-0 h-1.5 bg-gradient-to-r {{ $accent['bar'] }}"></span>

                    <div class="flex items-center justify-between mb-3">
                        <x-icon-tile emoji="📝" :tone="$accent['tone']" />
                        <div class="flex items-center gap-1.5">
                            @if ($it['hasCoding'] ?? false)
                                <x-status-badge tone="warning">💻 Có lập trình</x-status-badge>
                            @endif
                            <x-status-badge tone="info">{{ $it['itemsCount'] }} câu</x-status-badge>
                        </div>
                    </div>

                    <h3 class="font-semibold text-slate-800 leading-snug line-clamp-2">{{ $it['title'] }}</h3>

                    <div class="flex items-center justify-between mt-3 pt-3 border-t border-slate-100 text-xs text-slate-400">
                        <span>{{ $it['totalPoints'] }} điểm</span>
                        <span>{{ $it['durationMinutes'] ? $it['durationMinutes'].' phút' : 'Không giới hạn' }}</span>
                    </div>

                    <div class="mt-auto pt-4 flex items-center justify-end">
                        <span class="inline-flex items-center gap-1 text-sm font-medium text-rose-600 group-hover:gap-2 transition-all">
                            {{ $canTakeDirectly ? 'Làm bài' : 'Đăng nhập để làm bài' }}
                            <span aria-hidden="true">→</span>
                        </span>
                    </div>
                </a>
            @empty
                <div class="col-span-full">
                    <x-empty-state title="Chưa có bài luyện tập công khai nào" description="Quay lại sau để xem bài mới." />
                </div>
            @endforelse
        </div>
        --}}
    </div>
@endsection

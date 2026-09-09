@extends('layouts.guest')

@section('title', 'Luyện tập')
@section('meta-description', 'Luyện tập trực tuyến miễn phí: chọn dạng câu hỏi (trắc nghiệm, điền đáp án, lập trình, câu nhiều phần) và chuyên đề, làm từng câu và biết ngay đúng hay sai.')

@section('content')
    @php
        $items = $items ?? [];
        $canTakeDirectly = $canTakeDirectly ?? false;
        // SỬA 9/9 (7) — dữ liệu bộ lọc lấy CHUNG với màn học sinh (App\Support\PracticeFilters).
        $practiceTypes = $practiceTypes ?? [];
        $practiceTags = $practiceTags ?? [];
        $practiceTotal = $practiceTotal ?? 0;
        $cardAccent = fn (bool $hasCoding) => $hasCoding
            ? ['tone' => 'amber', 'bar' => 'from-amber-400 to-amber-300']
            : ['tone' => 'emerald', 'bar' => 'from-emerald-400 to-emerald-300'];
        $selectedTagIds = array_map('intval', (array) old('tag_ids', []));
    @endphp

    {{-- SỬA 24/8 — khách chốt: trang này không còn tập trung vào "làm theo đề gồm nhiều câu
         hỏi" nữa (phần đề bên dưới đã ẩn, xem ghi chú ở đó) — hero + toàn bộ nội dung chính đổi
         sang giới thiệu đúng lối "Luyện tập theo câu": chọn dạng câu hỏi + chuyên đề, bấm vào
         là ra trang làm từng câu, biết đúng/sai ngay, bấm "Câu tiếp theo ›".

         SỬA 24/8 (v2) — khách yêu cầu thiết kế lại "to ra vs đẹp", tham khảo cách các trang
         luyện tập/quiz phổ biến (Duolingo, Khan Academy, Quizlet) dựng màn "chọn trước khi
         luyện": thẻ lớn bấm chọn dạng câu hỏi (giống ô kỹ năng lớn của Duolingo) thay cho pill
         nhỏ, dải số liệu to kiểu Khan Academy, dải "3 bước" để người mới hiểu ngay luồng hoạt
         động, chip chuyên đề có đếm số đã chọn + nút xoá lọc bằng Alpine (không tạo route mới).

         SỬA 24/8 (v4) — khách chốt: thêm dạng "Lập trình" vào bộ lọc — hệ thống vẫn CHƯA có
         sandbox chấm code thật, nên câu Lập trình ở màn luyện chỉ được GHI NHẬN bài làm, không
         tự báo đúng/sai như Trắc nghiệm/Điền đáp án (xem PracticeByQuestionService::answer()). --}}
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
                        <p class="text-xl lg:text-2xl font-bold text-slate-800">{{ number_format($practiceTotal) }}+</p>
                        <p class="text-xs text-slate-400">câu hỏi có thể luyện</p>
                    </div>
                </div>
                <div class="rounded-2xl bg-white shadow-sm border border-white p-4 lg:p-5 flex items-center gap-3">
                    <x-icon-tile emoji="🏷️" tone="sky" />
                    <div>
                        <p class="text-xl lg:text-2xl font-bold text-slate-800">{{ count($practiceTags) }}</p>
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

            {{-- SỬA 9/9 (7) (khách: "trang luyện tập ngoài public cũng vậy sửa giúp tôi nha") —
                 dựng lại bộ lọc y như màn học sinh: 2 bước (dạng câu → chuyên đề), thẻ dạng câu
                 có SỐ CÂU thật, dạng nào 0 câu thì mờ và không bấm được, và chuyên đề CHỈ hiện
                 những cái thật sự có câu ở dạng đang chọn (Alpine practiceSetup — xem
                 partials/practice-setup-script.blade.php, dùng chung 2 màn). --}}
            <form method="{{ $canTakeDirectly ? 'POST' : 'GET' }}"
                  action="{{ $canTakeDirectly ? route('student.practiceByQuestion.start') : route('student.practiceByQuestion.setup') }}"
                  x-data="practiceSetup(@js($practiceTags), '{{ old('type', '') }}', @js($selectedTagIds))"
                  class="bg-white rounded-3xl border border-slate-200 shadow-sm p-5 lg:p-8 space-y-8">
                @if ($canTakeDirectly)
                    @csrf
                @endif

                {{-- ── Bước 1: dạng câu ── --}}
                <div>
                    <div class="flex items-center gap-2 mb-3">
                        <span class="w-6 h-6 rounded-lg bg-rose-100 text-rose-600 text-xs font-bold flex items-center justify-center">1</span>
                        <p class="text-sm font-semibold text-slate-700">Chọn dạng câu hỏi</p>
                    </div>

                    <input type="hidden" name="type" :value="type">

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 lg:gap-4">
                        <button type="button" @click="setType('')"
                                class="group flex flex-col gap-3 rounded-2xl border-2 p-4 lg:p-5 text-left transition-all hover:shadow-md"
                                :class="type === '' ? 'border-rose-500 bg-rose-50 shadow-md' : 'border-slate-200 hover:border-rose-200'">
                            <div class="flex items-center justify-between">
                                <x-icon-tile emoji="🌈" tone="violet" />
                                <span class="w-5 h-5 rounded-full border-2 flex items-center justify-center text-white text-[10px] transition-colors"
                                      :class="type === '' ? 'border-rose-500 bg-rose-500' : 'border-slate-300'">
                                    <span x-show="type === ''">✓</span>
                                </span>
                            </div>
                            <div>
                                <p class="font-semibold text-slate-800">Tất cả</p>
                                <p class="text-xs text-slate-500 mt-0.5">Trộn chung mọi dạng câu hỏi</p>
                                <p class="text-xs font-bold text-rose-600 mt-1.5">{{ number_format($practiceTotal) }} câu</p>
                            </div>
                        </button>

                        @foreach ($practiceTypes as $tf)
                            <button type="button" @click="setType('{{ $tf['value'] }}')"
                                    @disabled($tf['count'] === 0)
                                    class="group flex flex-col gap-3 rounded-2xl border-2 p-4 lg:p-5 text-left transition-all hover:shadow-md disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:shadow-none"
                                    :class="type === '{{ $tf['value'] }}' ? 'border-rose-500 bg-rose-50 shadow-md' : 'border-slate-200 hover:border-rose-200'">
                                <div class="flex items-center justify-between">
                                    <x-icon-tile :emoji="$tf['icon']" tone="sky" />
                                    <span class="w-5 h-5 rounded-full border-2 flex items-center justify-center text-white text-[10px] transition-colors"
                                          :class="type === '{{ $tf['value'] }}' ? 'border-rose-500 bg-rose-500' : 'border-slate-300'">
                                        <span x-show="type === '{{ $tf['value'] }}'">✓</span>
                                    </span>
                                </div>
                                <div>
                                    <p class="font-semibold text-slate-800">{{ $tf['label'] }}</p>
                                    <p class="text-xs text-slate-500 mt-0.5">{{ $tf['desc'] }}</p>
                                    <p class="text-xs font-bold mt-1.5 {{ $tf['count'] > 0 ? 'text-rose-600' : 'text-slate-400' }}">
                                        {{ $tf['count'] > 0 ? number_format($tf['count']).' câu' : 'chưa có câu nào' }}
                                    </p>
                                </div>
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- ── Bước 2: chuyên đề ── --}}
                <div>
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                        <div class="flex items-center gap-2">
                            <span class="w-6 h-6 rounded-lg bg-sky-100 text-sky-600 text-xs font-bold flex items-center justify-center">2</span>
                            <p class="text-sm font-semibold text-slate-700">Chọn chuyên đề <span class="font-normal text-slate-400">(bỏ trống = tất cả)</span></p>
                        </div>
                        <button type="button" x-show="selected.length > 0" x-cloak @click="selected = []"
                                class="text-xs font-medium text-rose-600 hover:text-rose-700">
                            Đã chọn <span x-text="selected.length"></span> chuyên đề — Xoá lọc
                        </button>
                    </div>

                    <template x-if="visibleTags.length === 0">
                        <p class="text-sm text-slate-400">Dạng câu này chưa có chuyên đề riêng — cứ bỏ trống, hệ thống lấy toàn bộ câu của dạng đang chọn.</p>
                    </template>

                    <div class="flex flex-wrap gap-2">
                        <template x-for="tag in visibleTags" :key="tag.id">
                            <button type="button" @click="toggle(tag.id)"
                                    class="inline-flex items-center gap-2 px-3.5 py-2 rounded-full text-sm border transition"
                                    :class="selected.includes(tag.id) ? 'bg-sky-50 border-sky-300 text-sky-700 font-semibold' : 'border-slate-200 text-slate-600 hover:border-sky-200'">
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

                <div class="rounded-xl bg-sky-50 border border-sky-100 p-3.5 text-xs text-sky-700">
                    Luyện câu đã phát hành (Kho chung + kho giáo viên) — không tính vào lịch sử làm bài, không giới hạn số lần luyện. Riêng câu Lập trình chưa có chấm tự động nên chỉ ghi nhận bài làm, không báo đúng/sai.
                </div>

                <div class="flex flex-col items-center gap-2 pt-1">
                    <p class="text-sm text-slate-500">
                        Sẽ luyện <span class="font-bold text-slate-800" x-text="matchCount"></span> câu<span x-show="type !== ''" x-cloak> dạng <span class="font-semibold text-slate-700" x-text="typeLabel"></span></span><span x-show="selected.length > 0" x-cloak> · <span x-text="selected.length"></span> chuyên đề</span>
                    </p>
                    <button type="submit" :disabled="matchCount === 0"
                            class="w-full sm:w-auto px-8 py-3.5 rounded-xl bg-rose-600 text-white font-semibold text-base shadow-sm hover:bg-rose-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
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
             khối dưới đây để hiện lại nếu khách đổi ý).
             SỬA 24/8 (v4) — cập nhật lại lưu ý cũ: lúc trước "Luyện tập theo câu" chưa hỗ trợ
             Lập trình nên khối "đề" bên dưới từng là nơi DUY NHẤT thấy đề có câu Lập trình —
             giờ đã hỗ trợ (dù chỉ ghi nhận bài làm, chưa tự chấm) nên việc ẩn khối này không còn
             làm mất hẳn khả năng luyện Lập trình ở trang công khai nữa. --}}
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

    @push('scripts')
        @include('partials.practice-setup-script')
    @endpush
@endsection

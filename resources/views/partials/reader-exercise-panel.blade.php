{{-- ═══════════ CỘT BÀI TẬP CỦA TRÌNH ĐỌC (dùng chung 2 màn) ═══════════
     SỬA 3/10 (khách: "2 cái phải đồng bộ") — tách khỏi student/materials/read.blade.php để màn
     đọc sản phẩm dùng CHUNG. Dựng theo hàm ExerciseItem của bản mẫu MaterialReaderPage.jsx.

     Bên gọi cần có: $exercises, $doneExercises, $exercisePercent, $statusMeta, $isTeacherView
     (tuỳ chọn) và thành phần Alpine onthiMaterialReader. --}}
                    <section class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-[28px] border border-[#D5EAD9] bg-[#F1FAF5] shadow-[0_7px_26px_rgba(45,96,145,0.055)]">
                        <div class="border-b border-[#E5EEF3] px-3 py-3 sm:px-4">
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-2 text-[#126F91]" title="Bài tập gắn với tài liệu">
                                    <x-lucide name="target" class="h-4 w-4" /><span class="text-xs font-semibold">Bài tập</span>
                                </div>
                                <span class="rounded-xl bg-[#EAF5F8] px-2.5 py-1.5 text-[10px] font-semibold text-[#126F91]" title="Tiến độ hoàn thành">{{ $doneExercises }}/{{ count($exercises) }}</span>
                            </div>
                            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-[#EAF0F5]">
                                <div class="h-full rounded-full bg-gradient-to-r from-[#2F9E72] to-[#68C69A]" style="width: {{ $exercisePercent }}%"></div>
                            </div>
                        </div>

                        <div class="flex min-h-0 flex-1 flex-col space-y-2.5 p-3.5 sm:p-4">
                            <div class="relative">
                                <x-lucide name="search" class="absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-[#9AAEBC]" />
                                <input x-model="q" placeholder="Tìm bài tập, hashtag..." aria-label="Tìm bài tập"
                                       class="w-full rounded-xl border border-[#DDEAF0] bg-[#F8FAFB] py-2.5 pl-9 pr-3 text-[11px] text-[#183D5E] outline-none transition placeholder:text-[#9AAEBC] focus:border-[#2D7FA3] focus:ring-2 focus:ring-[#DDF1F6]">
                            </div>

                            <div class="flex gap-1 rounded-xl border border-[#DCE9EE] bg-[#F2F6F8] p-1">
                                @foreach ([['all', 'Tất cả', 'filter'], ['todo', 'Chưa xong', 'play-circle'], ['done', 'Đã xong', 'check-circle-2']] as [$fKey, $fLabel, $fIcon])
                                    <button type="button" @click="filter = '{{ $fKey }}'"
                                            class="flex-1 rounded-lg px-2 py-2 text-[10px] font-bold transition"
                                            :class="filter === '{{ $fKey }}' ? 'bg-[#E2EEEC] text-[#436F6B] shadow-[0_2px_7px_rgba(67,111,107,0.12)]' : 'text-[#61798B] hover:bg-white hover:text-[#436F6B]'">
                                        <x-lucide :name="$fIcon" class="mx-auto h-3.5 w-3.5 sm:mr-1.5 sm:inline" /><span class="hidden sm:inline">{{ $fLabel }}</span>
                                    </button>
                                @endforeach
                            </div>

                            {{-- flex-col chứ không phải space-y: thuộc tính order (dùng để xếp
                                 lại thứ tự sau khi lọc/phân trang) chỉ có tác dụng với con của
                                 flex/grid. --}}
                            <div class="flex min-h-0 flex-1 flex-col gap-2.5 overflow-y-auto pr-0.5">
                                @forelse ($exercises as $ex)
                                    @php
                                        [$exStatusLabel, $exStatusClass, $exStatusIcon, $exSurface] = $statusMeta[$ex['status']] ?? $statusMeta['open'];
                                    @endphp
                                    {{-- SỬA 3/10 — thẻ bài tập soát lại theo ĐÚNG hàm ExerciseItem của
                                         bản mẫu, 3 chỗ trước đây làm thiếu:
                                           · chiều cao CỐ ĐỊNH 104px, các thẻ đều nhau tăm tắp;
                                           · bài ĐANG LÀM thì KHÔNG hiện viên trạng thái, hàng giữa
                                             rút còn 2 cột (sao + điểm) — xem ảnh khách gửi, thẻ
                                             "Luồng cực đại với Dinic" không có viên nào;
                                           · bấm vào tên bài thì thẻ được CHỌN (viền xanh + quầng). --}}
                                    <div x-show="visibleIds.includes({{ $ex['id'] }})" x-cloak
                                         :style="{ order: visibleIds.indexOf({{ $ex['id'] }}) }"
                                         :class="selectedId === {{ $ex['id'] }} ? 'oi-ex-selected ring-2 ring-[#B9DDE8] shadow-[0_3px_10px_rgba(45,96,145,0.08)]' : ''"
                                         class="oi-ex-card flex h-[104px] w-full shrink-0 flex-col justify-between gap-1.5 rounded-2xl border px-3 py-2.5 text-left transition {{ $exSurface }}">
                                        <button type="button" @click="selectedId = {{ $ex['id'] }}"
                                                class="block w-full min-w-0 truncate text-left text-[13px] font-semibold leading-5 text-[#123B68]" title="{{ $ex['title'] }}">{{ $ex['title'] }}</button>

                                        <div class="grid w-full items-center gap-2 {{ $ex['status'] === 'progress' ? 'grid-cols-[minmax(0,1fr)_auto]' : 'grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)_minmax(0,.8fr)]' }}">
                                            @if ($ex['status'] !== 'progress')
                                                <span class="inline-flex min-w-0 items-center justify-center gap-1 rounded-md px-1.5 py-1 text-[9px] font-bold leading-none {{ $exStatusClass }}" title="{{ $exStatusLabel }}">
                                                    <x-lucide :name="$exStatusIcon" class="h-2.5 w-2.5 shrink-0" /><span class="truncate">{{ $exStatusLabel }}</span>
                                                </span>
                                            @endif

                                            <span class="inline-flex items-center justify-center gap-0.5" role="img"
                                                  aria-label="Độ khó {{ $ex['difficultyStars'] }} trên 5 sao" title="{{ $ex['difficultyLabel'] }}">
                                                @for ($i = 1; $i <= 5; $i++)
                                                    {{-- Tô đặc bằng style chứ không bằng thuộc tính fill: thẻ <svg>
                                                         của x-lucide đã có sẵn fill="none" đứng TRƯỚC, mà HTML lấy
                                                         thuộc tính trùng tên ĐẦU TIÊN — truyền fill vào là mất tác
                                                         dụng, sao nào cũng rỗng. CSS fill thì đè được. --}}
                                                    <x-lucide name="star" class="h-3.5 w-3.5 {{ $i <= $ex['difficultyStars'] ? 'text-[#D29A18]' : 'text-[#C7D2D9]' }}"
                                                              style="{{ $i <= $ex['difficultyStars'] ? 'fill: currentColor' : '' }}" />
                                                @endfor
                                            </span>

                                            <span class="justify-self-end whitespace-nowrap pr-1 text-right text-[10px] font-semibold text-[#526B7D]">{{ $ex['points'] }} điểm</span>
                                        </div>

                                        @if ($isTeacherView ?? false)
                                            {{-- Giáo viên KHÔNG có nút "Làm bài" (đúng như màn đọc sản phẩm vẫn
                                                 làm từ 29/9): chỉ xem đề bài, học sinh mới là người làm. --}}
                                            <a href="{{ route('access.resource.exerciseAttachment', [$ex['productId'], $ex['id'], 'statement']) }}"
                                               target="_blank" rel="noopener" title="Xem đề bài" aria-label="Xem đề bài {{ $ex['title'] }}"
                                               class="inline-flex h-8 w-full items-center justify-center gap-1.5 rounded-lg border border-[#DDEAF0] bg-white px-3 text-[11px] font-bold text-[#466278] transition hover:border-[#9DC8D7] hover:bg-[#F0F8FB] hover:text-[#126F91]">
                                                <x-lucide name="file-text" class="h-3.5 w-3.5" /><span>Xem đề</span>
                                            </a>
                                        @else
                                            {{-- Giữ NGUYÊN đường đi cũ của nút Làm bài ở "Tài liệu của tôi":
                                                 POST kèm return_url tương đối để làm xong quay lại đúng trang này. --}}
                                            <form method="POST" action="{{ route('student.practiceByQuestion.startExercise', $ex['id']) }}" class="w-full">
                                                @csrf
                                                <input type="hidden" name="return_url" value="{{ request()->getRequestUri() }}">
                                                <button type="submit" title="Làm bài" aria-label="Làm bài {{ $ex['title'] }}"
                                                        class="inline-flex h-8 w-full items-center justify-center gap-1.5 rounded-lg bg-[#368F72] px-3 text-[11px] font-bold text-white transition hover:bg-[#2F8066] active:scale-[0.98]">
                                                    <x-lucide name="play-circle" class="h-3.5 w-3.5" /><span>Làm bài</span>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                @empty
                                    <div class="rounded-2xl border border-dashed border-[#C9DFE8] bg-[#F8FBFE] p-6 text-center text-[11px] text-[#61798B]">
                                        Tài liệu này chưa gắn bài tập nào.
                                    </div>
                                @endforelse

                                @if (count($exercises) > 0)
                                    <div x-show="filtered.length === 0" x-cloak
                                         class="rounded-2xl border border-dashed border-[#C9DFE8] bg-[#F8FBFE] p-6 text-center text-[11px] text-[#61798B]">
                                        Không tìm thấy bài tập phù hợp.
                                    </div>
                                @endif
                            </div>

                            {{-- Phân trang 3 bài/trang đúng bản mẫu mới. Trước đây danh sách chỉ
                                 cuộn, sản phẩm có vài chục bài là cuộn mỏi tay. --}}
                            <div x-show="pageCount > 1" x-cloak class="flex shrink-0 items-center justify-between gap-2 border-t border-[#DCE9EE] pt-2">
                                <button type="button" @click="page = Math.max(1, currentPage - 1)" :disabled="currentPage === 1" aria-label="Trang trước"
                                        class="grid h-8 w-8 place-items-center rounded-lg border border-[#DDE7EA] bg-white text-[#526B7D] transition hover:bg-[#EAF3F2] disabled:cursor-not-allowed disabled:opacity-40">
                                    <x-lucide name="arrow-left" class="h-3.5 w-3.5" />
                                </button>
                                <span class="text-[10px] font-semibold text-[#61798B]">Trang <span x-text="currentPage"></span> / <span x-text="pageCount"></span></span>
                                <button type="button" @click="page = Math.min(pageCount, currentPage + 1)" :disabled="currentPage === pageCount" aria-label="Trang tiếp theo"
                                        class="grid h-8 w-8 place-items-center rounded-lg border border-[#DDE7EA] bg-white text-[#526B7D] transition hover:bg-[#EAF3F2] disabled:cursor-not-allowed disabled:opacity-40">
                                    <x-lucide name="arrow-right" class="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </div>
                    </section>


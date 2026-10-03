{{-- ═══════════ MỤC HỌC LIỆU CỦA TRÌNH ĐỌC (dùng chung 2 màn) ═══════════
     SỬA 3/10 — tách khỏi student/materials/read.blade.php. Bên gọi truyền $attachments. --}}
                    @if (count($attachments) > 0)
                        {{-- ══════ HỌC LIỆU ══════
                             SỬA 3/10 (khách: "2 cái phải đồng bộ") — khu học sinh trước đây mở
                             màn đọc sản phẩm riêng, ở đó có tab "Học liệu" (audio/ảnh của từng
                             chương + tệp đính kèm). Giờ hai khu dùng CHUNG màn này, nên mang
                             mục đó sang — gộp màn mà không ai mất thứ đang dùng.

                             Để dạng gấp/mở và CHỈ hiện khi tài liệu thật sự có học liệu, nhờ
                             vậy tài liệu không có gì thì bố cục vẫn đúng bản mẫu. --}}
                        <section x-data="{ open: false }"
                                 class="mt-3 overflow-hidden rounded-[28px] border border-[#DDEAF0] bg-white shadow-[0_7px_26px_rgba(45,96,145,0.055)]">
                            <button type="button" @click="open = ! open" :aria-expanded="open"
                                    class="flex w-full items-center justify-between gap-3 px-3 py-3 text-left sm:px-4">
                                <span class="flex items-center gap-2 text-[#126F91]">
                                    <x-lucide name="library" class="h-4 w-4" /><span class="text-xs font-semibold">Học liệu</span>
                                </span>
                                <span class="flex items-center gap-2">
                                    <span class="rounded-xl bg-[#EAF5F8] px-2.5 py-1.5 text-[10px] font-semibold text-[#126F91]">{{ count($attachments) }}</span>
                                    <x-lucide name="chevron-down" class="h-4 w-4 text-[#9AAEBC] transition" ::class="open ? 'rotate-180' : ''" />
                                </span>
                            </button>

                            <div x-show="open" x-cloak class="max-h-[420px] overflow-y-auto border-t border-[#E5EEF3] p-3.5 sm:p-4">
                                @foreach ($attachments as $item)
                                    <div class="mb-2.5 rounded-2xl border border-[#DDEAF0] bg-[#F8FBFC] p-3">
                                        <div class="flex items-start gap-2">
                                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-[#E9F7F8] text-[#23869B]">
                                                <x-lucide :name="$item['kind'] === 'audio' ? 'volume-2' : ($item['kind'] === 'image' ? 'image' : 'paperclip')" class="h-3.5 w-3.5" />
                                            </span>
                                            <div class="min-w-0 flex-1">
                                                <p class="truncate text-[12.5px] font-semibold text-[#123B68]">{{ $item['title'] }}</p>
                                                @if ($item['chapterTitle'])
                                                    <p class="truncate text-[10.5px] text-[#7FA5B8]">{{ $item['chapterTitle'] }}</p>
                                                @endif
                                            </div>
                                        </div>

                                        @if ($item['kind'] === 'audio')
                                            {{-- Nghe ngay trong trang (bài nghe-hiểu), không phải tải về. --}}
                                            <audio controls preload="none" src="{{ $item['url'] }}" class="mt-2 w-full"></audio>
                                        @elseif ($item['kind'] === 'image')
                                            <a href="{{ $item['url'] }}" target="_blank" rel="noopener" class="mt-2 block overflow-hidden rounded-xl border border-[#DDEAF0]">
                                                <img src="{{ $item['url'] }}" alt="{{ $item['title'] }}" loading="lazy" class="w-full">
                                            </a>
                                        @else
                                            <a href="{{ $item['url'] }}" target="_blank" rel="noopener"
                                               class="mt-2 inline-flex items-center gap-1.5 rounded-xl border border-[#DDEAF0] bg-white px-3 py-1.5 text-[11.5px] font-semibold text-[#466278] transition hover:border-[#9DC8D7] hover:bg-[#F0F8FB] hover:text-[#126F91]">
                                                <x-lucide name="download" class="h-3.5 w-3.5" />Mở tệp
                                            </a>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endif

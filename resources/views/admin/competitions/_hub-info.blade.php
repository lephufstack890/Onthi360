{{-- SỬA 24/10 — tóm tắt các trường của phòng thi mới ở trang chi tiết cuộc thi (admin). --}}
@if (\App\Models\Competition::supportsHubFields())
    <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-4 sm:p-5">
        <div class="flex items-center justify-between gap-2 flex-wrap mb-3">
            <h2 class="font-medium text-slate-700">Hiển thị ở trang Cuộc thi</h2>
            <a href="{{ route('competitions.show', $competition->id) }}" target="_blank" rel="noopener" class="text-xs font-bold text-blue-600 hover:text-blue-700">Mở phòng thi ↗</a>
        </div>
        <div class="flex gap-4 flex-wrap">
            <img src="{{ $competition->imageUrl() }}" alt="" style="width:128px;height:72px;object-fit:cover;border-radius:12px;border:1px solid #e0ecf5">
            <div class="min-w-0 flex-1 space-y-1 text-[13px] text-slate-600">
                <p><span class="text-slate-400">Mô tả:</span> {{ $competition->description ?: '— Chưa nhập (dùng thể lệ) —' }}</p>
                <p><span class="text-slate-400">Nhãn mùa giải:</span> {{ $competition->edition_label ?: '—' }}</p>
                <p><span class="text-slate-400">Lộ trình học (khoá học):</span> {{ \App\Models\Competition::supportsCourse() ? ($competition->course?->title ?: '—') : '—' }}</p>
                @if ($competition->isSurvey())
                    @php $pw = $competition->entryPassword(); @endphp
                    <p x-data="{ show: false }"><span class="text-slate-400">Mật khẩu khảo sát:</span>
                        @if ($pw !== null)
                            <code class="rounded bg-emerald-50 px-1.5 py-0.5 text-emerald-700" x-show="show" x-cloak>{{ $pw }}</code>
                            <code class="rounded bg-slate-50 px-1.5 py-0.5 text-slate-500" x-show="!show">••••••</code>
                            <button type="button" class="text-xs font-bold text-blue-600" @click="show = !show" x-text="show ? 'Ẩn' : 'Hiện'">Hiện</button>
                        @else
                            <span class="font-bold text-rose-600">Chưa đặt — học sinh vào thẳng không cần mật khẩu. Đặt ở nút "Sửa cuộc thi".</span>
                        @endif
                    </p>
                @endif
            </div>
        </div>
    </div>
@endif

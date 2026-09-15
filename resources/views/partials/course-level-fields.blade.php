{{-- A10 · Bốn trường "bậc" của khoá học, dùng chung cho màn Thêm và Sửa khoá học.

     Chỉ có ý nghĩa khi khoá học được xếp vào một lộ trình (Lộ trình → Khoá học → Lớp học).
     Khoá lẻ để trống hết vẫn chạy bình thường như trước.

     Tách thành partial để hai màn không lệch nhau khi sau này thêm bớt trường.
     Bên gọi truyền $course (null khi thêm mới). --}}
@php $course = $course ?? null; @endphp

<div class="rounded-2xl border border-sky-100 bg-[#F8FBFE] p-3.5">
    <div class="mb-3 flex items-start gap-2.5">
        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl border border-sky-100 bg-white text-blue-600">
            <x-lucide name="route" class="h-4 w-4" />
        </span>
        <div class="min-w-0">
            <p class="text-[13px] font-bold text-slate-800">Thông tin bậc trong lộ trình</p>
            <p class="mt-0.5 text-[11px] leading-relaxed text-slate-500">
                Điền khi khoá học này là một bậc của lộ trình nào đó. Bỏ trống nếu là khoá lẻ.
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div>
            <label class="mb-1 block text-[11px] font-bold text-slate-600" for="level_code">Mã bậc</label>
            <input id="level_code" name="level_code" type="text" maxlength="60" class="admin-input"
                   value="{{ old('level_code', $course->level_code ?? '') }}" placeholder="FOUNDATION A">
            @error('level_code')<p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="mb-1 block text-[11px] font-bold text-slate-600" for="level_subtitle">Nhãn phụ</label>
            <input id="level_subtitle" name="level_subtitle" type="text" maxlength="60" class="admin-input"
                   value="{{ old('level_subtitle', $course->level_subtitle ?? '') }}" placeholder="CORE">
            @error('level_subtitle')<p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="mb-1 block text-[11px] font-bold text-slate-600" for="outcome">Câu kết quả</label>
            <input id="outcome" name="outcome" type="text" maxlength="160" class="admin-input"
                   value="{{ old('outcome', $course->outcome ?? '') }}" placeholder="Viết code đúng">
            <p class="mt-1 text-[10px] text-slate-400">Một câu ngắn in trên bậc, ví dụ “Làm quen code”.</p>
            @error('outcome')<p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="mb-1 block text-[11px] font-bold text-slate-600" for="session_count">Số buổi thiết kế</label>
            <input id="session_count" name="session_count" type="number" min="1" max="999" class="admin-input"
                   value="{{ old('session_count', $course->session_count ?? '') }}" placeholder="14">
            <p class="mt-1 text-[10px] leading-relaxed text-slate-400">
                Số buổi <strong>theo chương trình</strong>. Khác với số buổi đã xếp lịch thật của từng lớp.
            </p>
            @error('session_count')<p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>@enderror
        </div>
    </div>

    {{-- Khoá đang nằm trong lộ trình nào, bậc mấy — chỉ hiện ở màn Sửa. --}}
    @if ($course && $course->exists)
        @php $coursePaths = $course->learningPaths()->orderBy('title')->get(); @endphp
        <div class="mt-3 border-t border-sky-100 pt-3">
            @forelse ($coursePaths as $lp)
                <a href="{{ route('admin.learning-paths.steps', $lp->id) }}"
                   class="mb-1.5 flex items-center justify-between gap-2 rounded-xl border border-sky-100 bg-white px-3 py-2 transition-colors hover:border-sky-200 hover:bg-sky-50">
                    <span class="min-w-0">
                        <span class="block truncate text-[12px] font-bold text-slate-700">{{ $lp->title }}</span>
                        <span class="block text-[10px] text-slate-400">{{ $lp->gradeLabel() }} · {{ $lp->languageLabel() }}</span>
                    </span>
                    <span class="shrink-0 rounded-lg bg-blue-50 px-2 py-1 text-[11px] font-black text-blue-700">
                        Bậc {{ $lp->pivot->sort_order }}
                    </span>
                </a>
            @empty
                <p class="text-[11px] text-slate-400">Khoá học này chưa nằm trong lộ trình nào.</p>
            @endforelse
        </div>
    @endif
</div>

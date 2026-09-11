@extends('layouts.guest')

@section('title', 'Giáo viên & Chuyên gia Hàng đầu')
@section('meta-description', 'Đội ngũ giáo viên và chuyên gia đã được thẩm định của Ôn Thi 360 — hồ sơ đã duyệt, lớp đang phụ trách, số học viên và đánh giá đã xác thực.')

@section('content')
{{-- ═══════════════ [TEACHERS] MÀN GIÁO VIÊN & CHUYÊN GIA ═══════════════
     SỬA 11/9 — dựng lại theo ĐÚNG source giao diện khách gửi:
     education-main/src/components/TeachersPage.jsx.
     Bố cục/class chép nguyên; React state đổi sang Alpine; mọi nút gắn link thật.

     Dữ liệu lấy từ cơ sở dữ liệu (App\Services\Public\TeacherService::indexData):
       · thẻ giáo viên <- $teachers (chỉ hồ sơ ĐÃ DUYỆT và ĐƯỢC VINH DANH — teacher_profiles
                          is_featured + approval_status=approved, giống hệt luật ở trang chủ)
       · giới thiệu    <- bio            · môn dạy      <- subjects
       · thành tích    <- achievement_note (tách theo dòng / dấu ";")
       · đánh giá      <- rating_summaries (ReviewTargetType::Teacher)
       · lớp phụ trách <- class_rooms đang hoạt động  · học viên <- class_enrollments active

     Bản mẫu để "trường công tác" và ảnh chân dung riêng cho từng thầy cô — hệ thống chưa có
     2 cột đó, nên hiển thị môn dạy đã duyệt và ảnh đại diện mặc định thay vì bịa thông tin. --}}
@php
    $teachers = $teachers ?? [];

    // Ảnh đại diện mặc định — xoay vòng theo id nên mỗi thầy cô có ảnh ổn định giữa các lần tải.
    $teacherAvatars = ['teacher-thanh.png', 'testi-av-3.png', 'testi-av-2.png', 'user-avatar.png', 'testi-av-1.png', 'rank-avatar-4.png'];
    $avatarFor = fn (int $id) => asset('assets/'.$teacherAvatars[$id % count($teacherAvatars)]);

    // Hàng dữ liệu đưa sang Alpine: phân trang 3 thẻ/trang và hộp hồ sơ, đúng như bản mẫu.
    $teacherRows = [];
    foreach ($teachers as $t) {
        $teacherRows[] = [
            'id' => $t['id'],
            'name' => $t['name'],
            'title' => $t['subject'] ? 'Giáo viên '.$t['subject'] : 'Giáo viên đã được thẩm định',
            'school' => count($t['subjects']) > 0 ? implode(' · ', $t['subjects']) : 'Đội ngũ Ôn Thi 360',
            'avatar' => $avatarFor((int) $t['id']),
            'bio' => trim(preg_replace('/\s+/u', ' ', strip_tags((string) $t['bio']))),
            'average' => $t['average'],
            'reviewCount' => $t['reviewCount'],
            'classCount' => $t['classCount'],
            'studentCount' => $t['studentCount'],
            'classRooms' => $t['classRooms'],
        ];
    }
@endphp

<div class="max-w-[1780px] w-full mx-auto px-3 sm:px-5 lg:px-6 2xl:px-10 py-3 sm:py-5">
<div x-data="onthiTeachersPage({{ Js::from(['rows' => $teacherRows, 'pageSize' => 3, 'coursesHref' => route('courses.index')]) }})" class="flex flex-col gap-4">

    {{-- ══════ 1. HERO ══════ --}}
    <div class="relative overflow-hidden rounded-2xl border border-sky-200/90 bg-gradient-to-r from-[#0050A0] via-[#0066CC] to-[#0284C7] p-5 text-white shadow-[0_8px_24px_rgba(0,100,220,0.08)] sm:p-6">
        <img src="{{ asset('assets/hero-teachers.jpg') }}" alt=""
             class="absolute inset-0 h-full w-full object-cover object-right pointer-events-none opacity-35 mix-blend-overlay">

        <div class="relative z-10 max-w-2xl">
            <div class="mb-3 inline-flex items-center gap-1.5 rounded-full bg-amber-400 px-3 py-1 text-[11px] font-bold text-amber-950 shadow-sm">
                <x-lucide name="graduation-cap" class="w-3.5 h-3.5" />
                <span>Đội ngũ Giảng viên &amp; Chuyên gia Tiêu biểu</span>
            </div>

            <h1 class="text-2xl font-bold leading-tight tracking-tight text-white">Giáo viên &amp; Chuyên gia Hàng đầu</h1>

            <p class="mt-2 max-w-2xl text-xs leading-relaxed text-sky-100 sm:text-sm">
                Quy tụ các nhà giáo ưu tú, huấn luyện viên Olympic Tin học và tác giả của những bộ sách chuyên khảo uy tín nhất Việt Nam.
            </p>

            <div class="mt-4 flex flex-wrap gap-2 text-[11px] font-bold text-sky-100">
                <span class="inline-flex items-center gap-1.5 rounded-lg border border-white/20 bg-white/10 px-2.5 py-1.5">
                    <x-lucide name="users" class="h-3.5 w-3.5 text-sky-200" />{{ count($teachers) }} thầy cô đang được vinh danh
                </span>
                <span class="inline-flex items-center gap-1.5 rounded-lg border border-white/20 bg-white/10 px-2.5 py-1.5">
                    <x-lucide name="shield-check" class="h-3.5 w-3.5 text-amber-300" />Hồ sơ đã qua thẩm định
                </span>
            </div>
        </div>
    </div>

    {{-- ══════ 2. THẺ GIÁO VIÊN ══════ --}}
    @if (count($teachers) === 0)
        <div class="rounded-2xl border border-dashed border-sky-200 bg-white p-10 text-center">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-sky-50 text-sky-600">
                <x-lucide name="users" class="h-6 w-6" />
            </div>
            <h2 class="mt-3 text-sm font-bold text-slate-800">Chưa có thầy cô nào được vinh danh</h2>
            <p class="mt-1 text-xs text-slate-500">Danh sách hiện khi quản trị viên vinh danh hồ sơ giáo viên đã duyệt.</p>
            <a href="{{ route('courses.index') }}" class="mt-4 inline-block text-[11px] font-bold text-[#126F91] hover:underline">Xem lớp học đang mở →</a>
        </div>
    @else
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($teachers as $t)
                @php
                    $title = $t['subject'] ? 'Giáo viên '.$t['subject'] : 'Giáo viên đã được thẩm định';
                    $subjectLine = count($t['subjects']) > 0 ? implode(' · ', $t['subjects']) : 'Đội ngũ Ôn Thi 360';
                    $bio = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $t['bio'])));
                @endphp
                <div x-show="visibleIds.includes({{ $t['id'] }})" x-cloak
                     :style="'order:' + visibleIds.indexOf({{ $t['id'] }})"
                     class="group flex flex-col justify-between rounded-2xl border border-sky-100 bg-white p-4 shadow-[0_2px_12px_rgba(0,100,220,0.06)] transition-all hover:border-sky-200 hover:shadow-lg">
                    <div>
                        <div class="mb-3 flex items-center gap-3">
                            <img src="{{ $avatarFor((int) $t['id']) }}" alt="Avatar của {{ $t['name'] }}"
                                 class="h-14 w-14 shrink-0 rounded-xl border-2 border-sky-200 object-cover shadow-sm">
                            <div class="min-w-0">
                                <h3 class="text-sm font-bold leading-5 text-[#0B3C78] transition-colors group-hover:text-blue-600">{{ $t['name'] }}</h3>
                                <p class="text-[11px] font-medium leading-4 text-blue-600">{{ $title }}</p>
                                <p class="truncate text-[11px] text-slate-400">{{ $subjectLine }}</p>
                            </div>
                        </div>

                        <p class="mb-3 line-clamp-3 text-xs leading-relaxed text-slate-600">
                            {{ $bio ?: 'Thầy cô thuộc đội ngũ đã được thẩm định của Ôn Thi 360.' }}
                        </p>

                        @if (count($t['achievements']) > 0)
                            <div class="mb-3 space-y-1.5 rounded-xl border border-sky-100 bg-[#F8FBFE] p-2.5 text-xs text-slate-700">
                                <p class="text-[11px] font-bold text-[#0066CC]">Thành tích tiêu biểu:</p>
                                @foreach (array_slice($t['achievements'], 0, 3) as $a)
                                    <div class="flex items-center gap-2 text-[11px] leading-4">
                                        <x-lucide name="check-circle" class="h-3.5 w-3.5 shrink-0 text-emerald-500" />
                                        <span>{{ $a }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        {{-- Số liệu thật: đánh giá đã xác thực, lớp đang phụ trách, học viên --}}
                        <div class="mb-3 grid grid-cols-3 gap-1.5 text-center">
                            <div class="rounded-xl border border-sky-100 bg-[#F8FBFE] p-1.5">
                                <p class="text-sm font-black leading-tight text-[#0066CC]">{{ $t['average'] !== null ? number_format($t['average'], 1) : '—' }}</p>
                                <p class="mt-0.5 text-[10px] text-slate-500">{{ $t['reviewCount'] }} đánh giá</p>
                            </div>
                            <div class="rounded-xl border border-sky-100 bg-[#F8FBFE] p-1.5">
                                <p class="text-sm font-black leading-tight text-[#3B9374]">{{ $t['classCount'] }}</p>
                                <p class="mt-0.5 text-[10px] text-slate-500">Lớp phụ trách</p>
                            </div>
                            <div class="rounded-xl border border-sky-100 bg-[#F8FBFE] p-1.5">
                                <p class="text-sm font-black leading-tight text-[#AF7C32]">{{ number_format($t['studentCount']) }}</p>
                                <p class="mt-0.5 text-[10px] text-slate-500">Học viên</p>
                            </div>
                        </div>
                    </div>

                    <button type="button" @click="openProfile({{ $t['id'] }})"
                            class="flex min-h-10 w-full cursor-pointer items-center justify-center gap-1.5 rounded-xl bg-[#126F91] px-3 py-2 text-[11px] font-bold text-white shadow-sm transition-colors hover:bg-[#0F5F7A]">
                        <span>Xem hồ sơ &amp; lớp phụ trách</span>
                        <x-lucide name="chevron-right" class="w-3.5 h-3.5" />
                    </button>
                </div>
            @endforeach
        </div>

        {{-- ══════ 3. PHÂN TRANG ══════ --}}
        <nav aria-label="Phân trang danh sách giáo viên và chuyên gia" x-show="totalPages > 1" x-cloak
             class="flex flex-col items-center justify-between gap-2 rounded-2xl border border-sky-100 bg-white p-2.5 shadow-[0_2px_10px_rgba(0,100,220,0.04)] sm:flex-row">
            <span class="text-[11px] text-slate-400">Trang <strong class="text-slate-600" x-text="page"></strong> / <span x-text="totalPages"></span></span>
            <div class="flex items-center gap-1.5">
                <button type="button" aria-label="Trang trước" :disabled="page === 1" @click="pageIndex = Math.max(1, page - 1)"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-sky-100 text-sky-700 transition-colors hover:bg-sky-50 disabled:cursor-not-allowed disabled:opacity-35">
                    <x-lucide name="chevron-left" class="h-4 w-4" />
                </button>
                <template x-for="n in totalPages" :key="'tp' + n">
                    <button type="button" :aria-label="'Trang ' + n" :aria-current="page === n ? 'page' : null" @click="pageIndex = n"
                            class="inline-flex h-9 min-w-9 items-center justify-center rounded-xl px-2 text-[11px] font-bold transition-colors"
                            :class="page === n ? 'bg-[#0066CC] text-white shadow-sm' : 'text-slate-600 hover:bg-sky-50'"
                            x-text="n"></button>
                </template>
                <button type="button" aria-label="Trang sau" :disabled="page === totalPages" @click="pageIndex = Math.min(totalPages, page + 1)"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-sky-100 text-sky-700 transition-colors hover:bg-sky-50 disabled:cursor-not-allowed disabled:opacity-35">
                    <x-lucide name="chevron-right" class="h-4 w-4" />
                </button>
            </div>
        </nav>
    @endif

    {{-- ══════ 4. HỘP HỒ SƠ GIẢNG VIÊN ══════ --}}
    <div x-show="selected" x-cloak @keydown.escape.window="selected = null"
         class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/55 p-3 sm:p-6">
        <section @click.outside="selected = null" class="max-h-[92vh] w-full max-w-3xl overflow-y-auto rounded-3xl bg-white shadow-2xl">
            <template x-if="selected">
                <div>
                    <header class="relative overflow-hidden bg-gradient-to-r from-blue-800 to-sky-500 p-6 text-white">
                        <img src="{{ asset('assets/hero-teachers.jpg') }}" alt="" class="absolute inset-0 h-full w-full object-cover opacity-25">
                        <button type="button" @click="selected = null" class="absolute right-5 top-5 rounded-xl bg-white/15 px-2 py-1 text-xs font-bold">Đóng</button>
                        <div class="relative flex items-center gap-4">
                            <img :src="selected.avatar" alt="" class="h-18 w-18 rounded-2xl border-4 border-white/70 object-cover">
                            <div>
                                <p class="text-xs font-bold text-sky-100">Hồ sơ giảng viên đã xác thực</p>
                                <h2 class="mt-1 text-xl font-black" x-text="selected.name"></h2>
                                <p class="mt-1 text-xs text-sky-100"><span x-text="selected.title"></span> · <span x-text="selected.school"></span></p>
                            </div>
                        </div>
                    </header>

                    <div class="grid gap-5 p-5 md:grid-cols-[1.2fr_.8fr]">
                        <div>
                            <h3 class="text-sm font-black text-slate-800">Giới thiệu</h3>
                            <p class="mt-2 text-xs leading-relaxed text-slate-600"
                               x-text="selected.bio || 'Thầy cô thuộc đội ngũ đã được thẩm định của Ôn Thi 360.'"></p>

                            <h3 class="mt-5 text-sm font-black text-slate-800">Lớp đang phụ trách</h3>
                            <div class="mt-2 space-y-2">
                                <template x-for="room in selected.classRooms" :key="room.id">
                                    <a :href="'{{ route('courses.index') }}'"
                                       class="flex items-center justify-between rounded-xl border border-slate-100 p-3 transition-colors hover:border-sky-200 hover:bg-sky-50/60">
                                        <span>
                                            <span class="block text-xs font-bold text-slate-800" x-text="room.name"></span>
                                            <span class="text-[10px] text-slate-500">
                                                <span x-text="room.code"></span> · <span x-text="room.students"></span> học viên · đang mở
                                            </span>
                                        </span>
                                        <x-lucide name="chevron-right" class="h-4 w-4 text-blue-500" />
                                    </a>
                                </template>
                                <p x-show="selected.classRooms.length === 0" class="rounded-xl border border-dashed border-slate-200 p-3 text-[11px] text-slate-500">
                                    Thầy cô hiện chưa phụ trách lớp nào đang hoạt động.
                                </p>
                            </div>
                        </div>

                        <aside class="rounded-2xl border border-sky-100 bg-sky-50 p-4">
                            <p class="text-3xl font-black text-blue-700" x-text="selected.average !== null ? selected.average.toFixed(1) : '—'"></p>
                            <p class="text-xs font-bold text-slate-700">★ Đánh giá trung bình đã xác thực</p>
                            <div class="mt-4 space-y-2 text-[11px] text-slate-600">
                                <p><b x-text="selected.reviewCount"></b> đánh giá đã kiểm duyệt</p>
                                <p><b x-text="selected.studentCount.toLocaleString('vi-VN')"></b> học viên đang học</p>
                                <p><b x-text="selected.classCount"></b> lớp đang phụ trách</p>
                                <p class="border-t border-sky-100 pt-3">Review hiển thị sau kiểm duyệt; không công khai danh tính học sinh.</p>
                            </div>
                            <a :href="coursesHref" class="mt-5 block w-full rounded-xl bg-blue-600 py-2.5 text-center text-xs font-bold text-white hover:bg-blue-700">Xem lớp phụ trách</a>
                        </aside>
                    </div>
                </div>
            </template>
        </section>
    </div>
</div>
</div>
@endsection

@push('scripts')
    @include('partials.teachers-page-script')
@endpush

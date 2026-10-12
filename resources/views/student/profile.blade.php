@extends('layouts.student')

@section('title', 'Hồ sơ')
@section('page-title', 'Hồ sơ')

@section('content')
    @php
        $user = $user ?? auth()->user();
        $parentLinks = $parentLinks ?? collect();
        $regionOptions = \App\Support\VietnamProvinces::regionOptions();
        $provinceOptions = \App\Support\VietnamProvinces::options();
    @endphp

    @if (session('status') === 'profile-updated')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã lưu thông tin hồ sơ.'])
    @endif

    @include('partials.personal-profile', [
        'user' => $user,
        'action' => route('student.profile.update'),
        'roleLabel' => 'Học sinh',
        'withLocation' => true,
        'profileUrl' => route('student.profile'),
        'passwordUrl' => route('student.password.edit'),
    ])

    {{-- SỬA 12/10 — 2 thẻ theo source RoleWorkspace.jsx (StudentContent → "Hồ sơ"): Hồ sơ học tập + Quyền & liên kết. --}}
    @if (! empty($summary))
        @include('partials.parent-workspace-style')
        <div class="pw mt-6">
            <section class="pw-cols2">
                <div class="pw-card">
                    @include('parent._ct', ['icon' => 'graduation-cap', 'title' => 'Hồ sơ học tập'])
                    <div class="pw-who" style="margin-top:0">
                        <span class="pw-av">@if ($user->avatarUrl())<img src="{{ $user->avatarUrl() }}" alt="">@else{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}@endif</span>
                        <div class="pw-who-body">
                            <p class="pw-li-t">{{ $user->name }}</p>
                            <p class="pw-li-s">{{ $summary['class'] }} · Học sinh</p>
                        </div>
                    </div>
                    <div class="pw-who-row" style="margin-top:14px"><span>Mức độ hoàn thiện hồ sơ</span><b>{{ $summary['completeness'] }}%</b></div>
                    <div class="pw-bar pw-bar--slim" style="margin-top:6px"><i class="{{ $summary['completeness'] >= 75 ? '' : 'a' }}" style="width: {{ $summary['completeness'] }}%"></i></div>
                    <ul class="pw-list" style="margin-top:12px">
                        <li><div style="min-width:0"><p class="pw-li-s" style="margin:0">Khóa học đang theo</p><p class="pw-li-t">{{ $summary['course'] ?? 'Chưa đăng ký lớp nào' }}</p></div></li>
                        <li><div style="min-width:0"><p class="pw-li-s" style="margin:0">Số điện thoại / Tỉnh thành</p><p class="pw-li-t">{{ $user->phone ?: 'Chưa cập nhật' }}{{ $user->province ? ' · '.$user->province : '' }}</p></div></li>
                    </ul>
                </div>
                <div class="pw-card">
                    @include('parent._ct', ['icon' => 'shield-check', 'title' => 'Quyền & liên kết'])
                    <ul class="pw-list">
                        <li>
                            <div style="min-width:0;flex:1">
                                <p class="pw-li-t">Quyền học đang hiệu lực</p>
                                <p class="pw-li-s">{{ $summary['access']['count'] }} học liệu{{ $summary['access']['until'] ? ' · đến '.$summary['access']['until'] : '' }}</p>
                                @if ($summary['access']['expiringSoon'])<p class="pw-li-s" style="color:#b45309">Có quyền sắp hết hạn ({{ $summary['access']['soonest'] }})</p>@endif
                            </div>
                            <a class="pw-btn pw-btn--ghost" style="min-height:34px" href="{{ route('student.library.index') }}">Quản lý</a>
                        </li>
                        <li>
                            <div style="min-width:0;flex:1">
                                <p class="pw-li-t">Phụ huynh đã liên kết</p>
                                <p class="pw-li-s">{{ $summary['parents']['total'] === 0 ? 'Chưa có phụ huynh liên kết' : ($summary['parents']['first'] ?? 'Phụ huynh').' · '.$summary['parents']['verified'].'/'.$summary['parents']['total'].' đã xác minh' }}</p>
                            </div>
                            <span class="pw-chip {{ $summary['parents']['verified'] > 0 ? 'ok' : 'off' }}">{{ $summary['parents']['verified'] > 0 ? 'Đã xác minh' : 'Chưa có' }}</span>
                        </li>
                        <li>
                            <div style="min-width:0;flex:1">
                                <p class="pw-li-t">Mã kích hoạt học liệu</p>
                                <p class="pw-li-s">Nhập mã để mở thêm sách, chuyên đề, bộ đề.</p>
                            </div>
                            <a class="pw-btn pw-btn--ghost" style="min-height:34px" href="{{ route('access.activate') }}">Nhập mã</a>
                        </li>
                    </ul>
                </div>
            </section>
        </div>
    @endif

    <div class="mt-6 space-y-6">
            <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-4 sm:p-5">
                <h3 class="font-medium text-slate-700 mb-2 flex items-center gap-2"><span>👨‍👩‍👧</span> Phụ huynh liên kết</h3>
                <p class="text-[13px] text-slate-400 mb-3">Phụ huynh đã liên kết sẽ xem được lịch, điểm danh, tiến độ và kết quả của bạn.</p>
                @forelse ($parentLinks as $link)
                    <div class="flex items-center justify-between bg-slate-50 rounded-xl px-4 py-3 text-[13px] mb-2">
                        <span class="text-slate-600">{{ $link->parent->name ?? 'Phụ huynh' }}</span>
                        <x-ws.badge :tone="$link->isVerified() ? 'success' : 'warning'">{{ $link->isVerified() ? 'Đã xác minh' : 'Chờ xác minh' }}</x-ws.badge>
                    </div>
                @empty
                    <div class="flex items-center justify-between bg-slate-50 rounded-xl px-4 py-3 text-[13px]">
                        <span class="text-slate-600">Chưa có phụ huynh liên kết</span>
                        <button type="button" class="text-blue-600 font-medium">Tạo mã liên kết</button>
                    </div>
                @endforelse
            </div>

            <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-4 sm:p-5">
                <h3 class="font-medium text-slate-700 mb-2 flex items-center gap-2"><span><x-lucide name="star" class="h-4 w-4" /></span> Đánh giá của tôi</h3>
                <a href="{{ route('student.reviews.index') }}" class="text-[13px] text-blue-600 font-medium">Xem các đánh giá tôi đã viết ›</a>
            </div>
    </div>
@endsection

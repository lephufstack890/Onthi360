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

    {{-- SỬA 12/10 — thẻ "Hồ sơ học tập" + "Quyền & liên kết" theo source mới (RoleWorkspace.jsx → StudentContent → hồ sơ), style .sw của khu Học sinh. --}}
    @include('partials.student-workspace-style')
    @php
        $summary = $summary ?? null;
        $initial = mb_strtoupper(mb_substr($user->name ?? '?', 0, 1));
    @endphp
    <div class="sw" style="margin-top:24px">
        @if (! empty($summary))
            <div class="student-overview-grid is-even">
                <section class="student-overview-card">
                    <h2 class="student-overview-title"><x-lucide name="graduation-cap" class="h-4 w-4" />Hồ sơ học tập</h2>
                    <div class="student-connection-person" style="padding-bottom:4px">
                        @if ($user->avatarUrl())<img src="{{ $user->avatarUrl() }}" alt="">@else<span class="student-connection-avatar">{{ $initial }}</span>@endif
                        <div>
                            <h3>{{ $user->name }}</h3>
                            <p>{{ $summary['class'] }} · Học sinh</p>
                        </div>
                    </div>
                    <div class="student-class-progress" style="margin-top:10px">
                        <div><span>Mức độ hoàn thiện hồ sơ</span><strong>{{ $summary['completeness'] }}%</strong></div>
                        <div style="height:6px;margin-top:6px;border-radius:99px;background:#eaf1f7;overflow:hidden;display:block"><i style="display:block;height:100%;width:{{ $summary['completeness'] }}%;border-radius:99px;background:{{ $summary['completeness'] >= 75 ? '#278680' : '#d99a2b' }}"></i></div>
                    </div>
                    <div class="student-overview-list">
                        <article><p class="student-overview-muted">Khoá học đang theo</p><h3>{{ $summary['course'] ?? 'Chưa đăng ký lớp nào' }}</h3></article>
                        <article><p class="student-overview-muted">Số điện thoại / Tỉnh thành</p><h3>{{ $user->phone ?: 'Chưa cập nhật' }}{{ $user->province ? ' · '.$user->province : '' }}</h3></article>
                    </div>
                </section>

                <section class="student-overview-card">
                    <h2 class="student-overview-title"><x-lucide name="shield-check" class="h-4 w-4" />Quyền &amp; liên kết</h2>
                    <div class="student-overview-list">
                        <article class="student-overview-heading">
                            <div style="min-width:0;flex:1">
                                <h3>Quyền học đang hiệu lực</h3>
                                <p class="student-overview-muted">{{ $summary['access']['count'] }} học liệu{{ $summary['access']['until'] ? ' · đến '.$summary['access']['until'] : '' }}</p>
                                @if ($summary['access']['expiringSoon'])<p class="student-overdue">Có quyền sắp hết hạn ({{ $summary['access']['soonest'] }})</p>@endif
                            </div>
                            <a class="student-overview-action" href="{{ route('student.library.index') }}">Quản lý</a>
                        </article>
                        <article class="student-overview-heading">
                            <div style="min-width:0;flex:1">
                                <h3>Phụ huynh đã liên kết</h3>
                                <p class="student-overview-muted">{{ $summary['parents']['total'] === 0 ? 'Chưa có phụ huynh liên kết' : ($summary['parents']['first'] ?? 'Phụ huynh').' · '.$summary['parents']['verified'].'/'.$summary['parents']['total'].' đã xác minh' }}</p>
                            </div>
                            <a class="student-overview-action" href="{{ route('student.parents.index') }}">Quản lý</a>
                        </article>
                        <article class="student-overview-heading">
                            <div style="min-width:0;flex:1">
                                <h3>Mã kích hoạt học liệu</h3>
                                <p class="student-overview-muted">Nhập mã để mở thêm sách, chuyên đề, bộ đề.</p>
                            </div>
                            <a class="student-overview-action" href="{{ route('access.activate') }}">Nhập mã</a>
                        </article>
                    </div>
                </section>
            </div>
        @endif

        <div class="student-overview-grid is-even">
            <section class="student-overview-card">
                <h2 class="student-overview-title"><x-lucide name="users" class="h-4 w-4" />Phụ huynh liên kết</h2>
                <p class="student-overview-muted">Phụ huynh đã liên kết sẽ xem được lịch, điểm danh, tiến độ và kết quả của bạn.</p>
                @forelse ($parentLinks as $link)
                    <div class="student-connection-person">
                        <span class="student-connection-avatar"><x-lucide name="users" class="h-5 w-5" /></span>
                        <div><h3>{{ $link->parent->name ?? 'Phụ huynh' }}</h3></div>
                        <span class="student-connected">{{ $link->isVerified() ? 'Đã xác minh' : 'Chờ xác minh' }}</span>
                    </div>
                @empty
                    <p class="student-overview-empty">Chưa có phụ huynh liên kết.</p>
                @endforelse
                <a class="student-overview-action" href="{{ route('student.parents.index') }}">Quản lý liên kết</a>
            </section>

            <section class="student-overview-card">
                <h2 class="student-overview-title"><x-lucide name="star" class="h-4 w-4" />Đánh giá của tôi</h2>
                <p class="student-overview-muted">Xem lớp đủ điều kiện đánh giá và các đánh giá bạn đã viết.</p>
                <a class="student-overview-action" href="{{ route('student.reviews.index') }}">Xem các đánh giá tôi đã viết <x-lucide name="arrow-right" class="h-3 w-3" /></a>
            </section>
        </div>
    </div>
@endsection

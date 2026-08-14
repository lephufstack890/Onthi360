{{--
  Route: parent.children.index
  Spec: 10.3 (chỉ thấy con đã liên kết/xác minh) + 3.3 (không browse tìm trẻ em khác).
  Dữ liệu thật do App\Http\Controllers\Parent\ChildController truyền vào. Form "Gửi yêu cầu"
  submit thật qua parent.children.linkRequest -> App\Services\Parent\ChildService::
  requestLink() — tạo ParentLink status=pending, chờ admin xác minh ở admin.users.show
  (10.3: "xác minh phụ huynh chặt chẽ"). Trước đây đây chỉ là ô nhập "mã liên kết" trang trí,
  không submit được, và không có cột "mã" nào trong schema — đổi sang định danh thật: EMAIL
  của học sinh.
--}}
@extends('layouts.parent')

@section('title', 'Con của tôi')
@section('page-title', 'Con của tôi')

@section('content')
    @php
        $children = $children ?? [];
        $childrenStatusMessage = match (session('status')) {
            'link-requested' => 'Đã gửi yêu cầu liên kết — chờ admin xác minh trước khi bạn xem được dữ liệu của con.',
            default => null,
        };
    @endphp

    @if ($childrenStatusMessage)
        @include('partials.toast-flash', ['type' => 'success', 'message' => $childrenStatusMessage])
    @endif
    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <x-page-header title="Con của tôi" subtitle="Chỉ hiển thị học sinh đã liên kết và xác minh — không thể tìm kiếm học sinh khác (10.3)." />

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        @forelse ($children as $c)
            <a href="{{ route('parent.children.show', $c['id']) }}" class="rounded-2xl bg-white border border-slate-200 p-5 hover:shadow-md transition block">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-12 h-12 rounded-full bg-gradient-to-br from-violet-200 to-rose-100 flex items-center justify-center font-medium text-slate-700">
                        {{ mb_substr($c['name'], 0, 1) }}
                    </div>
                    <div>
                        <p class="font-medium text-slate-700">{{ $c['name'] }}</p>
                        <p class="text-xs text-slate-400">Lớp {{ $c['class'] }}</p>
                    </div>
                </div>
                <x-status-badge :tone="$c['tone']">{{ $c['status'] }}</x-status-badge>
            </a>
        @empty
            <div class="col-span-full">
                <x-empty-state title="Chưa liên kết con nào" description="Gửi yêu cầu liên kết bằng email của con ở bên dưới, hoặc yêu cầu giáo viên/admin hỗ trợ xác minh." />
            </div>
        @endforelse
    </div>

    {{-- Form gửi yêu cầu liên kết --}}
    <div class="mt-6 bg-white rounded-2xl border border-slate-200 p-5 max-w-md">
        <h3 class="font-medium text-slate-700 mb-3">Gửi yêu cầu liên kết</h3>
        <form method="POST" action="{{ route('parent.children.linkRequest') }}" class="flex gap-2">
            @csrf
            <input type="email" name="student_email" required maxlength="255" value="{{ old('student_email') }}"
                   class="flex-1 rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition"
                   placeholder="Email của con">
            <button type="submit" class="px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium shrink-0 hover:bg-rose-700 transition">Gửi yêu cầu</button>
        </form>
        <p class="text-xs text-slate-400 mt-2">Yêu cầu cần được admin xác minh trước khi bạn xem được dữ liệu của con.</p>
    </div>
@endsection

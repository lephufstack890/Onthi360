@extends('layouts.teacher')

@section('title', 'Điểm danh')
@section('page-title', 'Điểm danh')

@section('content')
    @php
        $rows = $rows ?? [];
        $sessionResources = $sessionResources ?? [];
        $materialOptions = $materialOptions ?? [];
        $questionOptions = $questionOptions ?? [];
        $assessmentOptions = $assessmentOptions ?? [];
    @endphp

    <a href="{{ route('teacher.schedule.index') }}" class="text-sm text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-rose-600">‹ Quay lại Lịch</a>

    <x-page-header title="Điểm danh" subtitle="{{ $classRoom->name ?? '' }} — {{ $session->starts_at?->format('d/m/Y H:i') ?? '' }}{{ $session->topic ? ' · '.$session->topic : '' }}" />

    @if (session('status') === 'attendance-saved')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã lưu điểm danh.'])
    @elseif (session('status') === 'summary-saved')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã lưu tổng kết buổi học.'])
    @elseif (session('status') === 'resource-added')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã gắn tài nguyên vào buổi học.'])
    @elseif (session('status') === 'resource-removed')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã gỡ tài nguyên khỏi buổi học.'])
    {{-- SỬA 9/9 (4) — thông báo cho các thao tác Hoạt động. Câu chữ nói RÕ học sinh thấy hay chưa,
         vì đây chính là điều dễ hiểu nhầm nhất của tính năng này. --}}
    @elseif (session('status') === 'activity-created')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã tạo hoạt động. Thêm tài nguyên vào rồi bấm ▶ để phát cho học sinh.'])
    @elseif (session('status') === 'activity-published')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã phát hoạt động — học sinh đã thấy được.'])
    @elseif (session('status') === 'activity-unpublished')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã thu hồi hoạt động — học sinh không còn thấy nữa.'])
    @elseif (session('status') === 'activity-deleted')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã xoá hoạt động cùng tài nguyên bên trong.'])
    @endif
    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <form method="POST" action="{{ route('teacher.schedule.attendance.save', $session->id) }}">
        @csrf
        <x-data-table :columns="['Học sinh', 'Có mặt', 'Vắng', 'Vắng có phép', 'Đi trễ', 'Nhận xét', 'Em cần học thêm']">
            @forelse ($rows as $r)
                <tr class="hover:bg-slate-50 align-top">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <img src="https://ui-avatars.com/api/?name={{ urlencode($r['name']) }}&background=e0f2fe&color=0369a1&size=64&bold=true"
                                 alt="{{ $r['name'] }}" class="w-8 h-8 rounded-full shrink-0">
                            <div>
                                <span class="font-medium text-slate-700">{{ $r['name'] }}</span>
                                @if ($r['source'] === 'auto')
                                    <span class="block text-[11px] text-emerald-600 font-medium mt-0.5">✓ Tự động (đã vào làm bài)</span>
                                @endif
                            </div>
                        </div>
                    </td>
                    @foreach (['present' => 'Có mặt', 'absent' => 'Vắng', 'excused' => 'Vắng có phép', 'late' => 'Đi trễ'] as $value => $label)
                        <td class="px-4 py-3 text-center">
                            <input type="radio" name="status[{{ $r['studentId'] }}]" value="{{ $value }}" @checked($r['status'] === $value) class="accent-rose-600">
                        </td>
                    @endforeach
                    <td class="px-4 py-3">
                        <textarea name="note[{{ $r['studentId'] }}]" rows="2"
                                  class="w-48 rounded-lg border border-slate-200 text-xs p-2 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">{{ $r['note'] }}</textarea>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <input type="checkbox" name="needs_more_practice[{{ $r['studentId'] }}]" value="1" @checked($r['needsMorePractice']) class="accent-amber-500 w-4 h-4">
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-4 py-6 text-center text-slate-400">Lớp này chưa có học sinh nào.</td></tr>
            @endforelse
        </x-data-table>

        @if (count($rows) > 0)
            <div class="mt-4">
                <button type="submit" class="px-5 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium shadow-sm hover:bg-rose-700 transition">Lưu điểm danh</button>
            </div>
        @endif
    </form>

    <div class="bg-white rounded-2xl border border-slate-200 p-5 mt-6">
        <h3 class="font-medium text-slate-700 mb-2">Tổng kết buổi học</h3>
        <form method="POST" action="{{ route('teacher.schedule.summary.save', $session->id) }}">
            @csrf
            <textarea name="summary" rows="4" placeholder="Buổi học hôm nay đã dạy gì, học sinh tiếp thu ra sao, cần lưu ý gì cho buổi sau..."
                      class="w-full rounded-lg border border-slate-200 text-sm p-3 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">{{ $session->summary }}</textarea>
            <button type="submit" class="mt-3 px-5 py-2.5 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium hover:border-rose-300 transition">Lưu tổng kết</button>
        </form>
    </div>

    {{-- ═══════════════ HOẠT ĐỘNG BUỔI HỌC ═══════════════
         SỬA 9/9 (4) (khách: "trong mục tài nguyên buổi học thêm mục tạo hoạt động, trong hoạt
         động thì có nhiều tài nguyên buổi học; khi họ thêm tài nguyên xong thì học sinh sẽ chưa
         thấy được ngay mà giáo viên phải click icon play thì học sinh mới thấy được").

         Cấu trúc: buổi học -> nhiều HOẠT ĐỘNG -> mỗi hoạt động nhiều TÀI NGUYÊN.
         Công tắc duy nhất quyết định học sinh thấy hay không là nút ▶ / ⏸ của từng hoạt động
         (App\Models\SessionActivity::published_at) — thêm tài nguyên KHÔNG tự phát cho học sinh. --}}
    @php
        $sessionActivities = $sessionActivities ?? [];
        $looseResources = $looseResources ?? [];
        $publishedCount = collect($sessionActivities)->where('published', true)->count();
        $resourceIcons = [
            'assessment' => '🧾', 'material' => '📘', 'question' => '❓',
            'video' => '🎬', 'link' => '🔗', 'note' => '📝',
        ];
    @endphp

    <div class="bg-white rounded-2xl border border-slate-200 p-5 mt-6">
        <div class="flex flex-wrap items-start justify-between gap-3 mb-1">
            <div class="flex items-start gap-3">
                <span class="w-10 h-10 rounded-xl bg-violet-50 text-violet-600 flex items-center justify-center text-lg shrink-0">🧩</span>
                <div>
                    <h3 class="font-semibold text-slate-800">Hoạt động buổi học</h3>
                    <p class="text-xs text-slate-400 mt-0.5">
                        Mỗi hoạt động gom các bài giao của buổi này. Học sinh <strong>chỉ thấy hoạt động đã phát</strong> —
                        soạn xong thì bấm nút ▶ để phát.
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <span class="px-2.5 py-1 rounded-full bg-slate-100 text-slate-500 text-xs font-bold">{{ count($sessionActivities) }} hoạt động</span>
                @if ($publishedCount > 0)
                    <span class="px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-600 text-xs font-bold">{{ $publishedCount }} đã phát</span>
                @endif
            </div>
        </div>

        {{-- Danh sách hoạt động --}}
        <div class="space-y-3 mt-4">
            @forelse ($sessionActivities as $activity)
                <div class="rounded-2xl border {{ $activity['published'] ? 'border-emerald-200 bg-emerald-50/30' : 'border-slate-200' }} overflow-hidden">
                    <div class="flex flex-wrap items-center gap-3 px-4 py-3 {{ $activity['published'] ? 'bg-emerald-50/60' : 'bg-slate-50/70' }}">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-semibold text-slate-800 truncate">{{ $activity['title'] }}</p>
                                @if ($activity['published'])
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 text-[11px] font-bold">
                                        ● Học sinh đang thấy
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-slate-200 text-slate-600 text-[11px] font-bold">
                                        ○ Đang soạn — học sinh chưa thấy
                                    </span>
                                @endif
                                <span class="text-[11px] text-slate-400">{{ count($activity['resources']) }} tài nguyên</span>
                            </div>
                            @if (! empty($activity['note']))
                                <p class="text-xs text-slate-500 mt-1">{{ $activity['note'] }}</p>
                            @endif
                            @if ($activity['published'] && $activity['publishedAt'])
                                <p class="text-[11px] text-emerald-600 mt-1">Đã phát lúc {{ $activity['publishedAt']->format('H:i d/m/Y') }}</p>
                            @endif
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            {{-- Nút ▶ / ⏸ — công tắc cho học sinh thấy hay không. --}}
                            <form method="POST" action="{{ route('teacher.schedule.activities.publish', [$session->id, $activity['id']]) }}">
                                @csrf
                                @if ($activity['published'])
                                    <button type="submit" title="Thu hồi — học sinh sẽ không thấy nữa"
                                            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-white border border-emerald-300 text-emerald-700 text-sm font-semibold hover:bg-emerald-50 transition">
                                        <span class="text-base leading-none">⏸</span> Thu hồi
                                    </button>
                                @else
                                    <button type="submit" title="Phát cho học sinh xem"
                                            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700 shadow-sm transition">
                                        <span class="text-base leading-none">▶</span> Phát cho học sinh
                                    </button>
                                @endif
                            </form>

                            <form method="POST" action="{{ route('teacher.schedule.activities.destroy', [$session->id, $activity['id']]) }}"
                                  onsubmit="return confirm('Xoá hoạt động &quot;{{ $activity['title'] }}&quot; cùng {{ count($activity['resources']) }} tài nguyên bên trong?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" title="Xoá hoạt động"
                                        class="w-9 h-9 rounded-xl text-slate-300 hover:text-rose-600 hover:bg-rose-50 transition">✕</button>
                            </form>
                        </div>
                    </div>

                    <div class="px-4 py-3 bg-white">
                        {{-- Tài nguyên trong hoạt động --}}
                        @forelse ($activity['resources'] as $res)
                            <div class="flex items-center gap-3 py-2 {{ ! $loop->last ? 'border-b border-slate-100' : '' }}">
                                <span class="text-base shrink-0">{{ $resourceIcons[$res['type']] ?? '📄' }}</span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm text-slate-700 truncate">{{ $res['title'] }}</p>
                                    <p class="text-[11px] text-slate-400">{{ $res['typeLabel'] }}</p>
                                </div>
                                <form method="POST" action="{{ route('teacher.schedule.resources.delete', [$session->id, $res['id']]) }}" class="shrink-0">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs text-slate-400 hover:text-rose-600 transition">Gỡ</button>
                                </form>
                            </div>
                        @empty
                            <p class="text-xs text-slate-400 py-2">Chưa có tài nguyên nào — chọn bài giao bên dưới để thêm.</p>
                        @endforelse

                        {{-- Thêm tài nguyên vào ĐÚNG hoạt động này --}}
                        <form method="POST" action="{{ route('teacher.schedule.resources.save', $session->id) }}"
                              class="flex flex-wrap items-end gap-2 mt-3 pt-3 border-t border-dashed border-slate-200">
                            @csrf
                            <input type="hidden" name="type" value="assessment">
                            <input type="hidden" name="activity_id" value="{{ $activity['id'] }}">
                            <div class="flex-1 min-w-[220px]">
                                <label class="text-[11px] text-slate-500" for="assessment_{{ $activity['id'] }}">Thêm bài giao vào hoạt động này</label>
                                <x-select id="assessment_{{ $activity['id'] }}" name="assessment_id" class="mt-1 w-full">
                                    @if (empty($assessmentOptions))
                                        <option value="">— Bạn chưa tạo đề nào —</option>
                                    @else
                                        @foreach ($assessmentOptions as $opt)
                                            <option value="{{ $opt['id'] }}">{{ $opt['title'] }}</option>
                                        @endforeach
                                    @endif
                                </x-select>
                            </div>
                            <button type="submit" @disabled(empty($assessmentOptions))
                                    class="px-4 py-2 rounded-xl bg-slate-800 text-white text-xs font-semibold hover:bg-slate-900 disabled:opacity-50 disabled:cursor-not-allowed transition">
                                + Thêm
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="rounded-2xl border-2 border-dashed border-slate-200 py-8 text-center">
                    <p class="text-3xl mb-2">🧩</p>
                    <p class="text-sm text-slate-500">Buổi học chưa có hoạt động nào.</p>
                    <p class="text-xs text-slate-400 mt-1">Tạo hoạt động đầu tiên ở ô bên dưới, thêm bài giao vào, rồi bấm ▶ để phát cho học sinh.</p>
                </div>
            @endforelse
        </div>

        {{-- Tạo hoạt động mới --}}
        <form method="POST" action="{{ route('teacher.schedule.activities.store', $session->id) }}"
              class="mt-4 rounded-2xl border border-dashed border-violet-200 bg-violet-50/40 p-4">
            @csrf
            <p class="text-[13px] font-bold text-violet-700 mb-2">＋ Tạo hoạt động mới</p>
            <div class="flex flex-wrap items-end gap-2">
                <div class="flex-1 min-w-[200px]">
                    <label class="text-[11px] text-slate-500" for="activity_title">Tên hoạt động</label>
                    <input id="activity_title" name="title" type="text" maxlength="255" required
                           placeholder="VD: Khởi động đầu giờ / Luyện tập tại lớp / Bài về nhà"
                           class="mt-1 w-full rounded-lg border border-slate-200 text-sm p-2">
                </div>
                <div class="flex-1 min-w-[200px]">
                    <label class="text-[11px] text-slate-500" for="activity_note">Ghi chú (tuỳ chọn)</label>
                    <input id="activity_note" name="note" type="text" maxlength="1000"
                           placeholder="VD: Làm trong 15 phút đầu"
                           class="mt-1 w-full rounded-lg border border-slate-200 text-sm p-2">
                </div>
                <button type="submit" class="px-4 py-2 rounded-xl bg-violet-600 text-white text-sm font-semibold hover:bg-violet-700 transition">
                    Tạo hoạt động
                </button>
            </div>
        </form>

        {{-- Tài nguyên gắn từ trước khi có tính năng Hoạt động — giữ lại để không mất dữ liệu cũ. --}}
        @if (! empty($looseResources))
            <div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50/50 p-4">
                <p class="text-[13px] font-bold text-amber-700">Tài nguyên chưa thuộc hoạt động nào ({{ count($looseResources) }})</p>
                <p class="text-[11px] text-amber-600 mt-0.5 mb-2">Đây là tài nguyên gắn trước khi có mục Hoạt động. Học sinh <strong>không thấy</strong> những mục này — tạo hoạt động rồi thêm lại nếu còn cần.</p>
                @foreach ($looseResources as $res)
                    <div class="flex items-center gap-3 py-1.5">
                        <span class="text-base shrink-0">{{ $resourceIcons[$res['type']] ?? '📄' }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm text-slate-700 truncate">{{ $res['title'] }}</p>
                            <p class="text-[11px] text-slate-400">{{ $res['typeLabel'] }}</p>
                        </div>
                        <form method="POST" action="{{ route('teacher.schedule.resources.delete', [$session->id, $res['id']]) }}" class="shrink-0">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-xs text-slate-400 hover:text-rose-600 transition">Gỡ</button>
                        </form>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection

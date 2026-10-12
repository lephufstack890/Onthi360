{{--
  Route: parent.dashboard | Frame: PAR-01
  SỬA 12/10 — dựng lại theo source mới: education-main/src/components/RoleWorkspace.jsx → ParentOverview().
  Thứ tự khối bám bản mẫu: Hero → thẻ tóm tắt (chọn con + tiến độ) → 3 thẻ số → "Gợi ý đồng hành"
  + "Lịch & điểm danh" → "Thông báo từ lớp". Dữ liệu thật do Parent\DashboardController truyền
  vào (App\Services\Parent\ParentWorkspaceService::snapshot); chỉ con ĐÃ XÁC MINH (10.3).
--}}
@extends('layouts.parent')

@section('title', 'Tổng quan')
@section('page-title', 'Tổng quan')

@section('content')
    @include('partials.parent-workspace-style')
    @php
        $parentName = auth()->user()->name ?? 'Phụ huynh';
        $single = count($viewChildren) === 1 ? $viewChildren[0] : null;
        $who = $single['name'] ?? 'Các con';
        $noteTone = ['success' => 'pw-t-emerald', 'info' => 'pw-t-blue', 'warning' => 'pw-t-amber', 'danger' => 'pw-t-rose'];
        $latest = $latestResult;
    @endphp

    <div class="pw">
        @include('parent._hero', [
            'title' => $hasChildren ? 'Chào '.$parentName.', '.($single ? $single['name'].' đang được theo dõi sát sao' : 'tình hình học tập của các con') : 'Chào '.$parentName.', hãy liên kết với con để bắt đầu',
            'desc' => 'Lịch học, điểm danh, bài được giao và kết quả của con được cập nhật từ hoạt động học tập thực tế.',
            'cta' => ['label' => 'Xem tiến độ', 'href' => route('parent.results.index', ['child' => $selected]), 'icon' => 'trending-up'],
        ])

        @if (! $hasChildren)
            @include('parent._no-children')
        @else
            <section class="pw-card pw-card--soft pw-sum">
                <div class="pw-sum-top">
                    <div style="min-width:0">
                        <p class="pw-eyebrow">Theo dõi hành trình học tập</p>
                        <h2>{{ $single ? 'Tình hình học tập của '.$single['name'] : 'Tình hình học tập của các con' }}</h2>
                        <p class="pw-sub">{{ $single ? $single['class'].' · ' : '' }}dữ liệu được cập nhật từ hoạt động học tập gần nhất</p>
                    </div>
                    @include('parent._picker')
                </div>
                <div class="pw-who">
                    <span class="pw-av">@if ($single && $single['avatar'])<img src="{{ $single['avatar'] }}" alt="{{ $single['name'] }}">@elseif ($single){{ $single['initial'] }}@else<x-lucide name="users" style="width:18px;height:18px" />@endif</span>
                    <div class="pw-who-body">
                        <div class="pw-who-row"><span>Tiến độ lớp học{{ $single ? ' của '.$single['name'] : '' }}</span><b>{{ $progressPercent }}%</b></div>
                        <div class="pw-bar"><i style="width: {{ $progressPercent }}%"></i></div>
                    </div>
                </div>
                <p class="pw-note">
                    @if ($totalSessions > 0)Đã diễn ra {{ $endedSessions }}/{{ $totalSessions }} buổi học
                    @else Chưa có buổi học nào được lên lịch @endif
                    @if ($latest && $latest['delta'] !== null) · điểm gần nhất {{ $latest['delta'] >= 0 ? 'tăng' : 'giảm' }} {{ rtrim(rtrim(number_format(abs($latest['delta']), 2, '.', ''), '0'), '.') ?: '0' }} điểm so với lần trước @endif
                </p>
            </section>

            <div class="pw-grid3">
                @include('parent._stat', ['label' => 'Điểm danh', 'value' => $attendancePercent !== null ? $attendancePercent.'%' : '—', 'note' => $pastSessions > 0 ? 'Có mặt '.$presentSessions.'/'.$pastSessions.' buổi đã học' : 'Chưa có buổi học nào', 'icon' => 'check-circle', 'tone' => 'emerald'])
                @include('parent._stat', ['label' => 'Tiến độ học', 'value' => $progressPercent.'%', 'note' => $endedSessions.'/'.$totalSessions.' buổi đã diễn ra', 'icon' => 'notebook-pen', 'tone' => 'blue'])
                @include('parent._stat', ['label' => 'Điểm gần nhất', 'value' => $latest['score'] ?? '—', 'note' => $latest ? $latest['title'].($latest['delta'] !== null ? ' · '.($latest['delta'] >= 0 ? 'tăng ' : 'giảm ').rtrim(rtrim(number_format(abs($latest['delta']), 2, '.', ''), '0'), '.').' điểm' : '') : 'Chưa có bài nào được chấm', 'icon' => 'trophy', 'tone' => 'amber'])
            </div>

            <div class="pw-cols">
                <section class="pw-card">
                    @include('parent._ct', ['icon' => 'sparkles', 'title' => 'Gợi ý đồng hành hôm nay', 'action' => 'Xem tiến độ', 'href' => route('parent.results.index', ['child' => $selected])])
                    @if ($tip)
                        <div class="pw-tip">
                            <span class="pw-tip-ic"><x-lucide name="heart" /></span>
                            <div style="min-width:0;flex:1">
                                <h3>{{ $tip['title'] }}</h3>
                                <p>{{ $tip['text'] }}</p>
                                @if ($tip['route'])<a class="pw-btn" href="{{ route($tip['route'], ['child' => $selected]) }}">{{ $tip['cta'] }}</a>@endif
                            </div>
                        </div>
                    @endif
                </section>

                <aside class="pw-card">
                    @include('parent._ct', ['icon' => 'calendar-days', 'title' => 'Lịch & điểm danh', 'action' => 'Mở lịch', 'href' => route('parent.schedule.index', ['child' => $selected])])
                    <ul class="pw-list">
                        @forelse (array_slice($upcoming, 0, 3) as $s)
                            <li>
                                <div class="pw-date {{ $s['today'] ? 'today' : '' }}"><b>{{ $s['when'] }}</b><span>{{ $s['start'] }}</span></div>
                                <div style="min-width:0">
                                    <p class="pw-li-t">{{ $s['title'] }}</p>
                                    <p class="pw-li-s">{{ $s['child'] ? $s['child'].' · ' : '' }}{{ $s['class'] }}</p>
                                </div>
                            </li>
                        @empty
                            <li class="pw-empty" style="display:block;width:100%">Chưa có buổi học sắp tới.</li>
                        @endforelse
                        @if ($pastSessions > 0)
                            <li><p class="pw-li-s ok" style="margin:0">Đã điểm danh {{ $presentSessions }}/{{ $pastSessions }} buổi đã học</p></li>
                        @endif
                    </ul>
                </aside>
            </div>
        @endif

        <section class="pw-card">
            @include('parent._ct', ['icon' => 'message-square-text', 'title' => 'Thông báo từ lớp', 'action' => 'Xem tất cả', 'href' => route('parent.notifications.index')])
            <div class="pw-notes">
                @forelse ($notes as $n)
                    <a class="pw-note-card" href="{{ route('notifications.read', $n['id']) }}">
                        <span class="pw-note-ic {{ $noteTone[$n['tone'] ?? 'info'] ?? 'pw-t-blue' }}"><x-lucide name="message-square-text" /></span>
                        <div style="min-width:0"><b>{{ $n['title'] }}</b><em>{{ $n['time'] }}</em></div>
                    </a>
                @empty
                    <p class="pw-empty" style="grid-column:1/-1">Chưa có thông báo nào.</p>
                @endforelse
            </div>
        </section>
    </div>
@endsection

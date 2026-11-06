{{-- SỬA 6/11 — Tab 5 · Bài tập / Đề luyện tập. Danh sách bài giao + giao đề (form cũ teacher.classes.assign, đặt trong popup) +
     số em đã nộp + nhắc em chưa nộp. Dữ liệu gốc: ClassRoomService::showForTeacher('assign'); số liệu: ClassSpaceService::assignmentStats. --}}
@php
    $assignments = $assignments ?? [];
    $assignableAssessments = $assignableAssessments ?? [];
    $asSt = $spaceData ?? [];
    $asRoster = (int) ($asSt['roster'] ?? 0);
    $asCan = ($space['can']['materials'] ?? false) && ! auth()->user()->isPermissionRevoked('teacher.assign-assessment');
    $asTone = ['Đang mở' => 'p-late', 'Đã lên lịch' => 'p-ex', 'Đã đóng' => 'p-gray', 'Nháp' => 'p-gray', 'Đã lưu trữ' => 'p-gray'];
    $asFilterOf = fn ($l) => match ($l) { 'Đang mở' => 'open', 'Đã lên lịch' => 'sched', default => 'closed' };
    $asCount = collect($assignments)->countBy(fn ($a) => $asFilterOf($a['statusLabel']));
@endphp
@if (session('status') === 'class-exam-assigned')
    @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã giao cho lớp — học sinh thấy ở mục Bài được giao.'])
@endif
@if ($errors->any())
    @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
@endif

<div class="csp-card" x-data="{ f: 'all', open: {{ $errors->has('assessment_id') || old('assessment_id') ? 'true' : 'false' }}, opens: '', closes: '' }" @keydown.escape.window="open = false">
    <div class="csp-ch">
        <span class="csp-ic o"><x-lucide name="clipboard-list" /></span>
        <div class="grow"><h3>Bài tập / Đề luyện tập</h3><p>Giao cho cả lớp, theo dõi em nào đã nộp, nhắc em chưa nộp.</p></div>
        <nav class="csp-seg">
            <button type="button" :class="{ on: f === 'all' }" @click="f = 'all'">Tất cả {{ count($assignments) }}</button>
            <button type="button" :class="{ on: f === 'open' }" @click="f = 'open'">Đang mở {{ $asCount['open'] ?? 0 }}</button>
            <button type="button" :class="{ on: f === 'sched' }" @click="f = 'sched'">Hẹn giờ {{ $asCount['sched'] ?? 0 }}</button>
            <button type="button" :class="{ on: f === 'closed' }" @click="f = 'closed'">Đã đóng {{ $asCount['closed'] ?? 0 }}</button>
        </nav>
        @if ($asCan)
            <button type="button" class="csp-btn pri" @click="open = true"><x-lucide name="plus" />Giao bài / đề</button>
        @endif
    </div>

    @if (empty($assignments))
        <div class="csp-empty"><x-lucide name="clipboard-list" /><b>Lớp chưa có bài nào được giao</b><span>Bấm <b>Giao bài / đề</b> để chọn đề có sẵn trong kho của bạn.</span></div>
    @else
        <div class="asg-grid">
            @foreach ($assignments as $ag)
                @php
                    $done = (int) ($asSt['submitted'][$ag['id']] ?? 0);
                    $wait = (int) ($asSt['grading'][$ag['id']] ?? 0);
                    $pct = $asRoster > 0 ? min(100, round($done / $asRoster * 100)) : 0;
                    $isExam = in_array($asSt['types'][$ag['id']] ?? '', ['exam', 'test', 'contest'], true);
                @endphp
                <article class="asg-card" x-show="f === 'all' || f === '{{ $asFilterOf($ag['statusLabel']) }}'">
                    <span class="csp-ic {{ $isExam ? 'o' : '' }}" style="width:44px;height:44px"><x-lucide :name="$isExam ? 'target' : 'clipboard-list'" /></span>
                    <div class="asg-main">
                        <div class="asg-top"><b>{{ $ag['title'] }}</b><span class="csp-pill {{ $asTone[$ag['statusLabel']] ?? 'p-gray' }}">{{ $ag['statusLabel'] }}</span>@if ($wait > 0)<span class="csp-pill p-ex">{{ $wait }} bài chờ chấm</span>@endif</div>
                        <div class="asg-meta"><x-lucide name="clock-3" />Mở: {{ $ag['opensAtLabel'] }} · Đóng: {{ $ag['closesAtLabel'] }}</div>
                        @if ($ag['instructions'])<p class="asg-ins">{{ $ag['instructions'] }}</p>@endif
                        <div class="asg-prog"><div><i style="width: {{ $pct }}%; background: {{ $pct >= 100 ? '#16A34A' : '#2563EB' }}"></i></div><b>{{ $done }}/{{ $asRoster }} đã nộp</b></div>
                        <div class="asg-acts">
                            <a href="{{ route('teacher.results.index', ['class' => $classRoom->id, 'assessment' => $ag['id']]) }}" class="csp-btn sm"><x-lucide name="eye" />Xem bài nộp</a>
                            @if (($space['can']['materials'] ?? false) && $done < $asRoster && $ag['statusLabel'] === 'Đang mở')
                                <form method="POST" action="{{ route('teacher.classes.assignments.remind', ['class' => $classRoom->id, 'assignment' => $ag['id']]) }}">
                                    @csrf
                                    <button type="submit" class="csp-btn sm"><x-lucide name="bell" />Nhắc {{ $asRoster - $done }} em chưa nộp</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    @endif

    @if ($asCan)
        <template x-teleport="body">
            <div class="tam-ov" x-show="open" x-transition.opacity x-cloak @click.self="open = false">
                <form class="tam-dlg" method="POST" action="{{ route('teacher.classes.assign', $classRoom->id) }}" x-show="open" x-transition.scale.95 role="dialog" aria-modal="true">
                    @csrf
                    {{-- Ô ngày giờ gộp → tách đúng các trường cũ opens_/closes_ day/month/year/hour/minute mà máy chủ đang nhận. --}}
                    @foreach (['opens', 'closes'] as $px)
                        <input type="hidden" name="{{ $px }}_day" :value="{{ $px }} ? {{ $px }}.slice(8, 10) : ''">
                        <input type="hidden" name="{{ $px }}_month" :value="{{ $px }} ? {{ $px }}.slice(5, 7) : ''">
                        <input type="hidden" name="{{ $px }}_year" :value="{{ $px }} ? {{ $px }}.slice(0, 4) : ''">
                        <input type="hidden" name="{{ $px }}_hour" :value="{{ $px }} ? {{ $px }}.slice(11, 13) : ''">
                        <input type="hidden" name="{{ $px }}_minute" :value="{{ $px }} ? {{ $px }}.slice(14, 16) : ''">
                    @endforeach
                    <header class="tam-head" style="background:linear-gradient(120deg,#FFF1E6,#EEF4FF)">
                        <span class="tam-hic" style="background:linear-gradient(135deg,#F97316,#EA580C)"><x-lucide name="clipboard-list" /></span>
                        <div class="tam-htx"><h3>Giao bài / đề cho lớp</h3><p>{{ $className }}</p></div>
                        <button type="button" class="tam-x" @click="open = false" aria-label="Đóng"><x-lucide name="x" /></button>
                    </header>
                    <div class="tam-body" style="display:grid;gap:14px;padding-top:16px">
                        @if (empty($assignableAssessments))
                            <div class="csp-note"><x-lucide name="info" /><span>Bạn chưa có đề nào. Tạo đề ở <a href="{{ route('teacher.questions.index', ['tab' => 'assessments']) }}" style="font-weight:800;color:#1D4ED8">Kho bài tập / câu hỏi và đề</a> rồi quay lại đây.</span></div>
                        @else
                            <div>
                                <label class="csp-lb" for="asg-ass">Chọn bài / đề</label>
                                <select id="asg-ass" name="assessment_id" required class="csp-inp" style="width:100%">
                                    <option value="">— Chọn trong kho của bạn —</option>
                                    @foreach ($assignableAssessments as $ass)
                                        <option value="{{ $ass['id'] }}" @selected((string) old('assessment_id') === (string) $ass['id'])>{{ $ass['title'] }}{{ $ass['status'] === 'Nháp' ? ' (Nháp — tự phát hành khi giao)' : '' }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="asg-two">
                                <div><label class="csp-lb">Mở lúc <em class="asg-opt">(không bắt buộc)</em></label><input type="datetime-local" x-model="opens" class="csp-inp" style="width:100%"></div>
                                <div><label class="csp-lb">Đóng lúc / hạn nộp <em class="asg-opt">(không bắt buộc)</em></label><input type="datetime-local" x-model="closes" class="csp-inp" style="width:100%"></div>
                            </div>
                            <div>
                                <label class="csp-lb" for="asg-shift">Chia ca thi <em class="asg-opt">(không bắt buộc — cần đủ Mở lúc + Đóng lúc)</em></label>
                                <input id="asg-shift" name="shift_count" type="number" min="1" max="20" value="{{ old('shift_count') }}" class="csp-inp" style="width:160px" placeholder="VD: 3">
                            </div>
                            <div>
                                <label class="csp-lb" for="asg-ins">Hướng dẫn làm bài <em class="asg-opt">(không bắt buộc)</em></label>
                                <textarea id="asg-ins" name="instructions" rows="3" class="csp-inp" style="width:100%" placeholder="Ví dụ: Làm trong 45 phút, nộp trước 21:00.">{{ old('instructions') }}</textarea>
                            </div>
                        @endif
                    </div>
                    <footer class="tam-foot">
                        <small><x-lucide name="info" />Đề tự phát hành nếu mọi câu đã đủ điều kiện.</small>
                        <div class="tam-acts">
                            <button type="button" class="tam-btn ghost" @click="open = false">Huỷ</button>
                            @if (! empty($assignableAssessments))<button type="submit" class="tam-btn pri" style="background:linear-gradient(135deg,#F97316,#EA580C)"><x-lucide name="check" />Giao cho lớp</button>@endif
                        </div>
                    </footer>
                </form>
            </div>
        </template>
    @endif
</div>
<style>
    .asg-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
    @media (max-width: 1000px) { .asg-grid { grid-template-columns: minmax(0, 1fr); } }
    .asg-card { display: flex; align-items: flex-start; gap: 14px; padding: 16px; border: 1px solid #E5EDF5; border-radius: 16px; background: #fff; }
    .asg-main { flex: 1; min-width: 0; }
    .asg-top { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 8px; }
    .asg-top b { font-size: 14px; color: #0F2F57; }
    .asg-meta { display: flex; align-items: center; gap: 5px; margin-top: 4px; font-size: 12px; color: #64748B; }
    .asg-meta svg { width: 13px; height: 13px; }
    .asg-ins { margin: 6px 0 0; font-size: 12px; color: #475569; }
    .asg-prog { display: flex; align-items: center; gap: 10px; margin-top: 10px; }
    .asg-prog div { flex: 1; height: 8px; border-radius: 9px; background: #EEF2F7; overflow: hidden; }
    .asg-prog i { display: block; height: 100%; border-radius: 9px; }
    .asg-prog b { font-size: 12px; white-space: nowrap; color: #334155; }
    .asg-acts { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
    .asg-acts form { margin: 0; }
    .asg-two { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .asg-opt { font-style: normal; font-weight: 600; color: #94A3B8; }
    @media (max-width: 560px) { .asg-two { grid-template-columns: 1fr; } }
</style>

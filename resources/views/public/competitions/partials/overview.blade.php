{{-- SỬA 24/10 — tab "Thông tin kỳ thi" (source: ContestAssessmentOverview + khối thể lệ). --}}
@php
    $canPick = ! $survey && ($round['assignmentMode'] === 'choice') && $assignedForm === null && $access['allowed'] && count($forms) > 1;
@endphp
@if (! $survey)
    <div class="hub-content-heading"><h3>Danh sách đề thi</h3><span class="hub-score-scale">{{ count($forms) > 1 ? count($forms).' mã đề tương đương' : '1 đề thi' }}</span></div>
    <p class="hub-assessment-note">
        @if ($assignedForm !== null)
            Bạn đã nhận đề. Đề được giữ nguyên khi tiếp tục bài đang làm.
        @elseif (count($forms) <= 1)
            Mỗi học sinh làm đề chính thức của kỳ thi và nộp một lần.
        @elseif ($round['assignmentMode'] === 'random')
            Hệ thống giao ngẫu nhiên một đề khi bắt đầu. Mỗi học sinh làm một đề và nộp một lần.
        @else
            Chọn một đề để làm. Lựa chọn được chốt khi xác nhận bắt đầu kỳ thi.
        @endif
    </p>
    <div class="hub-exam-forms" role="group" aria-label="Các mã đề tương đương">
        @foreach ($forms as $f)
            @php $isMine = $assignedForm === $f['no']; @endphp
            <label class="hub-exam-form"
                   @if ($assignedForm !== null) data-selected="{{ $isMine ? 'true' : 'false' }}" @elseif ($round['assignmentMode'] === 'choice' && count($forms) > 1) :data-selected="selectedForm === {{ $f['no'] }} ? 'true' : 'false'" @endif>
                @if ($canPick)
                    <input type="radio" name="contest-form" value="{{ $f['no'] }}" x-model.number="selectedForm" aria-label="Chọn {{ $f['label'] }}">
                @else
                    <x-lucide name="file-text" />
                @endif
                <span><b>{{ $f['label'] }}</b><small>{{ $f['questionCount'] }} câu · {{ $f['points'] }} điểm · {{ $f['duration'] }}</small></span>
                <em>{{ $isMine ? 'Đề của bạn' : (count($forms) > 1 ? 'Hoán vị' : 'Chính thức') }}</em>
            </label>
        @endforeach
    </div>
    @if (! $manager)
        <p class="hub-assessment-note hub-locked-note">{{ $resume ? 'Mở bài đang làm để xem nội dung đề đã nhận.' : 'Nội dung câu hỏi chỉ mở sau khi được duyệt, đến giờ thi và xác nhận vào bài.' }}</p>
    @endif
    @if (count($forms) > 1)
        <p class="contest-table-note">Các mã đề dùng cùng câu hỏi, cùng thang điểm và thời gian; chỉ đổi thứ tự câu. Mỗi kỳ thi vẫn chỉ có một lượt nộp.</p>
    @endif
    @if ($manager && count($questions))
        <div class="hub-content-heading" style="margin-top:16px"><h3>Câu hỏi của đề (ban tổ chức xem)</h3><span class="hub-score-scale">{{ count($questions) }} câu · {{ $round['totalPoints'] }} điểm</span></div>
        <div class="hub-question-grid">
            @foreach ($questions as $i => $q)
                <div class="hub-question"><span>{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span><div><b>{{ $q['title'] }}</b><small>{{ $q['kind'] }}</small></div><small class="hub-question-points">{{ rtrim(rtrim(number_format($q['points'], 2, '.', ''), '0'), '.') }} điểm</small></div>
            @endforeach
        </div>
    @endif
@elseif (count($paperList ?? []) > 1)
    {{-- SỬA 24/10 (khách: "thiết lập 2 đề thì Thông tin kỳ thi hiện 2 đề; học sinh chọn đề bất kỳ để làm; chọn đề nào thì
         hiện câu hỏi của đề đó") — bấm thẻ đề để chọn (ai cũng bấm được để xem); bên dưới chỉ hiện câu hỏi của đề đang chọn.
         Nội dung câu hỏi: ban tổ chức luôn thấy, học sinh thấy sau khi đã nộp ít nhất 1 lượt (giống kỳ 1 đề). --}}
    @php $canPickPaper = ($action['kind'] ?? '') === 'enter'; @endphp
    <div class="hub-content-heading"><h3>Danh sách đề</h3><span class="hub-score-scale">{{ count($paperList) }} đề · mỗi đề 100 điểm</span></div>
    <p class="hub-assessment-note">
        @if ($manager)
            Kỳ gồm nhiều đề sinh từ ma trận. Bấm vào đề để xem câu hỏi. Học sinh tự chọn đề khi bắt đầu; mỗi lượt có thể chọn đề khác.
        @elseif ($surveyDraft ?? false)
            Bạn có một lượt đang làm dở. Bắt đầu lại sẽ làm từ đầu — chọn đề bất kỳ rồi bấm làm bài.
        @else
            Chọn một đề để làm. Mỗi lượt làm lại có thể chọn đề khác; bảng xếp hạng lấy điểm cao nhất của bạn.
        @endif
    </p>
    <div class="hub-exam-forms" role="radiogroup" aria-label="Các đề của kỳ">
        @foreach ($paperList as $p)
            <label class="hub-exam-form" :data-selected="selectedPaper === {{ $p['id'] }} ? 'true' : 'false'">
                <input type="radio" name="contest-paper" value="{{ $p['id'] }}" x-model.number="selectedPaper" aria-label="Chọn {{ $p['label'] }}">
                <span><b>{{ $p['label'] }}</b><small>{{ $p['questionCount'] }} câu · 100 điểm · {{ $p['duration'] ? $p['duration'].' phút' : 'Không giới hạn' }}</small></span>
                <em>{{ $myPaperId === $p['id'] && ! $manager ? 'Đã làm lần trước' : 'Đề '.$loop->iteration }}</em>
            </label>
        @endforeach
    </div>
    @if ($canPickPaper)
        <div class="hub-desktop-action" style="margin-top:4px"><button type="button" class="contest-btn primary hub-primary-action" @click="openEntry()"><x-lucide name="key-round" /><span x-text="'Làm ' + (paperLabels[selectedPaper] || 'đề đã chọn')"></span><x-lucide name="arrow-right" /></button></div>
    @endif
    @foreach ($paperList as $p)
        <div x-show="selectedPaper === {{ $p['id'] }}" @if (! $loop->first) x-cloak @endif>
            <div class="hub-content-heading" style="margin-top:16px"><h3>Câu hỏi {{ $p['label'] }}</h3><span class="hub-score-scale">{{ $p['questionCount'] }} câu · 100 điểm</span></div>
            @if (count($p['questions']))
                <div class="hub-question-grid">
                    @foreach ($p['questions'] as $i => $q)
                        <div class="hub-question"><span>{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span><div><b>{{ $q['title'] }}</b><small>{{ $q['kind'] }}</small></div><small class="hub-question-points">{{ rtrim(rtrim(number_format($q['points'], 2, '.', ''), '0'), '.') }} điểm</small>
                            @if ($canPickPaper)
                                <button type="button" class="hub-question-start" aria-label="Làm {{ $p['label'] }}" title="Làm đề này" @click="openEntry()"><x-lucide name="arrow-up-right" /></button>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <div class="hub-exam-source"><x-lucide name="file-text" /><div><b>{{ $p['questionCount'] }} câu hỏi · 100 điểm</b><p>Nội dung câu hỏi mở sau khi bạn nhập mật khẩu và làm đề này.</p></div></div>
            @endif
        </div>
    @endforeach
@else
    <div class="hub-content-heading"><h3>Câu hỏi khảo sát</h3><span class="hub-score-scale">{{ count($questions) }} câu · {{ $round['totalPoints'] }} điểm</span></div>
    <p class="hub-assessment-note">
        @if (! count($questions))
            Giáo viên đang chuẩn bị đề. Bạn có thể bắt đầu sau khi đề được chốt.
        @elseif ($questionsVisible)
            Cùng bộ câu hỏi cho cả kỳ, kể cả khi làm lại. Mỗi lượt làm được tổng kết riêng.
        @else
            Cả kỳ khảo sát dùng một bộ câu hỏi chung. Nội dung chỉ mở sau khi bạn nhập mật khẩu và bắt đầu khảo sát.
        @endif
    </p>
    @if ($questionsVisible && count($questions))
        <div class="hub-question-grid">
            @foreach ($questions as $i => $q)
                <div class="hub-question"><span>{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span><div><b>{{ $q['title'] }}</b><small>{{ $q['kind'] }}</small></div><small class="hub-question-points">{{ rtrim(rtrim(number_format($q['points'], 2, '.', ''), '0'), '.') }} điểm</small>
                    @if ($action['kind'] === 'enter')
                        <button type="button" class="hub-question-start" aria-label="Làm từ câu {{ $i + 1 }}" title="Mở khảo sát" @click="openEntry()"><x-lucide name="arrow-up-right" /></button>
                    @endif
                </div>
            @endforeach
        </div>
    @else
        <div class="hub-exam-source"><x-lucide name="file-text" /><div><b>{{ count($questions) }} câu hỏi · {{ $round['totalPoints'] }} điểm</b><p>Làm từng câu, có thể khảo sát nhiều lần và theo dõi tiến bộ qua từng lượt.</p></div></div>
    @endif
@endif

<details class="hub-rules">
    <summary>Thông tin cuộc thi &amp; thể lệ<x-lucide name="chevron-down" /></summary>
    <p>{{ $contest['description'] }}</p>
    @if ($contest['rules'] !== '' && $contest['rules'] !== $contest['description'])
        <p style="white-space:pre-line">{{ $contest['rules'] }}</p>
    @endif
    <ul>
        <li>{{ $survey ? 'Không giới hạn số lượt trong thời gian kỳ khảo sát mở. Cần nhập mật khẩu trước khi làm bài.' : 'Mỗi học sinh nộp một lần cho mỗi kỳ thi. Giáo viên hoặc admin tổ chức duyệt đăng ký cho toàn cuộc thi.' }}</li>
        <li>{{ $rankingRule }}</li>
        @if ($contest['publishLabel'])
            <li>Bảng xếp hạng công bố lúc {{ $contest['publishLabel'] }}.</li>
        @endif
        <li>Thời gian mở và đóng kỳ thi theo múi giờ Việt Nam (GMT+7).</li>
    </ul>
    <p class="hub-rule-organizer"><x-lucide name="shield-check" />Ban tổ chức: {{ $contest['organizer'] }}</p>
</details>

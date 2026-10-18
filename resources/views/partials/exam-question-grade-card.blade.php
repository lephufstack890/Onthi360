{{-- SỬA 14/10 (khách: "chấm xong thì hiển thị thêm điểm như hình") — thẻ "Kết quả chấm" của nút Chấm câu, chép bố cục
     RunResultPanel trong education-main/src/components/AssessmentModal.jsx:
       "Kết quả chấm: Đúng. 3/3 test đúng."  ·  "12.5/12.5 điểm"  ·  danh sách Test 1..n Đúng/Sai.
     Chỉ hiện Đúng/Sai + lý do của từng test. SỬA 17/10 — test SAI có thêm 2 link Tải input / Tải output (tải từ máy chủ,
     route student.assessment.take.testDownload — không nhúng dữ liệu test vào trang).
     Tham số: $qid (id câu), $isCode (câu lập trình). Biến Alpine nằm ở partials/exam-workspace-script. --}}
@once
    <style>
        .oi-grade-card { border: 1px solid #DDEAF0; border-radius: 12px; background: #fff; padding: 12px 14px; font-size: 12px; color: #45657D; }
        .oi-grade-head { margin: 0; line-height: 1.55; }
        .oi-grade-head strong.is-ok { color: #047857; }
        .oi-grade-head strong.is-part { color: #B45309; }
        .oi-grade-head strong.is-bad { color: #BE123C; }
        .oi-grade-score { margin: 8px 0 0; font-size: 15px; font-weight: 800; color: #126F91; }
        .oi-grade-tests { margin: 10px 0 0; padding: 0; list-style: none; }
        .oi-grade-tests li { display: flex; justify-content: space-between; gap: 10px; padding: 7px 0; border-top: 1px solid #DDEAF0; }
        .oi-grade-tests li:last-child { border-bottom: 1px solid #DDEAF0; }
        .oi-grade-tests b { font-weight: 800; text-align: right; }
        .oi-grade-tests b.is-ok { color: #047857; }
        .oi-grade-tests b.is-bad { color: #BE123C; }
        .oi-grade-note { margin: 8px 0 0; font-size: 10.5px; color: #7A92A3; }
        /* SỬA 17/10 — nút Tải input / Tải output của test sai (giống bài tập chuyên đề). */
        .oi-grade-tests .oi-grade-right { display: inline-flex; align-items: center; justify-content: flex-end; flex-wrap: wrap; gap: 4px 10px; }
        .oi-grade-tests li.has-reason { flex-wrap: wrap; }
        .oi-grade-reason { flex-basis: 100%; margin: 2px 0 0; font-size: 11px; line-height: 1.5; color: #9F1239; word-break: break-word; }
        .oi-grade-code { display: inline-block; margin-right: 6px; padding: 0 5px; border-radius: 4px; background: #FFE4E6; font-size: 10px; font-weight: 800; color: #BE123C; }
        html.theme-dark .assessment-modal .oi-grade-reason { color: #FBCFE8; }
        html.theme-dark .assessment-modal .oi-grade-code { background: #4C1D2B; color: #FDA4AF; }
        .oi-grade-dl { font-weight: 700; color: #126F91; text-decoration: underline; text-underline-offset: 2px; white-space: nowrap; }
        .oi-grade-dl:hover { color: #0F607E; }
        html.theme-dark .assessment-modal .oi-grade-dl { color: #8FD3E8; }
        html.theme-dark .assessment-modal .oi-grade-dl:hover { color: #B9E6F3; }
        .oi-grade-wait { display: flex; align-items: center; gap: 8px; margin: 0; color: #B45309; font-weight: 700; }
        .oi-grade-spin { width: 14px; height: 14px; border-radius: 999px; border: 2px solid #FDE7C2; border-top-color: #B45309; animation: oi-grade-spin .8s linear infinite; }
        @keyframes oi-grade-spin { to { transform: rotate(360deg); } }
        .oi-grade-empty { margin: 0; font-weight: 700; }
        .oi-grade-stale { margin: 0; font-weight: 700; color: #B45309; }
        .oi-grade-error { margin: 0; font-weight: 700; color: #BE123C; line-height: 1.5; }
        .oi-grade-hint { margin: 8px 0 0; font-size: 11px; line-height: 1.6; color: #7A92A3; }
        .oi-grade-hint b { color: #126F91; }
        /* Nền tối — cùng tông với khung làm bài tối của bản mẫu. */
        html.theme-dark .assessment-modal .oi-grade-card { background: #22384D; border-color: #39556B; color: #C9D8E1; }
        html.theme-dark .assessment-modal .oi-grade-head strong.is-ok,
        html.theme-dark .assessment-modal .oi-grade-tests b.is-ok { color: #86E0B5; }
        html.theme-dark .assessment-modal .oi-grade-head strong.is-part { color: #F7C873; }
        html.theme-dark .assessment-modal .oi-grade-head strong.is-bad,
        html.theme-dark .assessment-modal .oi-grade-tests b.is-bad,
        html.theme-dark .assessment-modal .oi-grade-error { color: #FDA4AF; }
        html.theme-dark .assessment-modal .oi-grade-score { color: #8FD3E8; }
        html.theme-dark .assessment-modal .oi-grade-tests li { border-color: #E6EEF3; }
        html.theme-dark .assessment-modal .oi-grade-note,
        html.theme-dark .assessment-modal .oi-grade-hint { color: #9FB4C3; }
        html.theme-dark .assessment-modal .oi-grade-hint b { color: #8FD3E8; }
    </style>
@endonce

<div class="oi-grade-card min-h-0 flex-1 overflow-y-auto" aria-live="polite">
    <template x-if="gradingId === {{ $qid }}">
        <p role="status" class="oi-grade-wait"><span class="oi-grade-spin" aria-hidden="true"></span>Đang chấm câu trả lời...</p>
    </template>

    <template x-if="gradingId !== {{ $qid }} && doneGradeOf({{ $qid }})">
        <div>
            <p role="status" aria-atomic="true" class="oi-grade-head">
                <strong :class="doneGradeOf({{ $qid }}).status === 'Đúng' ? 'is-ok' : (doneGradeOf({{ $qid }}).status === 'Đúng một phần' ? 'is-part' : 'is-bad')">Kết quả chấm: <span x-text="doneGradeOf({{ $qid }}).status"></span>.</strong>
                <span x-text="doneGradeOf({{ $qid }}).isCode && doneGradeOf({{ $qid }}).total > 0
                    ? (doneGradeOf({{ $qid }}).passed + '/' + doneGradeOf({{ $qid }}).total + ' test đúng.')
                    : (fmtScore(doneGradeOf({{ $qid }}).score) + '/' + fmtScore(doneGradeOf({{ $qid }}).maxScore) + ' điểm.')"></span>
            </p>
            @if ($isCode)
                <p class="oi-grade-score" x-text="fmtScore(doneGradeOf({{ $qid }}).score) + '/' + fmtScore(doneGradeOf({{ $qid }}).maxScore) + ' điểm'"></p>
                <ul class="oi-grade-tests" x-show="(doneGradeOf({{ $qid }}).tests || []).length">
                    <template x-for="(t, ti) in (doneGradeOf({{ $qid }}).tests || [])" :key="ti">
                        <li :class="t.reason ? 'has-reason' : ''">
                            <span x-text="t.name"></span>
                            <span class="oi-grade-right">
                                <b :class="t.ok ? 'is-ok' : 'is-bad'" x-text="t.label"></b>
                                {{-- SỬA 17/10 (khách: "chấm xong cho tải test sai về, Tải input và Tải output như bài tập chuyên đề"). --}}
                                <template x-if="!t.ok">
                                    <span class="oi-grade-right">
                                        <a class="oi-grade-dl" :href="testFileUrl({{ $qid }}, t.index || (ti + 1), 'in')" download :title="'Tải dữ liệu vào của ' + t.name + ' (.in)'">Tải input</a>
                                        <a class="oi-grade-dl" :href="testFileUrl({{ $qid }}, t.index || (ti + 1), 'out')" download :title="'Tải kết quả mong đợi của ' + t.name + ' (.out)'">Tải output</a>
                                    </span>
                                </template>
                            </span>
                            {{-- SỬA 18/10 (khách: "báo mã lỗi từng test sai để người ta biết đường check"). --}}
                            <p class="oi-grade-reason" x-show="!t.ok && t.reason"><span class="oi-grade-code" x-show="t.code" x-text="t.code"></span><span x-text="t.reason"></span></p>
                        </li>
                    </template>
                </ul>
            @endif
            <p class="oi-grade-note">Điểm chính thức chốt khi Nộp &amp; tổng kết (trừ điểm theo nhật ký, nếu có, tính lúc đó).</p>
        </div>
    </template>

    <template x-if="gradingId !== {{ $qid }} && !doneGradeOf({{ $qid }})">
        <div>
            <p class="oi-grade-error" x-show="gradeError[{{ $qid }}]" x-text="gradeError[{{ $qid }}]"></p>
            <p class="oi-grade-stale" x-show="!gradeError[{{ $qid }}] && staleGrade({{ $qid }})">Đã sửa câu trả lời · cần chấm lại.</p>
            <p class="oi-grade-empty" x-show="!gradeError[{{ $qid }}] && !staleGrade({{ $qid }})">Kết quả và điểm sẽ hiển thị tại đây sau khi chấm.</p>
            @if ($isCode)
                <p class="oi-grade-hint">Bài làm được <b>tự động lưu</b> ngay khi bạn gõ. Bấm <b>Chấm câu</b> ở thanh trên để chấm riêng câu này, hoặc <b>Nộp &amp; tổng kết</b> khi làm xong cả đề — câu đã chấm không phải chấm lại.</p>
            @endif
        </div>
    </template>
</div>

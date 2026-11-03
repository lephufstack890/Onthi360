{{-- SỬA 3/11 (khách: "Bài phổ thông: nộp cả bài mới chấm, hiện đáp án và lời giải sau khi nộp") — khối Đáp án & lời giải ở trang
     kết quả, CHỈ hiện với "Bài phổ thông" (App\Support\GeneralSetReview). Đề khác không thấy khối này. CSS tiền tố gsr-. --}}
@php
    $gsrRows = \App\Support\GeneralSetReview::rows($attemptModel);
    $gsrAssessment = $attemptModel->assessment;
    $gsrSolution = $gsrAssessment && $gsrAssessment->solution_pdf_path
        ? route('general-sets.file', ['assessment' => $gsrAssessment->id, 'which' => 'solution']) : null;
@endphp
@if ($gsrRows !== [])
    <style>
        .gsr { margin-top: 12px; overflow: hidden; border: 1px solid #E0F2FE; border-radius: 16px; background: #fff; box-shadow: 0 3px 12px rgba(28, 91, 121, .05); }
        .gsr-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; padding: 12px 16px; border-bottom: 1px solid #EEF4F8; }
        .gsr-head h2 { margin: 0; font-size: 14px; font-weight: 800; color: #123B68; }
        .gsr-head p { margin: 2px 0 0; font-size: 12px; color: #607A90; }
        .gsr-pdf { display: inline-flex; align-items: center; gap: 6px; padding: 8px 12px; border-radius: 10px; background: #EEF2FF; color: #4338CA; font-size: 12px; font-weight: 800; text-decoration: none; }
        .gsr-pdf svg { width: 15px; height: 15px; }
        .gsr-row { display: grid; grid-template-columns: 34px minmax(0, 1fr); gap: 10px; padding: 12px 16px; }
        .gsr-row + .gsr-row { border-top: 1px solid #F1F5F9; }
        .gsr-no { display: grid; place-items: center; width: 30px; height: 30px; border-radius: 9px; font-size: 12px; font-weight: 900; }
        .gsr-no.ok { background: #E7F6EE; color: #1F7A55; } .gsr-no.bad { background: #FDECEC; color: #C0392B; } .gsr-no.wait { background: #EEF4FA; color: #45657D; }
        .gsr-t { font-size: 13px; font-weight: 700; color: #123B68; }
        .gsr-t small { margin-left: 6px; font-size: 11px; font-weight: 700; color: #94A3B8; }
        .gsr-ans { display: flex; flex-wrap: wrap; gap: 6px 14px; margin-top: 5px; font-size: 12.5px; color: #45657D; }
        .gsr-ans b.r { color: #1F7A55; } .gsr-ans b.m-bad { color: #C0392B; } .gsr-ans b.m-ok { color: #1F7A55; }
        .gsr-exp { margin-top: 6px; padding: 8px 10px; border-radius: 10px; background: #FFFBEB; border: 1px solid #FDE68A; font-size: 12.5px; line-height: 1.55; color: #78350F; white-space: pre-line; }
    </style>
    <section class="gsr">
        <div class="gsr-head">
            <div>
                <h2>Đáp án &amp; lời giải</h2>
                <p>Đối chiếu từng câu để biết mình sai ở đâu — làm lại bao nhiêu lần cũng được.</p>
            </div>
            @if ($gsrSolution)
                <a href="{{ $gsrSolution }}" target="_blank" rel="noopener" class="gsr-pdf"><x-lucide name="file-text" />Xem lời giải chi tiết (PDF)</a>
            @endif
        </div>
        @foreach ($gsrRows as $r)
            <div class="gsr-row">
                <span class="gsr-no {{ $r['ok'] === true ? 'ok' : ($r['ok'] === false ? 'bad' : 'wait') }}">{{ $r['no'] }}</span>
                <div class="min-w-0">
                    <div class="gsr-t">{{ $r['title'] }}<small>{{ $r['type'] }}</small></div>
                    <div class="gsr-ans">
                        <span>Đáp án đúng: <b class="r">{{ $r['correct'] }}</b></span>
                        <span>Bạn chọn: <b class="{{ $r['ok'] === true ? 'm-ok' : ($r['ok'] === false ? 'm-bad' : '') }}">{{ $r['mine'] }}</b></span>
                    </div>
                    @if ($r['explanation'])
                        <div class="gsr-exp"><b>Lời giải:</b> {{ $r['explanation'] }}</div>
                    @endif
                </div>
            </div>
        @endforeach
    </section>
@endif

{{--
    SỬA 3/11 (khách: "bài phổ thông vẫn thuộc Bài chung nhưng 1 bài có nhiều câu; lập trình giữ nguyên") — khối "Bài phổ thông"
    ở tab Bài chung của Kho bài tập: MỖI BÀI 1 DÒNG (các câu con không còn hiện thành bài lẻ). Dữ liệu:
    App\Services\GeneralSetBankService::rows(). Tham số: $gsbRows, $gsbMode = 'admin' | 'teacher'. CSS tiền tố gsb-.
--}}
@php $gsbRows = $gsbRows ?? []; $gsbMode = $gsbMode ?? 'admin'; $gsbFlash = session('generalSetFlash'); @endphp
@if ($gsbFlash)
    @include('partials.toast-flash', ['type' => $gsbFlash[0] === 'error' ? 'error' : 'success', 'message' => $gsbFlash[1]])
@endif
@if ($gsbRows !== [])
<style>
    .gsb { margin-top: 16px; border: 1px solid #DCE5FB; border-radius: 18px; background: #fff; overflow: hidden; }
    .gsb-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; padding: 12px 16px; background: linear-gradient(90deg, #EEF2FF, #F8FAFF); border-bottom: 1px solid #E3E9FB; }
    .gsb-head h3 { display: flex; align-items: center; gap: 8px; margin: 0; font-size: 14px; font-weight: 800; color: #312E81; }
    .gsb-head h3 svg { width: 17px; height: 17px; }
    .gsb-head h3 span { padding: 1px 8px; border-radius: 99px; background: #4F46E5; color: #fff; font-size: 11px; }
    .gsb-head p { margin: 0; font-size: 12px; color: #5B5F86; }
    .gsb-row { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 10px 16px; align-items: center; padding: 12px 16px; }
    .gsb-row + .gsb-row { border-top: 1px solid #EEF1F8; }
    .gsb-t { display: flex; gap: 10px; min-width: 0; }
    .gsb-ic { display: grid; place-items: center; width: 36px; height: 36px; flex: none; border-radius: 11px; background: #EEF2FF; color: #4F46E5; }
    .gsb-ic svg { width: 18px; height: 18px; }
    .gsb-t a, .gsb-t b { display: block; font-size: 14px; font-weight: 800; color: #1E3A8A; text-decoration: none; }
    .gsb-t a:hover { text-decoration: underline; }
    .gsb-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 8px; margin-top: 4px; font-size: 11.5px; color: #64748B; }
    .gsb-chip { padding: 1px 8px; border-radius: 99px; border: 1px solid #C7D2FE; background: #EEF2FF; font-weight: 700; color: #4338CA; }
    .gsb-st { padding: 1px 8px; border-radius: 99px; font-weight: 800; }
    .gsb-st.success { background: #E7F6EE; color: #1F7A55; } .gsb-st.neutral { background: #F1F5F9; color: #475569; } .gsb-st.warning { background: #FEF3C7; color: #92400E; }
    .gsb-code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
    .gsb-acts { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 6px; }
    .gsb-acts form { margin: 0; }
    .gsb-btn { display: inline-flex; align-items: center; gap: 5px; height: 32px; padding: 0 12px; border-radius: 9px; border: 1px solid #D6E0F0; background: #fff; font-size: 12px; font-weight: 800; color: #1D4ED8; cursor: pointer; text-decoration: none; }
    .gsb-btn svg { width: 14px; height: 14px; }
    .gsb-btn.is-green { border-color: #BBE5D2; color: #1F7A55; } .gsb-btn.is-green:hover { background: #EAF7F0; }
    .gsb-btn.is-red { border-color: #F5C2C2; color: #B91C1C; } .gsb-btn.is-red:hover { background: #FEF2F2; }
    .gsb-btn.is-gray { color: #475569; }
    .gsb-btn:hover { background: #F1F5FF; }
    @media (max-width: 760px) { .gsb-row { grid-template-columns: minmax(0, 1fr); } .gsb-acts { justify-content: flex-start; } }
</style>
<section class="gsb" aria-label="Bài phổ thông">
    <div class="gsb-head">
        <h3><x-lucide name="list-checks" />Bài phổ thông <span>{{ count($gsbRows) }}</span></h3>
        <p>Mỗi bài gồm nhiều câu (trắc nghiệm, đúng/sai, điền từ…) — bấm tên bài để xem các câu bên trong.</p>
    </div>
    @foreach ($gsbRows as $g)
        <div class="gsb-row">
            <div class="gsb-t">
                <span class="gsb-ic"><x-lucide name="list-checks" /></span>
                <div class="min-w-0">
                    @if ($gsbMode === 'admin')
                        <a href="{{ route('admin.content.show', ['content' => $g['id'], 'kind' => 'assessment']) }}#assessment-items">{{ $g['title'] }}</a>
                    @elseif ($g['hasStatement'])
                        <a href="{{ route('general-sets.file', ['assessment' => $g['id'], 'which' => 'statement']) }}" target="_blank" rel="noopener">{{ $g['title'] }}</a>
                    @else
                        <b>{{ $g['title'] }}</b>
                    @endif
                    <div class="gsb-meta">
                        <span class="gsb-chip">Phổ thông · {{ $g['count'] }} câu · {{ $g['totalPoints'] }} điểm</span>
                        @if ($g['typeSummary'])<span>{{ $g['typeSummary'] }}</span>@endif
                        <span class="gsb-st {{ $g['tone'] }}">{{ $g['status'] }}</span>
                        @if ($g['draftQuestions'] > 0 && $gsbMode === 'admin')<span>{{ $g['draftQuestions'] }} câu nháp</span>@endif
                    </div>
                    <div class="gsb-meta">
                        @if ($g['code'])<span class="gsb-code">Mã: {{ $g['code'] }}</span>@endif
                        @if ($gsbMode === 'admin')<span>{{ $g['owner'] }}</span>@endif
                        @if ($g['createdAt'])<span>Tạo {{ $g['createdAt'] }}</span>@endif
                    </div>
                </div>
            </div>
            <div class="gsb-acts">
                @if ($gsbMode === 'admin')
                    <a href="{{ route('admin.content.show', ['content' => $g['id'], 'kind' => 'assessment']) }}#assessment-items" class="gsb-btn"><x-lucide name="pencil" />Sửa / xem câu</a>
                    @if ($g['published'])
                        <form method="POST" action="{{ route('admin.content.generalSets.unpublish', $g['id']) }}">@csrf
                            <button type="submit" class="gsb-btn is-gray"><x-lucide name="eye-off" />Ngừng phát hành</button></form>
                    @else
                        <form method="POST" action="{{ route('admin.content.generalSets.publish', $g['id']) }}">@csrf
                            <button type="submit" class="gsb-btn is-green"><x-lucide name="check-circle-2" />Phát hành</button></form>
                    @endif
                    <form method="POST" action="{{ route('admin.content.generalSets.destroy', $g['id']) }}"
                          onsubmit="return confirm('Xoá bài &quot;{{ addslashes($g['title']) }}&quot; cùng {{ $g['count'] }} câu bên trong?');">@csrf @method('DELETE')
                        <button type="submit" class="gsb-btn is-red"><x-lucide name="trash-2" />Xoá</button></form>
                @else
                    @if ($g['hasStatement'])
                        <a href="{{ route('general-sets.file', ['assessment' => $g['id'], 'which' => 'statement']) }}" target="_blank" rel="noopener" class="gsb-btn"><x-lucide name="file-text" />Xem đề</a>
                    @endif
                    <a href="{{ route('teacher.assessments.index') }}" class="gsb-btn is-green"><x-lucide name="send" />Giao cho lớp</a>
                    <form method="POST" action="{{ route('teacher.questions.generalSets.destroy', $g['id']) }}"
                          onsubmit="return confirm('Xoá bài &quot;{{ addslashes($g['title']) }}&quot; cùng {{ $g['count'] }} câu bên trong?');">@csrf @method('DELETE')
                        <button type="submit" class="gsb-btn is-red"><x-lucide name="trash-2" />Xoá</button></form>
                @endif
            </div>
        </div>
    @endforeach
</section>
@endif

{{--
    SỬA 3/11 (khách: "tab Phổ thông thì form Phổ thông — file nhập như nào thì form Phổ thông như vậy") — FORM NHẬP TAY
    "Bài phổ thông" (1 bài nhiều câu). Các ô khớp ĐÚNG các mục của gói ZIP Phổ thông (question.json):
      content.title / code / description · statement.pdf · solution.pdf · taxonomy (môn, khối, tỉnh, năm, tag) · pedagogy.difficulty
      · attribution.source_citation · content.questions[] (type, title, prompt, grading, explanation, ảnh).
    Bấm "Tạo bài" → máy chủ đóng thành ĐÚNG 1 gói ZIP Phổ thông rồi đi lại đúng đường nhập ZIP (App\Support\GeneralSetFormPackage
    + GeneralSetController) — nên luật kiểm tra, chia điểm tổng 10, chấm bài… y hệt nhập file.
    Tham số: $gsfAction (route nhận form), $gsfNote (bài tạo ra nằm ở đâu). CSS tiền tố gsf-.
--}}
@php
    $gsfSubjects = \App\Support\SubjectCatalog::SUBJECTS;
    $gsfProvinceGroups = \App\Support\ProvinceCatalog::groups();
    $gsfScopes = \App\Support\ProvinceCatalog::QUESTION_SCOPES;
    $gsfYears = \App\Support\ProvinceCatalog::years();
    $gsfLevels = \App\Support\QuestionDifficulty::LEVELS;
    $gsfState = old('gsf_state');
    $gsfOldGrades = array_map('intval', (array) old('grades', []));
@endphp
<style>
    .gsf { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 18px; align-items: start; margin-top: 4px; }
    @media (max-width: 1100px) { .gsf { grid-template-columns: 1fr; } }
    .gsf-panel { padding: 20px; border: 1px solid #E3ECF5; border-radius: 22px; background: #fff; box-shadow: 0 2px 8px rgba(0, 90, 180, .04); }
    .gsf-panel + .gsf-panel { margin-top: 16px; }
    .gsf-h { display: flex; align-items: center; gap: 8px; margin: 0 0 14px; padding-bottom: 12px; border-bottom: 1px solid #EEF3F8; font-size: 17px; font-weight: 800; color: #1E3A5F; }
    .gsf-h svg { width: 18px; height: 18px; color: #6366F1; }
    .gsf-input > svg { width: 16px; height: 16px; flex: none; color: #6366F1; }
    .gsf-h small { margin-left: auto; font-size: 12px; font-weight: 600; color: #64748B; }
    .gsf-grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 14px; }
    @media (max-width: 700px) { .gsf-grid2 { grid-template-columns: 1fr; } }
    .gsf-field { display: grid; gap: 5px; min-width: 0; }
    .gsf-field > label, .gsf-lbl { font-size: 13px; font-weight: 700; color: #334155; }
    .gsf-field small, .gsf-hint { font-size: 11.5px; line-height: 1.5; color: #7A8EA3; }
    .gsf-req { color: #DC2626; }
    .gsf-input, .gsf-select, .gsf-area { width: 100%; min-height: 40px; padding: 8px 12px; border: 1px solid #D6E2EE; border-radius: 12px; background: #fff; font-size: 13.5px; color: #1E293B; }
    .gsf-area { min-height: 64px; resize: vertical; line-height: 1.5; }
    .gsf-input:focus, .gsf-select:focus, .gsf-area:focus { outline: none; border-color: #818CF8; box-shadow: 0 0 0 3px rgba(99, 102, 241, .14); }
    .gsf-grades { display: flex; flex-wrap: wrap; gap: 6px; }
    .gsf-grades label { display: inline-flex; align-items: center; gap: 6px; padding: 6px 10px; border: 1px solid #DCE6F0; border-radius: 10px; font-size: 12.5px; font-weight: 600; color: #475569; cursor: pointer; }
    .gsf-stack { display: grid; gap: 12px; }

    .gsf-q { position: relative; padding: 14px; border: 1px solid #DDE5F3; border-left: 4px solid #6366F1; border-radius: 16px; background: #FAFBFF; }
    .gsf-q + .gsf-q { margin-top: 12px; }
    .gsf-qhead { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-bottom: 10px; }
    .gsf-qno { display: inline-grid; place-items: center; min-width: 30px; height: 30px; padding: 0 8px; border-radius: 9px; background: #6366F1; color: #fff; font-size: 13px; font-weight: 800; }
    .gsf-qtype { width: auto; min-width: 170px; min-height: 34px; padding: 4px 10px; font-weight: 700; }
    .gsf-qpts { font-size: 12px; font-weight: 700; color: #4338CA; }
    .gsf-qtools { display: inline-flex; gap: 4px; margin-left: auto; }
    .gsf-ib { display: inline-grid; place-items: center; width: 30px; height: 30px; border: 1px solid #DCE6F0; border-radius: 9px; background: #fff; color: #475569; cursor: pointer; }
    .gsf-ib:hover:not(:disabled) { background: #EEF2FF; }
    .gsf-ib:disabled { opacity: .35; cursor: default; }
    .gsf-ib svg { width: 14px; height: 14px; }
    .gsf-ib--red { color: #B91C1C; border-color: #FECACA; }
    .gsf-ib--red:hover:not(:disabled) { background: #FEF2F2; }
    .gsf-box { padding: 10px 12px; border: 1px dashed #C7D2FE; border-radius: 12px; background: #fff; }
    .gsf-box-title { margin: 0 0 8px; font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; color: #4338CA; }
    .gsf-choice { display: grid; grid-template-columns: auto 26px minmax(0, 1fr) auto; align-items: center; gap: 8px; }
    .gsf-choice + .gsf-choice { margin-top: 6px; }
    .gsf-choice input[type=radio] { width: 16px; height: 16px; accent-color: #16A34A; }
    .gsf-letter { display: inline-grid; place-items: center; width: 26px; height: 26px; border-radius: 8px; background: #EEF2FF; font-size: 12px; font-weight: 800; color: #4338CA; }
    .gsf-choice.is-correct .gsf-letter { background: #16A34A; color: #fff; }
    .gsf-tf { display: inline-flex; gap: 6px; }
    .gsf-tf button { min-height: 34px; padding: 0 16px; border: 1px solid #DCE6F0; border-radius: 10px; background: #fff; font-size: 13px; font-weight: 700; color: #475569; cursor: pointer; }
    .gsf-tf button.is-on { border-color: #16A34A; background: #DCFCE7; color: #166534; }
    .gsf-tf button.is-on.is-false { border-color: #DC2626; background: #FEE2E2; color: #991B1B; }
    .gsf-part { display: grid; gap: 8px; padding: 10px; border: 1px solid #E2E8F0; border-radius: 12px; background: #F8FAFC; }
    .gsf-part + .gsf-part { margin-top: 8px; }
    .gsf-part-head { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
    .gsf-part-code { font-size: 13px; font-weight: 800; color: #1E3A5F; }
    .gsf-add { display: inline-flex; align-items: center; gap: 6px; min-height: 32px; margin-top: 8px; padding: 0 12px; border: 1px dashed #A5B4FC; border-radius: 10px; background: #fff; font-size: 12.5px; font-weight: 700; color: #4338CA; cursor: pointer; }
    .gsf-add:hover { background: #EEF2FF; }
    .gsf-add svg { width: 14px; height: 14px; }
    .gsf-addq { width: 100%; justify-content: center; min-height: 44px; margin-top: 14px; font-size: 14px; }
    .gsf-more { margin-top: 10px; }
    .gsf-more summary { cursor: pointer; font-size: 12.5px; font-weight: 700; color: #4338CA; }
    .gsf-more[open] summary { margin-bottom: 8px; }
    .gsf-aside { position: sticky; top: 16px; display: grid; gap: 16px; }
    .gsf-file { display: grid; gap: 4px; padding: 10px 12px; border: 1px dashed #C7D2FE; border-radius: 12px; background: #F8F9FF; }
    .gsf-file input { font-size: 12.5px; }
    .gsf-sum { padding: 12px; border-radius: 14px; background: #EEF2FF; font-size: 12.5px; line-height: 1.6; color: #3730A3; }
    .gsf-sum b { font-size: 15px; }
    .gsf-submit { display: inline-flex; width: 100%; align-items: center; justify-content: center; gap: 8px; min-height: 46px; border: 0; border-radius: 14px; background: #4F46E5; color: #fff; font-size: 14px; font-weight: 800; cursor: pointer; box-shadow: 0 8px 18px rgba(79, 70, 229, .25); }
    .gsf-submit:hover { background: #4338CA; }
    .gsf-submit:disabled { opacity: .6; cursor: wait; }
    .gsf-submit svg { width: 16px; height: 16px; }
    .gsf-err { margin: 0 0 12px; padding: 10px 12px; border-radius: 12px; border: 1px solid #FECACA; background: #FEF2F2; font-size: 12.5px; color: #B91C1C; }
    .gsf-err li { margin-left: 16px; list-style: disc; }
    .gsf-q.has-err { border-color: #FCA5A5; border-left-color: #DC2626; background: #FFF7F7; }
    .gsf-qerr { margin: 8px 0 0; font-size: 12px; font-weight: 600; color: #B91C1C; }
</style>

<form method="POST" action="{{ $gsfAction }}" enctype="multipart/form-data" class="gsf"
      x-data="oiGeneralSetForm(@js($gsfState))" @submit="onSubmit($event)">
    @csrf
    <input type="hidden" name="gsf_state" :value="stateJson()">
    <input type="hidden" name="questions_json" :value="questionsJson()">

    <div class="gsf-main">
        @if ($errors->has('general_form'))
            <ul class="gsf-err">@foreach ($errors->get('general_form') as $msg)<li>{{ $msg }}</li>@endforeach</ul>
        @endif

        {{-- ── Thông tin bài (content.title / code / description + taxonomy) ── --}}
        <section class="gsf-panel">
            <h2 class="gsf-h"><x-lucide name="list-checks" /> Thông tin bài phổ thông</h2>
            <div class="gsf-stack">
                <div class="gsf-grid2">
                    <div class="gsf-field">
                        <label for="gsf-code">Mã bài</label>
                        <input id="gsf-code" name="code" value="{{ old('code') }}" maxlength="60" class="gsf-input" placeholder="Ví dụ: HTML-B8 (để trống = tự tạo)">
                    </div>
                    {{-- SỬA 3/11 (khách: "tab Phổ thông thì loại cố định Phổ thông, không cho chọn") — dạng từng câu chọn ở danh sách câu bên dưới. --}}
                    <div class="gsf-field">
                        <span class="gsf-lbl">Loại bài</span>
                        <div class="gsf-input" aria-readonly="true" style="display:flex;align-items:center;gap:8px;background:#F1F5F9;color:#334155;font-weight:600">
                            <x-lucide name="list-checks" class="h-4 w-4" /> Phổ thông (1 bài nhiều câu)
                            <span style="margin-left:auto;font-size:11px;font-weight:500;color:#94A3B8">cố định ở tab này</span>
                        </div>
                    </div>
                </div>
                <div class="gsf-field">
                    <label for="gsf-title">Tên bài <span class="gsf-req">*</span></label>
                    <input id="gsf-title" name="title" value="{{ old('title') }}" maxlength="255" required class="gsf-input" placeholder="Ví dụ: Bài 8 - Định dạng văn bản (HTML)">
                </div>
                <div class="gsf-field">
                    <label for="gsf-desc">Mô tả ngắn</label>
                    <input id="gsf-desc" name="description" value="{{ old('description') }}" maxlength="255" class="gsf-input" placeholder="Ví dụ: 10 câu trắc nghiệm, đúng/sai về thẻ định dạng HTML">
                </div>
                <div class="gsf-grid2">
                    <div class="gsf-field">
                        <label for="gsf-subject">Môn học</label>
                        <select id="gsf-subject" name="subject" class="gsf-select">
                            <option value="">— Chưa phân loại —</option>
                            @foreach ($gsfSubjects as $code => $label)
                                <option value="{{ $code }}" @selected(old('subject', \App\Support\SubjectCatalog::DEFAULT_QUESTION_SUBJECT) === $code)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="gsf-field">
                        <span class="gsf-lbl">Khối lớp</span>
                        <div class="gsf-grades">
                            @foreach (range(6, 12) as $g)
                                <label><input type="checkbox" name="grades[]" value="{{ $g }}" @checked(in_array($g, $gsfOldGrades, true))> Lớp {{ $g }}</label>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="gsf-grid2">
                    <div class="gsf-field">
                        <label for="gsf-province">Tỉnh thành</label>
                        <select id="gsf-province" name="province" class="gsf-select">
                            @foreach ($gsfScopes as $code => $label)
                                <option value="{{ $code }}" @selected(old('province', \App\Support\ProvinceCatalog::DEFAULT_QUESTION_SCOPE) === $code)>{{ $label }}</option>
                            @endforeach
                            @foreach ($gsfProvinceGroups as $group => $items)
                                <optgroup label="{{ $group }}">
                                    @foreach ($items as $code => $label)
                                        <option value="{{ $code }}" @selected(old('province') === $code)>{{ $label }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>
                    <div class="gsf-field">
                        <label for="gsf-year">Năm</label>
                        <select id="gsf-year" name="exam_year" class="gsf-select">
                            @foreach ($gsfYears as $y)
                                <option value="{{ $y }}" @selected((int) old('exam_year', date('Y')) === $y)>{{ $y }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </section>

        {{-- ── Danh sách câu (content.questions) ── --}}
        <section class="gsf-panel">
            <h2 class="gsf-h"><x-lucide name="layers" /> Câu hỏi trong bài <small x-text="questions.length + ' câu · ' + pointText() + ' điểm/câu'"></small></h2>
            <p class="gsf-hint" style="margin:-4px 0 12px">Điểm <b>tự chia đều, tổng 10</b>. Nội dung câu để trống nếu câu đã nằm trong <b>đề PDF</b> — học sinh xem đề ở tab "Đề bài PDF" rồi chọn đáp án.</p>

            <template x-for="(q, qi) in questions" :key="q.uid">
                <div class="gsf-q" :class="errorOf(q) && showErrors ? 'has-err' : ''" :id="'gsf-q-' + q.uid">
                    <div class="gsf-qhead">
                        <span class="gsf-qno" x-text="'Câu ' + (qi + 1)"></span>
                        <select class="gsf-select gsf-qtype" x-model="q.type" aria-label="Dạng câu">
                            <option value="single_choice">Trắc nghiệm</option>
                            <option value="true_false">Đúng / Sai</option>
                            <option value="short_answer">Điền từ</option>
                            <option value="composite">Câu nhiều ý</option>
                        </select>
                        <span class="gsf-qpts" x-text="pointText() + ' điểm'"></span>
                        <span class="gsf-qtools">
                            <button type="button" class="gsf-ib" @click="move(qi, -1)" :disabled="qi === 0" title="Đưa lên"><x-lucide name="arrow-up" /></button>
                            <button type="button" class="gsf-ib" @click="move(qi, 1)" :disabled="qi === questions.length - 1" title="Đưa xuống"><x-lucide name="arrow-down" /></button>
                            <button type="button" class="gsf-ib gsf-ib--red" @click="removeQ(qi)" :disabled="questions.length === 1" title="Xoá câu"><x-lucide name="trash-2" /></button>
                        </span>
                    </div>

                    <div class="gsf-stack">
                        <div class="gsf-field">
                            <textarea class="gsf-area" rows="2" x-model="q.prompt" maxlength="10000"
                                      placeholder="Nội dung câu hỏi (tuỳ chọn — để trống nếu câu nằm trong đề PDF)"></textarea>
                        </div>

                        {{-- Trắc nghiệm: grading.choices + correct_answer --}}
                        <div class="gsf-box" x-show="q.type === 'single_choice'">
                            <p class="gsf-box-title">Phương án — chọn nút tròn ở phương án đúng</p>
                            <template x-for="(c, ci) in q.choices" :key="ci">
                                <div class="gsf-choice" :class="q.correct === c.id ? 'is-correct' : ''">
                                    <input type="radio" :name="'gsf-correct-' + q.uid" :checked="q.correct === c.id" @change="q.correct = c.id" :aria-label="'Đáp án đúng là ' + c.id">
                                    <span class="gsf-letter" x-text="c.id"></span>
                                    <input class="gsf-input" style="min-height:34px" x-model="c.text" maxlength="1000" :placeholder="'Nội dung phương án ' + c.id + ' (tuỳ chọn)'">
                                    <button type="button" class="gsf-ib gsf-ib--red" @click="removeChoice(q, ci)" :disabled="q.choices.length <= 2" title="Bỏ phương án"><x-lucide name="trash-2" /></button>
                                </div>
                            </template>
                            <button type="button" class="gsf-add" @click="addChoice(q)" x-show="q.choices.length < 8"><x-lucide name="plus" /> Thêm phương án</button>
                        </div>

                        {{-- Đúng / Sai: grading.correct_answer true|false --}}
                        <div class="gsf-box" x-show="q.type === 'true_false'">
                            <p class="gsf-box-title">Đáp án đúng</p>
                            <div class="gsf-tf">
                                <button type="button" :class="q.tf === true ? 'is-on' : ''" @click="q.tf = true">Đúng</button>
                                <button type="button" :class="q.tf === false ? 'is-on is-false' : ''" @click="q.tf = false">Sai</button>
                            </div>
                        </div>

                        {{-- Điền từ: grading.accepted_answers + normalization --}}
                        <div class="gsf-box" x-show="q.type === 'short_answer'">
                            <p class="gsf-box-title">Đáp án được chấp nhận — mỗi dòng 1 đáp án</p>
                            <textarea class="gsf-area" rows="2" x-model="q.answers" placeholder="h1&#10;<h1>"></textarea>
                            <label class="gsf-hint" style="display:inline-flex;align-items:center;gap:6px;margin-top:6px"><input type="checkbox" x-model="q.caseSensitive"> Phân biệt chữ HOA / thường</label>
                        </div>

                        {{-- Câu nhiều ý: grading.parts[] --}}
                        <div class="gsf-box" x-show="q.type === 'composite'">
                            <p class="gsf-box-title">Các ý của câu</p>
                            <template x-for="(p, pi) in q.parts" :key="pi">
                                <div class="gsf-part">
                                    <div class="gsf-part-head">
                                        <span class="gsf-part-code" x-text="'Ý ' + partCode(pi) + ')'"></span>
                                        <select class="gsf-select" style="width:auto;min-height:32px;padding:2px 8px" x-model="p.type" aria-label="Kiểu trả lời của ý">
                                            <option value="true_false">Đúng / Sai</option>
                                            <option value="short_answer">Điền từ</option>
                                            <option value="single_choice">Trắc nghiệm</option>
                                        </select>
                                        <button type="button" class="gsf-ib gsf-ib--red" style="margin-left:auto" @click="q.parts.splice(pi, 1)" :disabled="q.parts.length <= 1" title="Bỏ ý"><x-lucide name="trash-2" /></button>
                                    </div>
                                    <input class="gsf-input" style="min-height:34px" x-model="p.prompt" maxlength="2000" placeholder="Nội dung ý (tuỳ chọn)">
                                    <div class="gsf-tf" x-show="p.type === 'true_false'">
                                        <button type="button" :class="p.tf === true ? 'is-on' : ''" @click="p.tf = true">Đúng</button>
                                        <button type="button" :class="p.tf === false ? 'is-on is-false' : ''" @click="p.tf = false">Sai</button>
                                    </div>
                                    <input class="gsf-input" style="min-height:34px" x-show="p.type === 'short_answer'" x-model="p.answers" placeholder="Đáp án chấp nhận, cách nhau dấu ; (vd: 2; hai)">
                                    <div x-show="p.type === 'single_choice'" style="display:flex;flex-wrap:wrap;align-items:center;gap:8px">
                                        <span class="gsf-hint">Số phương án</span>
                                        <select class="gsf-select" style="width:auto;min-height:32px;padding:2px 8px" x-model.number="p.nChoices">
                                            <template x-for="n in [2,3,4,5,6]" :key="n"><option :value="n" x-text="n" :selected="p.nChoices === n"></option></template>
                                        </select>
                                        <span class="gsf-hint">Đáp án đúng</span>
                                        <select class="gsf-select" style="width:auto;min-height:32px;padding:2px 8px" x-model="p.correct">
                                            <template x-for="l in letters(p.nChoices)" :key="l"><option :value="l" x-text="l" :selected="p.correct === l"></option></template>
                                        </select>
                                    </div>
                                </div>
                            </template>
                            <button type="button" class="gsf-add" @click="q.parts.push(newPart())" x-show="q.parts.length < 10"><x-lucide name="plus" /> Thêm ý</button>
                        </div>

                        <details class="gsf-more">
                            <summary>Tiêu đề câu · Ảnh · Lời giải ngắn (tuỳ chọn)</summary>
                            <div class="gsf-stack">
                                <input class="gsf-input" x-model="q.title" maxlength="255" placeholder="Tiêu đề câu (để trống = tự đặt 'Tên bài — Câu N')">
                                <div class="gsf-field">
                                    <span class="gsf-lbl" style="font-size:12px">Ảnh minh hoạ của câu</span>
                                    <input type="file" accept="image/*" :name="'q_image[' + q.uid + ']'" style="font-size:12.5px">
                                </div>
                                <textarea class="gsf-area" rows="2" x-model="q.explanation" maxlength="5000" placeholder="Lời giải ngắn — hiện cho học sinh sau khi nộp bài"></textarea>
                            </div>
                        </details>

                        <p class="gsf-qerr" x-show="showErrors && errorOf(q)" x-text="errorOf(q)"></p>
                    </div>
                </div>
            </template>

            <button type="button" class="gsf-add gsf-addq" @click="addQ()"><x-lucide name="plus" /> Thêm câu</button>
        </section>
    </div>

    <aside class="gsf-aside">
        <section class="gsf-panel">
            <h3 class="gsf-h" style="font-size:15px"><x-lucide name="file-text" /> Đề &amp; lời giải PDF</h3>
            <div class="gsf-stack">
                <label class="gsf-file">
                    <span class="gsf-lbl">Đề cả bài (statement.pdf)</span>
                    <input type="file" name="statement_pdf" accept="application/pdf,.pdf">
                    <small class="gsf-hint">Học sinh xem ở tab "Đề bài PDF" khi làm bài.</small>
                </label>
                <label class="gsf-file">
                    <span class="gsf-lbl">Lời giải cả bài (solution.pdf)</span>
                    <input type="file" name="solution_pdf" accept="application/pdf,.pdf">
                    <small class="gsf-hint">Không bắt buộc — hiện sau khi học sinh nộp bài.</small>
                </label>
            </div>
        </section>

        <section class="gsf-panel">
            <h3 class="gsf-h" style="font-size:15px"><x-lucide name="tag" /> Phân loại</h3>
            <div class="gsf-stack">
                <div class="gsf-field">
                    <label for="gsf-diff">Độ khó</label>
                    <select id="gsf-diff" name="difficulty" class="gsf-select">
                        <option value="">— Chưa đặt —</option>
                        @foreach ($gsfLevels as $key => $label)
                            <option value="{{ $key }}" @selected(old('difficulty') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="gsf-field">
                    <label for="gsf-tags">Tag / Chuyên đề</label>
                    <input id="gsf-tags" name="tags" value="{{ old('tags') }}" maxlength="500" class="gsf-input" placeholder="html, web (cách nhau dấu phẩy)">
                </div>
                <div class="gsf-field">
                    <label for="gsf-source">Nguồn / Tác giả</label>
                    <input id="gsf-source" name="source" value="{{ old('source') }}" maxlength="120" class="gsf-input" placeholder="Ví dụ: Ôn Thi 360 biên soạn">
                </div>
            </div>
        </section>

        <div class="gsf-sum">
            <b x-text="questions.length + ' câu'"></b> · <span x-text="summaryText()"></span><br>
            Mỗi câu <b x-text="pointText()"></b> điểm · tổng 10 điểm.<br>
            Học sinh làm cả bài, <b>nộp mới chấm</b>, xong xem đáp án + lời giải. {{ $gsfNote ?? '' }}
        </div>
        <button type="submit" class="gsf-submit" :disabled="submitting">
            <x-lucide name="save" /><span x-text="submitting ? 'Đang tạo bài…' : 'Tạo bài phổ thông (Nháp)'">Tạo bài phổ thông (Nháp)</span>
        </button>
    </aside>
</form>

<script>
    window.oiGeneralSetForm = function (saved) {
        const LETTERS = 'ABCDEFGH'.split('');
        let seq = 0;
        const uid = () => 'q' + Date.now().toString(36) + (seq++);
        const newPart = () => ({ type: 'true_false', prompt: '', tf: null, answers: '', nChoices: 4, correct: 'A' });
        const newQ = () => ({
            uid: uid(), type: 'single_choice', title: '', prompt: '', explanation: '',
            choices: ['A', 'B', 'C', 'D'].map((id) => ({ id, text: '' })), correct: '',
            tf: null, answers: '', caseSensitive: false, parts: [newPart(), newPart()],
        });
        let questions = [];
        try {
            const s = saved ? JSON.parse(saved) : null;
            if (Array.isArray(s) && s.length) questions = s.map((q) => Object.assign(newQ(), q, { uid: q.uid || uid() }));
        } catch (e) { /* bỏ qua — bắt đầu lại form trống */ }
        if (! questions.length) questions = [newQ()];

        return {
            questions, submitting: false, showErrors: false,
            newPart,
            letters(n) { return LETTERS.slice(0, Math.max(2, Math.min(8, n || 4))); },
            partCode(i) { return 'abcdefghij'[i] || String(i + 1); },
            addQ() {
                const last = this.questions[this.questions.length - 1];
                const q = newQ();
                if (last) q.type = last.type; // câu mới cùng dạng câu trước — soạn liền tay nhanh hơn
                this.questions.push(q);
                this.$nextTick(() => { const el = document.getElementById('gsf-q-' + q.uid); if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' }); });
            },
            removeQ(i) { if (this.questions.length > 1 && confirm('Xoá câu ' + (i + 1) + '?')) this.questions.splice(i, 1); },
            move(i, d) { const j = i + d; if (j < 0 || j >= this.questions.length) return; const a = this.questions; [a[i], a[j]] = [a[j], a[i]]; },
            addChoice(q) { q.choices.push({ id: LETTERS[q.choices.length], text: '' }); },
            removeChoice(q, i) {
                // Giữ đúng phương án đã đánh dấu đúng sau khi các chữ cái được đánh lại (A, B, C…).
                const correctObj = q.choices.find((c) => c.id === q.correct) || null;
                q.choices.splice(i, 1);
                q.choices.forEach((c, k) => { c.id = LETTERS[k]; });
                q.correct = correctObj && q.choices.includes(correctObj) ? correctObj.id : '';
            },
            pointText() {
                const n = this.questions.length || 1;
                return (Math.floor(1000 / n) / 100).toLocaleString('vi-VN', { maximumFractionDigits: 2 });
            },
            summaryText() {
                const L = { single_choice: 'Trắc nghiệm', true_false: 'Đúng/Sai', short_answer: 'Điền từ', composite: 'Câu nhiều ý' };
                const c = {};
                this.questions.forEach((q) => { c[q.type] = (c[q.type] || 0) + 1; });
                return Object.keys(L).filter((k) => c[k]).map((k) => c[k] + ' ' + L[k]).join(' · ');
            },
            splitAnswers(s, sep) { return String(s || '').split(sep).map((v) => v.trim()).filter((v) => v !== ''); },
            errorOf(q) {
                if (q.type === 'single_choice' && ! q.choices.some((c) => c.id === q.correct)) return 'Chưa chọn phương án đúng.';
                if (q.type === 'true_false' && typeof q.tf !== 'boolean') return 'Chưa chọn đáp án Đúng hay Sai.';
                if (q.type === 'short_answer' && ! this.splitAnswers(q.answers, /\n/).length) return 'Chưa nhập đáp án được chấp nhận.';
                if (q.type === 'composite') {
                    for (let i = 0; i < q.parts.length; i++) {
                        const p = q.parts[i];
                        if (p.type === 'true_false' && typeof p.tf !== 'boolean') return 'Ý ' + this.partCode(i) + ') chưa chọn Đúng / Sai.';
                        if (p.type === 'short_answer' && ! this.splitAnswers(p.answers, /;/).length) return 'Ý ' + this.partCode(i) + ') chưa nhập đáp án.';
                    }
                }
                return '';
            },
            // ĐÚNG khuôn content.questions của gói ZIP Phổ thông (GeneralSetZipPackage) — máy chủ đóng gói lại y hệt.
            toItem(q, i) {
                const item = { no: i + 1, type: q.type, points: 1, uid: q.uid };
                if (q.title.trim()) item.title = q.title.trim();
                if (q.prompt.trim()) item.prompt = q.prompt.trim();
                if (q.explanation.trim()) item.explanation = q.explanation.trim();
                if (q.type === 'single_choice') item.grading = { choices: q.choices.map((c) => ({ id: c.id, text: c.text })), correct_answer: q.correct };
                if (q.type === 'true_false') item.grading = { correct_answer: q.tf };
                if (q.type === 'short_answer') item.grading = { accepted_answers: this.splitAnswers(q.answers, /\n/), normalization: { trim: true, case_sensitive: !! q.caseSensitive, remove_diacritics: false } };
                if (q.type === 'composite') {
                    item.grading = { mode: 'per_part', parts: q.parts.map((p, k) => {
                        const part = { code: this.partCode(k), prompt: p.prompt.trim(), response_type: p.type, points: 1 };
                        if (p.type === 'true_false') part.correct_answer = p.tf;
                        if (p.type === 'short_answer') part.accepted_answers = this.splitAnswers(p.answers, /;/);
                        if (p.type === 'single_choice') { part.choices = this.letters(p.nChoices); part.correct_answer = part.choices.includes(p.correct) ? p.correct : 'A'; }
                        return part;
                    }) };
                }
                return item;
            },
            questionsJson() { return JSON.stringify(this.questions.map((q, i) => this.toItem(q, i))); },
            stateJson() { return JSON.stringify(this.questions); },
            onSubmit(e) {
                const bad = this.questions.find((q) => this.errorOf(q));
                if (bad) {
                    e.preventDefault();
                    this.showErrors = true;
                    const el = document.getElementById('gsf-q-' + bad.uid);
                    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    return;
                }
                this.submitting = true;
            },
        };
    };
</script>

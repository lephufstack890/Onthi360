@extends('layouts.admin')

@section('title', 'Sửa bài tập')
@section('page-title', 'Sửa bài tập')

@section('content')
    @php
        $allTags = $allTags ?? collect();
        $config = $exercise->grading_config ?? [];
        $testCasesCount = count($config['test_cases'] ?? []);
        $timeLimitMs = $config['time_limit_ms'] ?? 1000;
        $memoryLimitMb = $config['memory_limit_mb'] ?? 256;
        $zipAttachments = $exercise->metadata['attachments'] ?? [];
        $exerciseType = $exercise->type->value;
        $zipAssets = $exercise->metadata['assets'] ?? [];
        $isDraft = $isDraft ?? false;
        $chapters = $chapters ?? [];
        $chapterLabel = $product->chapterLabel();

        // SỬA 14/10 (khách: "dạng lập trình thì sửa được code mẫu, lời giải, đề bài và bộ test — giống sửa câu hỏi ở Kho
        // bài tập") — dữ liệu cho các khối sửa của câu Lập trình (cùng partial/luật với admin/content/questions/edit).
        $isCoding = $exerciseType === 'coding';
        $exTests = collect($config['test_cases'] ?? [])->map(fn ($c) => [
            'input' => (string) ($c['input'] ?? ''),
            'output' => (string) ($c['output'] ?? ''),
        ])->values()->all();
        $exFileIoInput = $config['file_io']['input'] ?? '';
        $exFileIoOutput = $config['file_io']['output'] ?? '';
        $exIoMode = (trim((string) old('file_io_input', $exFileIoInput)) !== '' || trim((string) old('file_io_output', $exFileIoOutput)) !== '') ? 'file' : 'std';
        $exAttachmentFields = [
            'statement' => ['statement_file', 'Đề bài (PDF)', 'application/pdf', 'Học sinh đọc ở tab "Đề bài" lúc làm bài. Tối đa 20 MB.', 'file-text'],
            'solution' => ['solution_file', 'Lời giải (PDF)', 'application/pdf', 'Chỉ Admin/Giáo viên xem được. Tối đa 20 MB.', 'file-check'],
            'reference' => ['reference_file', 'Code mẫu', '.cpp,.cc,.c,.py,.pas,.java,.js,.ts,.txt,.md', 'Tệp mã nguồn (.cpp, .py, .pas…), tối đa 2 MB.', 'code-2'],
        ];

        // SỬA 14/10 (khách: "trắc nghiệm, điền từ vẫn cho sửa thoải mái, lỡ nhập lộn") — dữ liệu cho khối sửa câu nhập tay:
        // đổi dạng (Trắc nghiệm / Đúng-Sai / Điền từ), câu hỏi, đáp án, đáp án đúng. old() đổ lại khi lần lưu trước bị lỗi.
        $isManual = in_array($exerciseType, ['mcq', 'fill_blank'], true);
        $mfSubmitted = old('_token') !== null;
        $mfStoredOptions = array_values($config['options'] ?? []);
        $mfIsTf = ($exercise->metadata['manual_variant'] ?? null) === 'true_false'
            || ($exerciseType === 'mcq' && $mfStoredOptions === ['Đúng', 'Sai']);
        $mfKind = old('kind', $exerciseType === 'fill_blank' ? 'fill_blank' : ($mfIsTf ? 'true_false' : 'mcq'));
        $mfCorrect = $config['correct_options'][0] ?? null;
        $mfOptions = $mfSubmitted && is_array(old('options'))
            ? array_values(array_map('strval', old('options')))
            : ($exerciseType === 'mcq' && ! $mfIsTf ? $mfStoredOptions : []);
        if (count($mfOptions) < 2) {
            $mfOptions = array_pad($mfOptions, 4, '');
        }
        $mf = [
            'kind' => $mfKind,
            'options' => $mfOptions,
            'correct' => (string) old('correct_option', $exerciseType === 'mcq' && ! $mfIsTf && $mfCorrect !== null ? $mfCorrect : ''),
            'tf' => (string) old('tf_answer', $mfIsTf && $mfCorrect !== null ? ((int) $mfCorrect === 0 ? 'true' : 'false') : ''),
            'accepted' => (string) old('accepted_answers', implode("\n", $config['accepted_answers'] ?? [])),
            'caseSensitive' => $mfSubmitted ? (bool) old('case_sensitive') : (bool) ($config['case_sensitive'] ?? false),
        ];
    @endphp

    <a href="{{ route('admin.products.show', $product->id) }}" class="text-[13px] text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-blue-600">‹ Quay lại {{ $product->title }}</a>

    <x-ws.page-header title="Sửa bài tập" icon="layers" :subtitle="$product->title" />

    @if ($isDraft)
        <div class="rounded-xl border border-sky-200 bg-sky-50 p-4 mb-6 text-[13px] text-sky-800 flex items-start gap-2">
            <span class="shrink-0"><x-lucide name="info" class="h-4 w-4" /></span>
            <p>
                Bài tập này vừa đọc xong từ gói ZIP — kiểm tra lại thông tin bên dưới rồi bấm
                <strong>"Lưu bài tập"</strong> để hoàn tất. Nếu bạn rời trang này mà chưa bấm Lưu,
                bài tập sẽ <strong>tự động bị xoá</strong> — không cần thao tác gì thêm, bạn có
                thể chọn ZIP lại bất cứ lúc nào.
            </p>
        </div>
    @endif

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    @if ($isManual)
        @include('admin.products.partials.adder-style')
        <style>
            .mf-ck .ck-editor__editable_inline { min-height: 130px; }
            .mf-opt-del { display: grid; flex-shrink: 0; place-items: center; width: 30px; height: 30px; border-radius: 9px; color: #94A3B8; font-size: 18px; line-height: 1; cursor: pointer; }
            .mf-opt-del:hover { background: #FFF1F2; color: #BE123C; }
            .mf-opt-del:disabled { visibility: hidden; }
            .mf-add { display: inline-flex; align-items: center; gap: 6px; margin-top: 8px; padding: 7px 12px; border: 1.5px dashed #A9C6DD; border-radius: 10px; font-size: 12.5px; font-weight: 700; color: #2563EB; cursor: pointer; }
            .mf-add:hover { border-color: #2563EB; background: #F5F9FF; }
            .mf-pv-opt { padding: 7px 10px; border: 1px solid #DDEAF0; border-radius: 10px; font-size: 13px; color: #45657D; }
            .mf-pv-opt.is-ok { border-color: #6EE7B7; background: #ECFDF5; color: #047857; font-weight: 600; }
            .mf-chip { display: inline-flex; padding: 4px 10px; border-radius: 999px; background: #ECFDF5; color: #047857; font-size: 12px; font-weight: 600; }
        </style>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6"
         @if ($isManual)
         x-data="{ kind: @js($mf['kind']), options: @js($mf['options']), correct: @js($mf['correct']), tf: @js($mf['tf']),
                   accepted: @js($mf['accepted']), caseSensitive: @js($mf['caseSensitive']),
                   letter(i) { return String.fromCharCode(65 + i); },
                   addOption() { if (this.options.length < 8) this.options.push(''); },
                   removeOption(i) {
                       if (this.options.length <= 2) return;
                       this.options.splice(i, 1);
                       const c = this.correct === '' ? -1 : Number(this.correct);
                       if (c === i) this.correct = ''; else if (c > i) this.correct = String(c - 1);
                   },
                   acceptedList() { return this.accepted.split(/\r?\n|,/).map(a => a.trim()).filter(Boolean); } }"
         @endif>
        <div class="lg:col-span-2 bg-white rounded-3xl border border-sky-100 p-5 space-y-4">
            <form method="POST" action="{{ route('admin.products.exercises.update', [$product->id, $exercise->id]) }}" class="space-y-4"
                  @if ($isCoding) enctype="multipart/form-data" @endif>
                @csrf
                @method('PUT')

                <div>
                    <label class="block text-[13px] font-medium text-slate-600 mb-1" for="title">Tiêu đề bài tập</label>
                    <input id="title" name="title" type="text" value="{{ old('title', $exercise->title) }}" required maxlength="255"
                           class="admin-input">
                </div>

                <div>
                    <label class="block text-[13px] font-medium text-slate-600 mb-1" for="points">Điểm</label>
                    <input id="points" name="points" type="number" min="0" value="{{ old('points', $exercise->points) }}"
                           class="w-40 rounded-xl border border-sky-100 text-[13px] p-2.5 hover:border-blue-200 focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-300 transition">
                </div>

                @if ($chapterLabel && ! empty($chapters))
                    <div>
                        <label class="block text-[13px] font-medium text-slate-600 mb-1" for="material_id">Thuộc {{ mb_strtolower($chapterLabel) }}</label>
                        <x-ws.select id="material_id" name="material_id">
                            <option value="">— Chưa gắn {{ mb_strtolower($chapterLabel) }} —</option>
                            @foreach ($chapters as $c)
                                <option value="{{ $c['id'] }}" @selected((string) old('material_id', $exercise->material_id) === (string) $c['id'])>{{ $c['title'] }}</option>
                            @endforeach
                        </x-ws.select>
                    </div>
                @endif

                @if ($isManual)
                    {{-- ══════ SỬA 14/10 — CÂU NHẬP TAY: đổi dạng + sửa câu hỏi / đáp án ══════ --}}
                    <div class="oi-add" style="border-style:solid;background:#FAFCFF">
                        <div>
                            <span class="oi-add__lbl">Dạng bài <small>— chọn lại nếu lỡ chọn nhầm</small></span>
                            <input type="hidden" name="kind" :value="kind">
                            <div class="oi-kinds" style="grid-template-columns:repeat(auto-fit,minmax(170px,1fr))" role="radiogroup" aria-label="Dạng bài tập">
                                @foreach (['mcq' => ['Trắc nghiệm', 'Nhiều đáp án · chọn 1 đúng', 'list-checks'], 'true_false' => ['Đúng / Sai', '1 nhận định', 'check-circle-2'], 'fill_blank' => ['Điền từ', 'Gõ đáp án vào ô trống', 'text-cursor-input']] as $k => [$kLabel, $kDesc, $kIcon])
                                    <button type="button" role="radio" class="oi-kind" :class="kind === '{{ $k }}' ? 'is-on' : ''"
                                            :aria-checked="kind === '{{ $k }}' ? 'true' : 'false'" @click="kind = '{{ $k }}'">
                                        <span class="oi-kind__ic"><x-lucide name="{{ $kIcon }}" /></span>
                                        <span><b>{{ $kLabel }}</b><small>{{ $kDesc }}</small></span>
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <div class="mf-ck">
                            <label class="oi-add__lbl" for="body"><span x-text="kind === 'true_false' ? 'Nhận định cần đánh giá Đúng / Sai' : (kind === 'fill_blank' ? 'Đề bài (ghi rõ chỗ cần điền, VD: ___ )' : 'Câu hỏi')">Câu hỏi</span></label>
                            <textarea id="body" name="body" rows="4" data-rich-editor class="oi-add__input">{{ old('body', $exercise->body) }}</textarea>
                        </div>

                        {{-- Trắc nghiệm: 2–8 đáp án, thêm/bớt được --}}
                        <div x-show="kind === 'mcq'">
                            <span class="oi-add__lbl">Đáp án <small>— bấm "Đúng" ở đáp án đúng · × để bỏ một đáp án</small></span>
                            <div class="oi-opts">
                                <template x-for="(opt, i) in options" :key="i">
                                    <div class="oi-opt" :class="correct === String(i) ? 'is-correct' : ''">
                                        <label class="oi-opt__pick">
                                            <input type="radio" name="correct_option" :value="i" :checked="correct === String(i)" @change="correct = String(i)" :disabled="kind !== 'mcq'">
                                            <span x-text="correct === String(i) ? '✓ Đúng' : 'Đúng'"></span>
                                        </label>
                                        <b style="width:18px;color:#123B68" x-text="letter(i) + '.'"></b>
                                        <input type="text" name="options[]" maxlength="255" class="oi-add__input" x-model="options[i]"
                                               :placeholder="'Nội dung đáp án ' + letter(i)" :required="kind === 'mcq'" :disabled="kind !== 'mcq'">
                                        <button type="button" class="mf-opt-del" title="Bỏ đáp án này" :disabled="options.length <= 2" @click="removeOption(i)">×</button>
                                    </div>
                                </template>
                            </div>
                            <button type="button" class="mf-add" x-show="options.length < 8" @click="addOption()"><x-lucide name="plus" class="h-3.5 w-3.5" /> Thêm đáp án</button>
                        </div>

                        {{-- Đúng / Sai --}}
                        <div x-show="kind === 'true_false'" x-cloak>
                            <span class="oi-add__lbl">Đáp án đúng của nhận định</span>
                            <div class="oi-tf" role="radiogroup">
                                <label :class="tf === 'true' ? 'is-on-true' : ''"><input type="radio" name="tf_answer" value="true" :checked="tf === 'true'" @change="tf = 'true'" :disabled="kind !== 'true_false'"> ✓ Đúng</label>
                                <label :class="tf === 'false' ? 'is-on-false' : ''"><input type="radio" name="tf_answer" value="false" :checked="tf === 'false'" @change="tf = 'false'" :disabled="kind !== 'true_false'"> ✕ Sai</label>
                            </div>
                        </div>

                        {{-- Điền từ --}}
                        <div x-show="kind === 'fill_blank'" x-cloak>
                            <label class="oi-add__lbl" for="accepted_answers">Đáp án được chấp nhận <small>— mỗi dòng một cách viết đúng</small></label>
                            <textarea id="accepted_answers" name="accepted_answers" rows="3" maxlength="2000" class="oi-add__input" x-model="accepted"
                                      placeholder="def&#10;DEF" :required="kind === 'fill_blank'" :disabled="kind !== 'fill_blank'"></textarea>
                            <label class="oi-check" style="margin-top:8px">
                                <input type="checkbox" name="case_sensitive" value="1" x-model="caseSensitive" :disabled="kind !== 'fill_blank'"> Phân biệt chữ hoa / chữ thường
                            </label>
                        </div>
                    </div>
                @endif

                <div>
                    <label class="block text-[13px] font-medium text-slate-600 mb-2">Tag/chuyên đề</label>
                    {{-- SỬA 14/10 (khách: "tag nhiều quá, làm giống chỗ tạo bài tập để tìm cho tiện") — dùng chung ô chọn có tìm kiếm. --}}
                    @include('partials.tag-picker', ['tags' => $allTags, 'selected' => old('tag_ids', $exercise->tags->pluck('id')->all())])
                    <div style="height:8px"></div>
                    <input type="text" name="new_tags" value="{{ old('new_tags') }}" maxlength="500" placeholder="Tag mới, cách nhau bằng dấu phẩy"
                           class="admin-input">
                </div>

                @if ($isCoding)
                    {{-- ══════ SỬA 14/10 — CÂU LẬP TRÌNH: sửa giới hạn chạy, bộ test, đề bài / lời giải / code mẫu ══════ --}}
                    @include('admin.content.questions._editor-style')
                    @include('admin.products.exercises._coding-editor-style')
                    <div class="qe ex-coding">
                        <section class="ex-block">
                            <h3 class="ex-h"><x-lucide name="gauge" /> Cấu hình chấm bài</h3>
                            <div class="ex-grid2">
                                <div>
                                    <label class="ex-lbl" for="time_limit_ms">Giới hạn thời gian (ms)</label>
                                    <input id="time_limit_ms" name="time_limit_ms" type="number" min="1" max="60000" value="{{ old('time_limit_ms', $timeLimitMs) }}" class="admin-input">
                                </div>
                                <div>
                                    <label class="ex-lbl" for="memory_limit_mb">Giới hạn bộ nhớ (MB)</label>
                                    <input id="memory_limit_mb" name="memory_limit_mb" type="number" min="1" max="4096" value="{{ old('memory_limit_mb', $memoryLimitMb) }}" class="admin-input">
                                </div>
                            </div>
                            <div x-data="{ ioMode: @js(old('io_mode', $exIoMode)) }" class="ex-io">
                                <label class="ex-lbl" for="io_mode">Đọc / ghi dữ liệu</label>
                                <select id="io_mode" name="io_mode" x-model="ioMode" class="admin-input" style="max-width:320px">
                                    <option value="std">Bàn phím / màn hình (stdin/stdout)</option>
                                    <option value="file">Tệp có tên</option>
                                </select>
                                <div class="ex-grid2" x-show="ioMode === 'file'" x-cloak style="margin-top:10px">
                                    <div>
                                        <label class="ex-lbl" for="file_io_input">Tên tệp vào</label>
                                        <input id="file_io_input" name="file_io_input" type="text" maxlength="64" value="{{ old('file_io_input', $exFileIoInput) }}"
                                               placeholder="VD: BAI1.INP" :required="ioMode === 'file'" class="admin-input font-mono">
                                    </div>
                                    <div>
                                        <label class="ex-lbl" for="file_io_output">Tên tệp ra</label>
                                        <input id="file_io_output" name="file_io_output" type="text" maxlength="64" value="{{ old('file_io_output', $exFileIoOutput) }}"
                                               placeholder="VD: BAI1.OUT" :required="ioMode === 'file'" class="admin-input font-mono">
                                    </div>
                                </div>
                                <p class="ex-note" x-show="ioMode === 'file'" x-cloak>Học sinh <b>bắt buộc</b> đọc/ghi đúng 2 tệp này (C++: <code>freopen</code>; Python: <code>open</code>). Chỉ dùng chữ, số và <code>. _ -</code>.</p>
                            </div>
                        </section>

                        <section class="ex-block">
                            <h3 class="ex-h"><x-lucide name="database" /> Bộ test</h3>
                            <p class="ex-note" style="margin-bottom:10px">Đang có <b>{{ $testCasesCount }}</b> test. Nhập bộ test mới (tệp .zip hoặc các cặp .in/.out) sẽ <b>thay toàn bộ</b> bộ test cũ khi bấm Lưu; không nhập thì giữ nguyên.</p>
                            @include('admin.content.questions._test-set', ['qeMode' => 'edit', 'qeInitialTests' => $exTests])
                        </section>

                        <section class="ex-block">
                            <h3 class="ex-h"><x-lucide name="paperclip" /> Đề bài · Lời giải · Code mẫu</h3>
                            <div class="ex-files">
                                @foreach ($exAttachmentFields as $kind => [$field, $label, $accept, $hint, $icon])
                                    <div class="ex-file" x-data="{ picked: '' }">
                                        <div class="ex-file__head"><x-lucide name="{{ $icon }}" /><b>{{ $label }}</b></div>
                                        @if (isset($zipAttachments[$kind]['path']))
                                            <div class="ex-file__cur">
                                                <a href="{{ route('admin.products.exercises.attachment', [$product->id, $exercise->id, $kind]) }}">⬇ {{ $zipAttachments[$kind]['filename'] ?? 'Tệp đang có' }}</a>
                                                <label class="ex-file__rm"><input type="checkbox" name="remove_attachments[]" value="{{ $kind }}"
                                                       @checked(in_array($kind, (array) old('remove_attachments', []), true))> Bỏ tệp này</label>
                                            </div>
                                        @else
                                            <p class="ex-note">Chưa có tệp.</p>
                                        @endif
                                        <label class="ex-file__pick" :class="picked ? 'has-file' : ''">
                                            <x-lucide name="upload" />
                                            <span x-text="picked || '{{ isset($zipAttachments[$kind]['path']) ? 'Chọn tệp mới để thay' : 'Chọn tệp' }}'">{{ isset($zipAttachments[$kind]['path']) ? 'Chọn tệp mới để thay' : 'Chọn tệp' }}</span>
                                            <input type="file" name="{{ $field }}" accept="{{ $accept }}" class="ex-sr"
                                                   @change="picked = ($event.target.files[0] && $event.target.files[0].name) || ''">
                                        </label>
                                        <p class="ex-note">{{ $hint }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    </div>
                @endif

                <button type="submit" class="w-full inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white shadow-sm shadow-blue-200 transition-colors hover:bg-blue-700 hover:bg-blue-700 transition">
                    💾 Lưu bài tập
                </button>
            </form>
        </div>

        <div class="bg-white rounded-3xl border border-sky-100 p-5 space-y-4">
            <h3 class="font-medium text-slate-700 flex items-center gap-2"><span><x-lucide name="eye" class="h-4 w-4" /></span> {{ $isManual ? 'Xem trước' : 'Xem trước (chỉ đọc)' }}</h3>
            @if ($isManual)
                <p class="text-xs text-slate-400">Xem nhanh đáp án theo những gì đang nhập bên trái — bấm "Lưu bài tập" để lưu.</p>
                <div class="text-[13px] space-y-2">
                    <p><span class="text-slate-500">Dạng:</span> <b class="text-slate-700" x-text="kind === 'mcq' ? 'Trắc nghiệm' : (kind === 'true_false' ? 'Đúng / Sai' : 'Điền từ')"></b></p>
                    <template x-if="kind === 'mcq'">
                        <div class="space-y-1.5">
                            <template x-for="(opt, i) in options" :key="i">
                                <div class="mf-pv-opt" :class="correct === String(i) ? 'is-ok' : ''" x-text="(correct === String(i) ? '✓ ' : '') + letter(i) + '. ' + (opt || '…')"></div>
                            </template>
                            <p class="text-xs text-rose-600" x-show="correct === ''">Chưa chọn đáp án đúng.</p>
                        </div>
                    </template>
                    <template x-if="kind === 'true_false'">
                        <div class="space-y-1.5">
                            <div class="mf-pv-opt" :class="tf === 'true' ? 'is-ok' : ''" x-text="(tf === 'true' ? '✓ ' : '') + 'Đúng'"></div>
                            <div class="mf-pv-opt" :class="tf === 'false' ? 'is-ok' : ''" x-text="(tf === 'false' ? '✓ ' : '') + 'Sai'"></div>
                            <p class="text-xs text-rose-600" x-show="tf === ''">Chưa chọn Đúng hay Sai.</p>
                        </div>
                    </template>
                    <template x-if="kind === 'fill_blank'">
                        <div class="space-y-1.5">
                            <p class="text-slate-500">Đáp án chấp nhận:</p>
                            <div class="flex flex-wrap gap-1.5">
                                <template x-for="a in acceptedList()" :key="a"><span class="mf-chip" x-text="a"></span></template>
                            </div>
                            <p class="text-xs text-rose-600" x-show="acceptedList().length === 0">Chưa có đáp án nào.</p>
                            <p class="text-xs text-slate-400" x-text="caseSensitive ? 'Phân biệt hoa/thường' : 'Không phân biệt hoa/thường'"></p>
                        </div>
                    </template>
                </div>
            @elseif ($isCoding)
                <p class="text-xs text-slate-400">
                    Thông tin đang lưu của bài. Muốn đổi bộ test, giới hạn chạy hay tệp đề bài / lời giải / code mẫu thì sửa ở
                    khung bên trái rồi bấm "Lưu bài tập".
                </p>
            @else
            <p class="text-xs text-slate-400">
                Đề bài, test case và giới hạn chạy đọc thẳng từ gói ZIP — không sửa tay ở đây để
                tránh làm hỏng dữ liệu test case nhiều dòng. Muốn đổi đề/test case, xoá bài này
                rồi thêm lại bằng gói ZIP đã sửa.
            </p>
            @endif

            @if ($exerciseType === 'coding')
                <div class="text-[13px] space-y-2">
                    <div class="flex items-center justify-between"><span class="text-slate-500">Số test case</span><span class="font-medium text-slate-700">{{ $testCasesCount }}</span></div>
                    <div class="flex items-center justify-between"><span class="text-slate-500">Giới hạn thời gian</span><span class="font-medium text-slate-700">{{ $timeLimitMs }} ms</span></div>
                    <div class="flex items-center justify-between"><span class="text-slate-500">Giới hạn bộ nhớ</span><span class="font-medium text-slate-700">{{ $memoryLimitMb }} MB</span></div>
                </div>
            @elseif ($exerciseType === 'mcq' && ! $isManual)
                <div class="text-[13px] space-y-1.5">
                    @foreach (($config['options'] ?? []) as $i => $opt)
                        @php $isCorrect = in_array((int) $i, array_map('intval', $config['correct_options'] ?? []), true); @endphp
                        <div @class(['px-2.5 py-1.5 rounded-xl border text-slate-600', 'border-emerald-300 bg-emerald-50 text-emerald-700 font-medium' => $isCorrect, 'border-sky-100' => ! $isCorrect])>
                            {{ $isCorrect ? '✓ ' : '' }}{{ $opt }}
                        </div>
                    @endforeach
                </div>
            @elseif ($exerciseType === 'fill_blank' && ! $isManual)
                <div class="text-[13px] space-y-1.5">
                    <p class="text-slate-500">Đáp án chấp nhận:</p>
                    <p class="font-medium text-slate-700">{{ implode(', ', $config['accepted_answers'] ?? []) }}</p>
                    <p class="text-xs text-slate-400">
                        {{ ($config['case_sensitive'] ?? false) ? 'Phân biệt hoa/thường' : 'Không phân biệt hoa/thường' }}
                        @if ($config['remove_diacritics'] ?? false) · Không phân biệt dấu @endif
                    </p>
                </div>
            @elseif ($exerciseType === 'composite')
                <div class="text-[13px] space-y-3">
                    @foreach (($config['parts'] ?? []) as $part)
                        <div class="p-3 rounded-xl border border-sky-100">
                            <p class="font-medium text-slate-700 mb-1">Phần {{ strtoupper($part['code'] ?? '?') }} ({{ $part['points'] ?? 0 }} điểm) — {{ match ($part['response_type'] ?? '') { 'single_choice' => 'Trắc nghiệm', 'true_false' => 'Đúng/Sai', 'short_answer' => 'Trả lời ngắn', 'essay' => 'Tự luận', default => $part['response_type'] ?? '?' } }}</p>
                            @if (($part['response_type'] ?? '') === 'single_choice')
                                <p class="text-slate-500">Phương án: {{ implode(', ', $part['choices'] ?? []) }} · Đáp án đúng: <span class="font-medium text-emerald-700">{{ $part['correct_answer'] ?? '—' }}</span></p>
                            @elseif (($part['response_type'] ?? '') === 'true_false')
                                <p class="text-slate-500">Đáp án đúng: <span class="font-medium text-emerald-700">{{ ($part['correct_answer'] ?? false) ? 'Đúng' : 'Sai' }}</span></p>
                            @elseif (($part['response_type'] ?? '') === 'short_answer')
                                <p class="text-slate-500">Đáp án chấp nhận: <span class="font-medium text-emerald-700">{{ implode(', ', $part['accepted_answers'] ?? []) }}</span></p>
                            @else
                                <p class="text-xs text-sky-600">📨 Tự luận — ghi nhận bài làm, chưa có chấm tự động.</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            @if (! empty($zipAssets))
                <div class="pt-3 border-t border-slate-100">
                    <p class="text-xs font-medium text-slate-400 uppercase tracking-wide mb-2">Học liệu đính kèm (audio/ảnh...)</p>
                    <div class="space-y-2">
                        @foreach ($zipAssets as $asset)
                            @php $assetUrl = route('admin.products.exercises.asset', [$product->id, $exercise->id, $asset['id']]); @endphp
                            @if (($asset['kind'] ?? '') === 'audio')
                                <audio controls preload="none" class="w-full" src="{{ $assetUrl }}"></audio>
                            @elseif (($asset['kind'] ?? '') === 'image')
                                <img src="{{ $assetUrl }}" alt="{{ $asset['alt_text'] ?? '' }}" class="rounded-xl border border-sky-100">
                            @else
                                <a href="{{ $assetUrl }}" class="text-xs text-blue-600 font-medium">📎 {{ $asset['filename'] ?? $asset['kind'] }}</a>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif

            @if (! empty($zipAttachments))
                <div class="pt-3 border-t border-slate-100">
                    <p class="text-xs font-medium text-slate-400 uppercase tracking-wide mb-2">Tệp đính kèm (từ gói ZIP)</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($zipAttachments as $kind => $file)
                            <a href="{{ route('admin.products.exercises.attachment', [$product->id, $exercise->id, $kind]) }}"
                               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-sky-100 text-xs text-slate-600 hover:border-blue-200 hover:text-blue-600">
                                {{ match ($kind) { 'statement' => '📄 Đề bài', 'solution' => '📄 Lời giải', 'reference' => '💻 Code mẫu', default => $kind } }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
    @if ($isManual)
        @include('partials.rich-editor-assets')
    @endif
@endsection

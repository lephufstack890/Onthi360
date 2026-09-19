{{--
    SỬA 19/9 (2) — TÁCH RA TỪ resources/views/student/assessment/take.blade.php (lúc tách là
    bản chép nguyên văn khối @push('scripts') cũ).

    SỬA 19/9 (3) — THÊM recordAnswer()/recording/recordStatus cho nút "Ghi nhận bài làm" của
    phòng thi cuộc thi. Đây là phần BỔ SUNG THUẦN: màn làm bài thường không gọi tới nên hành vi
    của nó không đổi. save() giờ trả true/false (trước trả undefined) — cũng thuần bổ sung, mọi
    nơi gọi cũ đều bỏ qua giá trị trả về.

    Lý do tách: phòng thi CUỘC THI (student/competitions/exam.blade.php) dùng lại đúng bộ
    script này. Nếu copy thành bản thứ hai thì sớm muộn hai bản lệch nhau — sửa lỗi ở màn
    này, màn kia vẫn còn lỗi. Giờ cả hai view cùng @include partial này nên chỉ có MỘT bản.

    Hợp đồng với view gọi nó (đừng đổi nếu chưa sửa cả hai view):
      · Phải gọi trong @push('scripts').
      · View phải có x-data="examWorkspace({...})" với đủ các khoá: deadlineAt, serverNow,
        saveUrl, runUrl, questions, answers, codes, languages, firstId, lastId.
      · View phải có x-ref="examForm" (form nộp ẩn) và x-ref="rail" (dải số câu), cùng
        x-ref="hl<questionId>" cho mỗi câu lập trình (lớp tô màu cú pháp).
--}}
    <style>
        /* Nội dung đề do CKEditor lưu — cùng quy tắc với các trang khác đang dùng .rich-content. */
        .rich-content ul { list-style: disc; padding-left: 1.25rem; margin-bottom: .5rem; }
        .rich-content ol { list-style: decimal; padding-left: 1.25rem; margin-bottom: .5rem; }
        .rich-content p { margin-bottom: .5rem; }
        .no-scrollbar { scrollbar-width: none; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
    </style>

    @include('partials.pdf-fit-viewer')

    {{-- Bộ tô màu cú pháp + mã khởi tạo, dùng chung với màn luyện 1 bài. --}}
    @include('partials.code-editor-runtime')

    <script>

        function examWorkspace(config) {
            return {
                // ── Cấu hình từ server ──
                questions: config.questions || [],
                firstId: config.firstId,
                lastId: config.lastId,
                saveUrl: config.saveUrl,
                runUrl: config.runUrl,
                deadlineAt: config.deadlineAt ? new Date(config.deadlineAt).getTime() : null,
                // Bù lệch giờ máy học sinh vs máy chủ — GIỮ NGUYÊN cách tính của bản cũ. Đây chỉ
                // là hiển thị; chặn THẬT vẫn nằm ở server (AttemptService::isExpired()).
                clockOffsetMs: config.serverNow ? (new Date(config.serverNow).getTime() - Date.now()) : 0,

                // ── Trạng thái bài làm ──
                answers: Object.assign({}, config.answers),
                codes: Object.assign({}, config.codes),
                languages: Object.assign({}, config.languages),
                testInputs: {},
                // SỬA 18/9 (2) — kết quả lần chạy thử gần nhất, tách theo TỪNG CÂU: chuyển câu
                // rồi quay lại vẫn thấy đúng output của câu đó, không bị của câu khác đè lên.
                testOutputs: {},
                testStatus: {},
                testRunning: {},
                // SỬA 19/9 (3) — nút "Ghi nhận bài làm" (chỉ phòng thi cuộc thi dùng).
                recording: {},
                recordStatus: {},

                // ── Trạng thái giao diện ──
                activeId: config.firstId,
                activeTab: 'work',
                theme: 'light',
                remainingLabel: '',
                tone: 'normal',
                expired: false,
                submitting: false,
                confirmOpen: false,
                saving: false,
                timerId: null,
                inFlight: 0,
                // ── SỬA 19/9 (6) — CẢNH BÁO KHI RỜI PHÒNG THI ─────────────────────────────
                // Chỉ bật khi view truyền warnOnLeave: true (phòng thi CUỘC THI). Màn làm bài
                // thường không truyền -> false -> không đăng ký beforeunload, hành vi y như cũ.
                warnOnLeave: config.warnOnLeave === true,
                leaveOpen: false,
                leaveUrl: null,
                leaving: false,

                init() {
                    var self = this;
                    this.registerLeaveGuard();
                    this.questions.forEach(function (q) {
                        if (self.testInputs[q.id] === undefined) self.testInputs[q.id] = '';
                        if (self.testOutputs[q.id] === undefined) self.testOutputs[q.id] = 'Chưa chạy test';
                        if (self.testStatus[q.id] === undefined) self.testStatus[q.id] = '';
                        if (self.testRunning[q.id] === undefined) self.testRunning[q.id] = false;
                        if (self.recording[q.id] === undefined) self.recording[q.id] = false;
                        if (self.recordStatus[q.id] === undefined) self.recordStatus[q.id] = '';
                        if (q.kind === 'code' && !String(self.codes[q.id] || '').length) {
                            self.codes[q.id] = STARTER_CODE[self.languages[q.id]] || STARTER_CODE.cpp;
                        }
                    });

                    try {
                        if (window.localStorage.getItem('onthi360-exam-theme') === 'dark') this.setTheme('dark');
                    } catch (e) { /* trình duyệt chặn localStorage — cứ dùng nền sáng */ }

                    if (this.deadlineAt === null) return;
                    this.tick();
                    this.timerId = setInterval(function () { self.tick(); }, 1000);
                },


                /*
                 * SỬA 19/9 (6) — bấm X / "Thoát phòng thi": hỏi lại trước khi đi.
                 *
                 * Hết giờ hoặc đang nộp thì KHÔNG hỏi — lúc đó không còn gì để mất, chặn thêm
                 * một lớp hộp thoại chỉ làm thí sinh hoảng.
                 */
                askLeave(url) {
                    if (this.expired || this.submitting) {
                        this.leaving = true;
                        window.location.href = url;
                        return;
                    }

                    this.leaveUrl = url;
                    this.leaveOpen = true;
                },

                /** Đồng ý rời đi: bật cờ leaving để beforeunload không hỏi lại lần hai. */
                leaveNow() {
                    this.leaving = true;
                    this.leaveOpen = false;

                    if (this.leaveUrl) {
                        window.location.href = this.leaveUrl;
                    }
                },

                /*
                 * Đóng tab / F5 / nút Back — những lối ra mà nút bấm trong trang không chặn được.
                 * Trình duyệt hiện hộp thoại CỦA NÓ với câu chữ cố định, không đổi được; ở đây
                 * chỉ có thể bật/tắt. Bỏ qua khi đang nộp bài hoặc vừa bấm "Rời phòng thi", nếu
                 * không thì chính việc nộp bài cũng bị hỏi lại.
                 */
                registerLeaveGuard() {
                    if (!this.warnOnLeave) return;

                    var self = this;
                    window.addEventListener('beforeunload', function (event) {
                        if (self.expired || self.submitting || self.leaving) return;
                        event.preventDefault();
                        event.returnValue = '';
                    });
                },

                // ── Điều hướng câu ──
                currentKind() {
                    var q = this.questions.find((item) => item.id === this.activeId);
                    return q ? q.kind : 'choice';
                },
                setActive(id) {
                    if (this.expired || this.submitting) return;
                    this.activeId = id;
                    this.followActive();
                },
                goPrev() {
                    if (this.expired || this.submitting) return;
                    var i = this.questions.findIndex((q) => q.id === this.activeId);
                    if (i > 0) { this.activeId = this.questions[i - 1].id; this.followActive(); }
                },
                goNext() {
                    if (this.expired || this.submitting) return;
                    var i = this.questions.findIndex((q) => q.id === this.activeId);
                    if (i > -1 && i < this.questions.length - 1) { this.activeId = this.questions[i + 1].id; this.followActive(); }
                },
                // SỬA 18/9 — thay cho hàm cuộn dải cũ: đề nhiều câu thì dải số chỉ hiện được vài
                // viên, bấm mũi tên đi quá khung là viên đang xem khuất mất. Giờ dải TỰ CUỘN theo
                // câu đang xem, không phải kéo tay.
                followActive() {
                    var self = this;
                    this.$nextTick(function () {
                        var rail = self.$refs.rail;
                        if (!rail) return;
                        var pill = rail.querySelector('[data-rail-pill="' + self.activeId + '"]');
                        if (pill && typeof pill.scrollIntoView === 'function') {
                            pill.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                        }
                    });
                },

                // ── Nền sáng/tối (bản mẫu dùng useWorkspaceTheme, ở đây gọn lại đúng phần cần) ──
                setTheme(next) {
                    this.theme = next;
                    document.documentElement.classList.toggle('theme-dark', next === 'dark');
                },
                toggleTheme() {
                    this.setTheme(this.theme === 'dark' ? 'light' : 'dark');
                    try { window.localStorage.setItem('onthi360-exam-theme', this.theme); } catch (e) { /* bỏ qua */ }
                },

                // ── Tô màu + cuộn đồng bộ ──
                highlight(code, language) { return highlightCode(code, language, this.theme); },
                syncScroll(event, refName) {
                    var target = this.$refs[refName];
                    if (!target) return;
                    target.scrollTop = event.currentTarget.scrollTop;
                    target.scrollLeft = event.currentTarget.scrollLeft;
                },

                // ── Đếm đã trả lời ──
                isAnswered(id) {
                    var q = this.questions.find((item) => item.id === id);
                    if (q && q.kind === 'code') return String(this.codes[id] || '').trim().length > 0;
                    return String(this.answers[id] === undefined || this.answers[id] === null ? '' : this.answers[id]).trim().length > 0;
                },
                answeredCount() {
                    var self = this;
                    return this.questions.filter(function (q) { return self.isAnswered(q.id); }).length;
                },

                // ── Soạn mã ──
                onLanguageChange(id) {
                    // Đổi ngôn ngữ thì thay mã khởi tạo, ĐÚNG như bản mẫu — nhưng chỉ khi học
                    // sinh chưa viết gì khác mã khởi tạo, tránh xoá mất bài đang viết dở.
                    var current = String(this.codes[id] || '').trim();
                    var isStarter = current === '' || current === String(STARTER_CODE.cpp).trim() || current === String(STARTER_CODE.python).trim();
                    if (isStarter) this.codes[id] = STARTER_CODE[this.languages[id]] || STARTER_CODE.cpp;
                    this.onCode(id);
                },
                resetCode(id) {
                    this.codes[id] = STARTER_CODE[this.languages[id]] || STARTER_CODE.cpp;
                    this.onCode(id);
                },
                loadCodeFile(id, event) {
                    var self = this;
                    var file = event.target.files && event.target.files[0];
                    if (!file) return;
                    if (/\.py$/i.test(file.name)) self.languages[id] = 'python';
                    file.text().then(function (content) {
                        self.codes[id] = content;
                        self.onCode(id);
                    });
                    event.target.value = '';
                },
                onCode(id) {
                    this.save(id, { code_source: this.codes[id], language: this.languages[id] });
                },

                // ── Chạy thử (SỬA 18/9 (2)) ────────────────────────────────────────────────
                // Gửi mã + dữ liệu vào ô Input lên student.assessment.take.run, in stdout ra ô
                // Output của ĐÚNG câu đó. Không chấm điểm, không tính là một lần nộp, không đụng
                // tới bài làm đã lưu — chấm thật vẫn chỉ xảy ra lúc Nộp đề.
                async runTest(id) {
                    if (this.expired || this.testRunning[id]) return;

                    this.testRunning[id] = true;
                    this.testStatus[id] = '';
                    this.testOutputs[id] = 'Đang chạy…';

                    try {
                        var res = await fetch(this.runUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify({
                                question_id: id,
                                code_source: this.codes[id] ?? '',
                                language: this.languages[id] ?? 'cpp',
                                stdin: this.testInputs[id] ?? '',
                            }),
                        });

                        // KHÔNG gọi thẳng res.json(): 404/419/500 đều trả HTML, json() sẽ ném lỗi
                        // rồi rơi xuống catch -> báo "lỗi mạng" sai sự thật, người dùng đi kiểm tra
                        // wifi trong khi lỗi nằm ở máy chủ. Đọc mã HTTP trước.
                        if (!res.ok) throw new Error('HTTP ' + res.status);

                        var data = await res.json();

                        if (!data || !data.ok) {
                            this.testOutputs[id] = (data && data.message) ? data.message : 'Chạy thử thất bại.';
                            this.testStatus[id] = 'Không chạy được';
                            return;
                        }

                        // Ưu tiên hiện lỗi biên dịch/lỗi chạy — đó mới là cái cần đọc; stdout rỗng
                        // mà không nói gì thì người dùng tưởng nút hỏng.
                        var text = '';
                        if (data.compileOutput) text += 'Lỗi biên dịch:\n' + data.compileOutput + '\n';
                        if (data.stderr) text += 'Lỗi khi chạy:\n' + data.stderr + '\n';
                        if (data.output) text += data.output;
                        this.testOutputs[id] = text !== '' ? text : '(chương trình không in ra gì)';

                        var parts = [data.statusLabel || ''];
                        if (data.time) parts.push(data.time + 's');
                        if (data.memory) parts.push(Math.round(data.memory / 1024) + 'MB');
                        this.testStatus[id] = parts.filter(Boolean).join(' · ');
                    } catch (error) {
                        var reason = String((error && error.message) || '');

                        if (reason === 'HTTP 419') {
                            this.testOutputs[id] = 'Phiên làm việc đã hết hạn — tải lại trang rồi thử lại.';
                        } else if (reason === 'HTTP 404') {
                            this.testOutputs[id] = 'Máy chủ chưa nhận ra chức năng chạy thử (404) — báo quản trị viên nạp lại máy chủ sau khi cập nhật mã.';
                        } else if (reason.indexOf('HTTP ') === 0) {
                            this.testOutputs[id] = 'Máy chủ báo lỗi (' + reason + ') — báo quản trị viên xem storage/logs/laravel.log.';
                        } else {
                            this.testOutputs[id] = 'Không gửi được yêu cầu chạy thử — kiểm tra kết nối mạng rồi thử lại.';
                        }

                        this.testStatus[id] = reason !== '' ? reason : 'Không gửi được';
                    } finally {
                        this.testRunning[id] = false;
                    }
                },

                // ── Trắc nghiệm / điền đáp án ──
                onAnswer(id) {
                    var q = this.questions.find((item) => item.id === id);
                    var value = this.answers[id];
                    this.save(id, q && q.kind === 'fill' ? { text: value } : { selected_option: value });
                },

                // ── Tự lưu (GIỮ NGUYÊN giao thức cũ với student.assessment.take.save) ──
                // SỬA 19/9 (3): thêm GIÁ TRỊ TRẢ VỀ true/false để recordAnswer() biết đã lưu
                // được hay chưa mà báo cho thí sinh. Các nơi gọi cũ (onCode/onAnswer) không đọc
                // giá trị này nên hành vi của chúng không đổi.
                async save(questionId, payload) {
                    if (this.expired || this.submitting) return false;

                    this.inFlight++;
                    this.saving = true;
                    var ok = false;

                    try {
                        var res = await fetch(this.saveUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                            },
                            body: JSON.stringify({ answers: { [questionId]: payload } }),
                        });
                        var data = await res.json().catch(() => null);
                        if (data && data.expired) this.handleTimeUp(data.resultUrl);
                        // res.ok mới là "máy chủ đã nhận"; 419/500 vẫn tới được đây nên phải xét.
                        ok = res.ok === true;
                    } catch (e) {
                        // Mất mạng thoáng qua — không làm phiền học sinh giữa giờ thi; bài vẫn còn
                        // trên màn hình, lần sửa tiếp theo sẽ lưu lại.
                        ok = false;
                    } finally {
                        this.inFlight = Math.max(0, this.inFlight - 1);
                        this.saving = this.inFlight > 0;
                    }

                    return ok;
                },

                /*
                 * SỬA 19/9 (3) — nút "Ghi nhận bài làm" của phòng thi CUỘC THI.
                 *
                 * CỐ Ý chỉ LƯU chứ không chấm: trong cuộc thi, điểm và kết quả test chỉ được
                 * công bố sau khi ban tổ chức chấm xong (khác màn luyện tập vốn chấm ngay và in
                 * kết quả ra màn hình). Nút này tồn tại để thí sinh yên tâm là bài đã lên máy
                 * chủ — bình thường mỗi lần gõ đã tự lưu rồi, đây là cách bấm tay cho chắc.
                 */
                async recordAnswer(id) {
                    if (this.expired || this.submitting || this.recording[id]) return;

                    var q = this.questions.find(function (item) { return item.id === id; });
                    if (!q) return;

                    var payload;
                    if (q.kind === 'code') payload = { code_source: this.codes[id], language: this.languages[id] };
                    else if (q.kind === 'fill') payload = { text: this.answers[id] };
                    else payload = { selected_option: this.answers[id] };

                    this.recording[id] = true;
                    this.recordStatus[id] = 'Đang ghi nhận…';

                    var ok = await this.save(id, payload);

                    if (ok) {
                        var now = new Date();
                        var pad = function (n) { return String(n).padStart(2, '0'); };
                        this.recordStatus[id] = 'Đã ghi nhận lúc ' + pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds());
                    } else {
                        this.recordStatus[id] = 'Chưa ghi nhận được — kiểm tra mạng rồi bấm lại.';
                    }

                    this.recording[id] = false;
                },

                // ── Đồng hồ ──
                tick() {
                    var now = Date.now() + this.clockOffsetMs;
                    var remainingMs = this.deadlineAt - now;

                    if (remainingMs <= 0) {
                        this.remainingLabel = '00:00';
                        this.tone = 'danger';
                        if (!this.expired) this.handleTimeUp();
                        return;
                    }

                    var total = Math.floor(remainingMs / 1000);
                    var h = Math.floor(total / 3600);
                    var m = Math.floor((total % 3600) / 60);
                    var s = total % 60;
                    var pad = function (n) { return String(n).padStart(2, '0'); };
                    // Bản mẫu hiện dạng 01:27:42 — giữ đúng định dạng đó.
                    this.remainingLabel = pad(h) + ':' + pad(m) + ':' + pad(s);
                    this.tone = total <= 60 ? 'danger' : (total <= 300 ? 'warning' : 'normal');
                },

                // ── Nộp bài ──
                // Đổ trạng thái Alpine vào đúng các input ẩn của <form> rồi mới submit. Câu chưa
                // trả lời thì DISABLE input để trình duyệt không gửi lên — giữ nguyên hành vi cũ
                // (trước đây input của câu chưa trả lời đơn giản là không có giá trị), tránh tạo
                // ra bản ghi "đã trả lời" rỗng ở server.
                syncForm() {
                    var form = this.$refs.examForm;
                    if (!form) return;
                    var self = this;

                    var put = function (name, value) {
                        var el = form.querySelector('[name="' + name + '"]');
                        if (!el) return;
                        var text = (value === undefined || value === null) ? '' : String(value);
                        el.value = text;
                        el.disabled = text.trim() === '';
                    };

                    this.questions.forEach(function (q) {
                        if (q.kind === 'code') {
                            var code = self.codes[q.id];
                            put('answers[' + q.id + '][code_source]', code);
                            // Ngôn ngữ chỉ có nghĩa khi có mã — bám theo trạng thái của ô mã.
                            put('answers[' + q.id + '][language]', String(code || '').trim() === '' ? '' : self.languages[q.id]);
                        } else if (q.kind === 'fill') {
                            put('answers[' + q.id + '][text]', self.answers[q.id]);
                        } else {
                            put('answers[' + q.id + '][selected_option]', self.answers[q.id]);
                        }
                    });
                },

                handleTimeUp(resultUrl) {
                    this.expired = true;
                    // Hết giờ thì đóng mọi hộp thoại đang mở: lớp phủ "Đã hết giờ" phải là thứ
                    // duy nhất thí sinh nhìn thấy, không để hộp "Rời phòng thi?" đè lên trên.
                    this.leaveOpen = false;
                    this.confirmOpen = false;
                    clearInterval(this.timerId);

                    if (resultUrl) {
                        window.location.href = resultUrl;
                        return;
                    }

                    this.submitting = true;
                    this.syncForm();
                    this.$nextTick(() => this.$refs.examForm.submit());
                },

                doSubmit() {
                    this.submitting = true;
                    this.syncForm();
                    this.$nextTick(() => this.$refs.examForm.submit());
                },
            };
        }
    </script>

{{--
    SỬA 19/9 (5) — TÁCH RA TỪ resources/views/student/assessment/take-pdf.blade.php (lúc tách
    là bản chép nguyên văn khối @push('scripts') cũ), để phòng thi ĐỀ PDF của CUỘC THI
    (student/competitions/exam-pdf.blade.php) dùng chung một bộ script, không đẻ bản sao rồi
    lệch nhau về sau.

    Hợp đồng với view gọi nó:
      · Gọi trong @push('scripts').
      · View phải có x-data="pdfExamTake({ deadlineAt, serverNow, saveUrl,
        initialAnswerStatus, initialCodingStatus })" và x-ref="examForm".
      · Muốn dùng thanh chuyển câu thì mỗi thẻ câu gắn data-cau="<số câu>" (xem goTo()).
--}}
    <script>
        function pdfExamTake(config) {
            return {
                deadlineAt: config.deadlineAt ? new Date(config.deadlineAt).getTime() : null,
                clockOffsetMs: config.serverNow ? (new Date(config.serverNow).getTime() - Date.now()) : 0,
                saveUrl: config.saveUrl,
                answeredAnswerMap: { ...config.initialAnswerStatus },
                answeredCodingMap: { ...config.initialCodingStatus },
                /*
                 * Danh sách SỐ CÂU theo đúng thứ tự thẻ câu trên màn, ví dụ [1,2,3,4].
                 * Cố ý lưu cả danh sách chứ không chỉ "tổng số câu": số câu trong phiếu đáp án
                 * do người soạn đề đánh, có thể KHÔNG liên tục (vd 3, 7, 8). Nếu chuyển câu
                 * bằng phép cộng activeNo+1 thì sẽ nhảy vào số không tồn tại rồi đứng im.
                 * Màn take-pdf cũ không truyền -> mảng rỗng -> không dùng thanh chuyển câu.
                 */
                questionNos: config.questionNos || [],
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
                // SỬA 19/9 (5) — thanh chuyển câu của phòng thi đề PDF cuộc thi. BỔ SUNG THUẦN:
                // màn take-pdf cũ không dùng tới nên hành vi của nó không đổi.
                activeNo: 1,

                init() {
                    this.registerLeaveGuard();

                    /*
                     * Bắt đầu ở ĐÚNG câu đầu tiên có thật. Mặc định activeNo = 1, nhưng phiếu
                     * đáp án có thể đánh số bắt đầu từ 3 (người soạn tự đánh) — khi đó số 1
                     * không tồn tại, ô nào trên thanh chuyển câu cũng không sáng và nút "Câu
                     * tiếp" bấm lần đầu sẽ nhảy sai chỗ.
                     */
                    if (this.questionNos.length > 0 && this.questionNos.indexOf(this.activeNo) === -1) {
                        this.activeNo = this.questionNos[0];
                    }

                    if (this.deadlineAt === null) {
                        return;
                    }
                    this.tick();
                    this.timerId = setInterval(() => this.tick(), 1000);
                },

                tick() {
                    const now = Date.now() + this.clockOffsetMs;
                    const remainingMs = this.deadlineAt - now;

                    if (remainingMs <= 0) {
                        this.remainingLabel = '0:00';
                        this.tone = 'danger';
                        if (!this.expired) {
                            this.handleTimeUp();
                        }
                        return;
                    }

                    const totalSeconds = Math.floor(remainingMs / 1000);
                    const h = Math.floor(totalSeconds / 3600);
                    const m = Math.floor((totalSeconds % 3600) / 60);
                    const s = totalSeconds % 60;
                    this.remainingLabel = h > 0
                        ? `${h}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
                        : `${m}:${String(s).padStart(2, '0')}`;
                    this.tone = totalSeconds <= 60 ? 'danger' : (totalSeconds <= 300 ? 'warning' : 'normal');
                },

                /*
                 * SỬA 19/9 (5) — nhảy tới câu số N: cuộn thẻ câu đó vào giữa khung bên phải.
                 * CỐ Ý cuộn chứ không ẩn/hiện: đề PDF là đề "nhìn bên trái, tô bên phải" — thí
                 * sinh thường đối chiếu vài câu cùng lúc, ẩn bớt câu là làm khó họ.
                 */

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

                goTo(no) {
                    this.activeNo = no;

                    var card = document.querySelector('[data-cau="' + no + '"]');
                    if (card && typeof card.scrollIntoView === 'function') {
                        card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                },

                /** Vị trí của câu đang xem trong danh sách (−1 nếu không có). */
                activeIndex() {
                    return this.questionNos.indexOf(this.activeNo);
                },

                /** Lùi/tiến MỘT Ô trong danh sách, không phải cộng trừ số câu. */
                step(delta) {
                    if (this.questionNos.length === 0) return;
                    var i = this.activeIndex();
                    if (i === -1) i = 0;
                    else i = i + delta;
                    if (i < 0 || i > this.questionNos.length - 1) return;
                    this.goTo(this.questionNos[i]);
                },

                isFirst() { return this.questionNos.length === 0 || this.activeIndex() <= 0; },
                isLast() { return this.questionNos.length === 0 || this.activeIndex() === this.questionNos.length - 1; },

                answeredCount() {
                    return Object.values(this.answeredAnswerMap).filter(Boolean).length
                        + Object.values(this.answeredCodingMap).filter(Boolean).length;
                },

                onAnswerKey(answerKeyId, payload, isAnswered) {
                    this.answeredAnswerMap[answerKeyId] = isAnswered;
                    this.save({ answer_keys: { [answerKeyId]: payload } });
                },

                onCodingItem(codingItemId, payload, isAnswered) {
                    this.answeredCodingMap[codingItemId] = isAnswered;
                    this.save({ coding_items: { [codingItemId]: payload } });
                },

                async save(partial) {
                    if (this.expired || this.submitting) {
                        return;
                    }

                    this.inFlight++;
                    this.saving = true;

                    try {
                        const res = await fetch(this.saveUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                            },
                            body: JSON.stringify({ answers: partial }),
                        });
                        const data = await res.json().catch(() => null);

                        if (data && data.expired) {
                            this.handleTimeUp(data.resultUrl);
                        }
                    } catch (e) {
                        // Mất mạng thoáng qua — không làm phiền học sinh giữa giờ thi bằng lỗi
                        // đỏ; câu trả lời vẫn còn nguyên trên form, lần sửa tiếp theo sẽ lưu lại.
                    } finally {
                        this.inFlight = Math.max(0, this.inFlight - 1);
                        this.saving = this.inFlight > 0;
                    }
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
                    this.$nextTick(() => this.$refs.examForm.submit());
                },

                doSubmit() {
                    this.submitting = true;
                    this.$nextTick(() => this.$refs.examForm.submit());
                },
            };
        }
    </script>

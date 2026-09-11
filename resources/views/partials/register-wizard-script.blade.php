{{-- Alpine cho luồng đăng ký 3 bước — chuyển đúng useState/useEffect của
     education-main/src/components/AccessCenterModal.jsx (view "register").

     Khác bản mẫu: bước 2 gọi máy chủ thật (gửi mã / gửi lại / xác minh) thay vì chỉ đổi state,
     và chỉ khi xác minh đúng mới có token để bước 3 tạo tài khoản. --}}
<script>
    function onthiRegisterWizard(config) {
        return {
            step: config.initialStep,
            method: 'email',
            showPassword: false,
            busy: false,

            verificationEnabled: config.verificationEnabled,
            verificationToken: '',
            maskedEmail: '',
            digits: ['', '', '', '', '', ''],
            codeError: '',
            cooldown: 0,
            cooldownTimer: null,

            fieldErrors: {},

            form: {
                name: config.oldName,
                email: config.oldEmail,
                phone: config.oldPhone,
                password: '',
                passwordConfirmation: '',
                terms: false,
                role: config.oldRole,
            },

            destroy() {
                if (this.cooldownTimer) clearInterval(this.cooldownTimer);
            },

            get code() { return this.digits.join('').trim(); },

            get countdownLabel() {
                const s = Math.max(this.cooldown, 0);
                return '00:' + String(s).padStart(2, '0');
            },

            // ── Bước 1: kiểm tra tại chỗ rồi mới gọi máy chủ ──
            validateStep1() {
                const e = {};
                if (!this.form.name.trim()) e.name = 'Vui lòng nhập họ và tên.';
                if (!this.form.email.trim()) {
                    e.email = 'Vui lòng nhập email.';
                } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.form.email.trim())) {
                    e.email = 'Email chưa đúng định dạng.';
                }
                if (this.method === 'phone' && this.form.phone.trim() && !/^[0-9+\s.\-()]{8,20}$/.test(this.form.phone.trim())) {
                    e.phone = 'Số điện thoại chưa đúng định dạng.';
                }
                if (!this.form.password) {
                    e.password = 'Vui lòng nhập mật khẩu.';
                } else if (this.form.password.length < 8) {
                    e.password = 'Mật khẩu cần tối thiểu 8 ký tự.';
                }
                if (this.form.passwordConfirmation !== this.form.password) {
                    e.passwordConfirmation = 'Hai mật khẩu chưa khớp nhau.';
                }
                if (!this.form.terms) e.terms = 'Bạn cần đồng ý với điều khoản để tiếp tục.';

                this.fieldErrors = e;
                return Object.keys(e).length === 0;
            },

            async goToStep2() {
                if (!this.validateStep1()) return;

                // Chưa bật gửi thư thì không gọi máy chủ, đi thẳng sang bước 2 (màn thông báo).
                if (!this.verificationEnabled) {
                    this.step = 2;
                    return;
                }

                this.busy = true;
                this.codeError = '';
                try {
                    const res = await this.post(config.sendCodeUrl, {
                        name: this.form.name.trim(),
                        email: this.form.email.trim(),
                        phone: this.form.phone.trim() || null,
                        password: this.form.password,
                    });

                    if (!res.ok) {
                        this.applyServerErrors(res.data);
                        return;
                    }

                    this.maskedEmail = res.data.maskedEmail || this.form.email.trim();
                    this.digits = ['', '', '', '', '', ''];
                    this.step = 2;
                    this.startCooldown();
                    this.$nextTick(() => this.focusDigit(0));
                } finally {
                    this.busy = false;
                }
            },

            // ── Bước 2: ô nhập 6 chữ số ──
            focusDigit(i) {
                document.querySelector('[data-index="' + i + '"]')?.focus();
            },

            onDigitInput(event, i) {
                const v = (event.target.value || '').replace(/\D/g, '').slice(-1);
                this.digits[i] = v;
                event.target.value = v;
                this.codeError = '';
                if (v && i < 5) this.focusDigit(i + 1);
            },

            onDigitBackspace(event, i) {
                if (!this.digits[i] && i > 0) {
                    this.digits[i - 1] = '';
                    this.focusDigit(i - 1);
                }
            },

            // Dán cả mã 6 số từ hộp thư vào là điền đủ 6 ô.
            onDigitPaste(event) {
                const text = (event.clipboardData || window.clipboardData).getData('text') || '';
                const nums = text.replace(/\D/g, '').slice(0, 6).split('');
                for (let i = 0; i < 6; i++) this.digits[i] = nums[i] || '';
                this.$nextTick(() => {
                    document.querySelectorAll('[data-index]').forEach((el, i) => { el.value = this.digits[i] || ''; });
                    this.focusDigit(Math.min(nums.length, 5));
                });
            },

            startCooldown() {
                this.cooldown = config.resendCooldown;
                if (this.cooldownTimer) clearInterval(this.cooldownTimer);
                this.cooldownTimer = setInterval(() => {
                    this.cooldown -= 1;
                    if (this.cooldown <= 0) { this.cooldown = 0; clearInterval(this.cooldownTimer); }
                }, 1000);
            },

            async resendCode() {
                if (this.cooldown > 0 || this.busy) return;
                this.busy = true;
                this.codeError = '';
                try {
                    const res = await this.post(config.resendCodeUrl, { email: this.form.email.trim() });
                    if (!res.ok) {
                        this.codeError = res.data.message || 'Không gửi lại được mã. Vui lòng thử lại.';
                        return;
                    }
                    this.digits = ['', '', '', '', '', ''];
                    this.$nextTick(() => {
                        document.querySelectorAll('[data-index]').forEach((el) => { el.value = ''; });
                        this.focusDigit(0);
                    });
                    this.startCooldown();
                } finally {
                    this.busy = false;
                }
            },

            async verifyCode() {
                if (this.code.length !== 6 || this.busy) return;
                this.busy = true;
                this.codeError = '';
                try {
                    const res = await this.post(config.verifyCodeUrl, { email: this.form.email.trim(), code: this.code });
                    if (!res.ok) {
                        this.codeError = res.data.message || 'Mã xác minh không đúng.';
                        return;
                    }
                    this.verificationToken = res.data.token;
                    this.step = 3;
                } finally {
                    this.busy = false;
                }
            },

            // ── Tiện ích ──
            async post(url, body) {
                const token = document.querySelector('meta[name="csrf-token"]')?.content
                    || document.querySelector('input[name="_token"]')?.value;
                try {
                    const r = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': token || '',
                        },
                        body: JSON.stringify(body),
                    });
                    const data = await r.json().catch(() => ({}));
                    return { ok: r.ok, status: r.status, data };
                } catch (err) {
                    return { ok: false, status: 0, data: { message: 'Không kết nối được máy chủ. Vui lòng thử lại.' } };
                }
            },

            // Máy chủ trả lỗi kiểm tra (422) thì gắn vào đúng ô, không đá người dùng ra màn khác.
            applyServerErrors(data) {
                const errs = data.errors || {};
                const map = {};
                if (errs.name) map.name = errs.name[0];
                if (errs.email) map.email = errs.email[0];
                if (errs.phone) map.phone = errs.phone[0];
                if (errs.password) map.password = errs.password[0];
                this.fieldErrors = map;
                if (Object.keys(map).length === 0) {
                    this.fieldErrors = { email: data.message || 'Không gửi được mã xác minh. Vui lòng thử lại.' };
                }
            },
        };
    }
</script>

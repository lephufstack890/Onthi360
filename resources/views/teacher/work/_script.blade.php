@once
<script>
    /* Phần dùng chung của "Bài & đề" và "Kết quả & nhận xét" (khu Giáo viên): lọc, phân trang 10 dòng/trang, mở chi tiết,
       lưu nhận xét. `extra` được trộn thêm (form giao bài ở trang Bài & đề). */
    window.twWorkList = function (c, extra) {
        var labels = { unsubmitted: 'Chưa nộp', overdue: 'Quá hạn', pending: 'Chờ chấm', completed: 'Đã có kết quả' };
        var rows = c.rows.map(function (r) { r.fb = r.feedback || ''; r.fbBusy = false; r.fbMsg = ''; r.fbErr = ''; return r; });
        var base = window.swPager(0, 10, {
            rows: rows, urls: c.urls, stateLabels: labels, stateKeys: c.stateKeys || ['unsubmitted', 'overdue', 'pending', 'completed'],
            emptyFiltered: 'Chưa có lượt giao phù hợp.', emptyAll: 'Chưa có dữ liệu.',
            kind: 'all', status: c.initialStatus || 'all', query: '', open: null, assigning: false,
            get filtered() {
                var self = this, q = this.query.trim().toLowerCase();
                return this.rows.filter(function (r) {
                    return (self.kind === 'all' || r.type === self.kind) && (self.status === 'all' || r.status === self.status)
                        && (!q || (r.title + ' ' + r.account + ' ' + r.student + ' ' + r.code).toLowerCase().indexOf(q) !== -1);
                });
            },
            get total() { return this.filtered.length; },
            get visible() { return this.filtered.slice((this.page - 1) * this.size, this.page * this.size); },
            reset: function () { this.open = null; this.page = 1; },
            submitted: function (r) { return r.status === 'completed' || r.status === 'pending'; },
            isOpen: function (r, view) { return !!(this.open && this.open.id === r.id && this.open.view === view); },
            toggle: function (r, view) { this.open = this.isOpen(r, view) ? null : { id: r.id, view: view }; },
            resultClass: function (r) { return r.result === 'Đúng toàn bộ' ? 'tw-result-ac' : (r.result === 'Đúng một phần' ? 'tw-result-partial' : 'tw-result-wa'); },
            csrf: function () { var m = document.querySelector('meta[name="csrf-token"]'); return m ? m.content : ''; },
            post: function (url, body) {
                return fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf(), 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify(body) })
                    .then(function (res) { return res.json().catch(function () { return {}; }).then(function (j) { return { ok: res.ok, status: res.status, json: j }; }); });
            },
            firstError: function (r) {
                var j = r.json || {};
                if (j.errors) { var k = Object.keys(j.errors)[0]; return j.errors[k][0]; }
                if (r.status === 419) { return 'Phiên làm việc đã hết hạn. Hãy tải lại trang rồi thử lại.'; }
                if (r.status === 403) { return j.message || 'Bạn không có quyền thực hiện thao tác này.'; }
                return j.message || 'Chưa lưu được. Vui lòng thử lại.';
            },
            saveFeedback: async function (r) {
                r.fbMsg = ''; r.fbErr = ''; r.fbBusy = true;
                try {
                    var res = await this.post(this.urls.feedback + '/' + r.id + '/nhan-xet', { feedback: r.fb });
                    if (res.ok && res.json.ok) { r.feedback = res.json.feedback; r.feedbackAt = res.json.feedbackAt; r.fbMsg = 'Đã lưu nhận xét.'; }
                    else { r.fbErr = this.firstError(res); }
                } catch (e) { r.fbErr = 'Không kết nối được máy chủ. Vui lòng thử lại.'; }
                r.fbBusy = false;
            },
        });
        return extra ? Object.defineProperties(base, Object.getOwnPropertyDescriptors(extra)) : base;
    };
</script>
@endonce

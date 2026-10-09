{{-- Alpine cho trang Tài liệu — chuyển đúng useState/useMemo của education-main/src/components/MaterialsPage.jsx
     (+ MaterialFilters, utils/materialFilters.js, utils/materialLibrary.js) sang Alpine.

     SỬA 9/10 — dựng lại theo bản mẫu mới: bộ lọc (thể loại, độ khó, giá, quyền sử dụng, sắp xếp, tìm không dấu),
     các "không gian" theo vai trò (Kho tài liệu / Tài liệu của tôi / Tài liệu được giao / Tài liệu đã giao),
     phân trang 8 thẻ (6 dòng với danh sách giao), hộp chi tiết giá & quyền sử dụng, popup Giao tài liệu.
     Dữ liệu nạp một lần từ MaterialService::indexData(); lọc/sắp xếp/phân trang chạy ngay trên trình duyệt. --}}
<script>
    function onthiMaterialsPage(config) {
        var CATEGORY_ORDER = ['books', 'topics', 'exams'];
        var PAGE_CARD = 8;
        var PAGE_ASSIGN = 6;

        // Chuẩn hoá để tìm KHÔNG DẤU (bản mẫu: normalizeMaterialSearch).
        function norm(value) {
            return String(value || '').normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[đĐ]/g, 'd').toLowerCase().trim();
        }

        return {
            rows: (config.rows || []).map(function (r, i) { r._i = i; return r; }),
            scope: config.scope || {},
            loggedIn: !!config.loggedIn,
            role: (config.scope && config.scope.role) || 'guest',
            assignedRows: config.assignedRows || [],
            managedRows: config.managedRows || [],
            activateHref: config.activateHref,
            loginHref: config.loginHref,
            assignUrl: config.assignUrl || '',
            assignSearchUrl: config.assignSearchUrl || '',
            csrf: config.csrf || '',
            accessDays: config.accessDays || [7, 30, 90, 365],

            scopeTab: 'catalog',
            category: 'all',
            query: '',
            difficulty: 'all',
            price: 'all',
            access: 'all',
            status: 'all',
            sort: 'default',
            pageIndex: 1,
            selected: null,

            // Trạng thái popup "Giao tài liệu". Các khoá students/error/open do ô chọn học sinh dùng chung đọc-ghi.
            assign: { open: false, id: 0, title: '', students: [], deadline: '', accessDays: '30', note: '', error: '', saving: false, saved: null },

            categories: [
                { id: 'all', label: 'Tất cả thể loại' },
                { id: 'books', label: 'Sách' },
                { id: 'topics', label: 'Chuyên đề' },
                { id: 'exams', label: 'Bộ đề' },
            ],
            difficultyLabels: ['Cơ bản', 'Dễ', 'Trung bình', 'Khó', 'Nâng cao'],

            init() {
                var self = this;

                // ?tab= cũ (sách/chuyên đề/bộ đề) và ?scope= mới.
                if (this.categories.some(function (c) { return c.id === config.category; })) { this.category = config.category; }
                if (config.scope_tab) { this.scopeTab = config.scope_tab; }
                this.scopeTab = this.activeScope;

                // Bản mẫu quay về trang 1 mỗi khi đổi bộ lọc / từ khoá / tab.
                ['query', 'category', 'difficulty', 'price', 'access', 'status', 'sort', 'scopeTab'].forEach(function (key) {
                    self.$watch(key, function () { self.pageIndex = 1; self.syncUrl(); });
                });
                // "Hạn đọc gần nhất" chỉ có ở các danh sách lượt giao; rời khỏi đó thì về sắp xếp mặc định.
                this.$watch('scopeTab', function () { if (!self.isAssignmentView && self.sort === 'deadline') { self.sort = 'default'; } });
            },

            // ── Quyền theo vai trò ──
            get canManage() { return !!this.scope.canManage; },
            get isAdmin() { return this.role === 'admin'; },
            get canFilterAccess() { return this.loggedIn && this.activeScope !== 'managed'; },

            get byId() {
                var map = {};
                this.rows.forEach(function (r) { map[r.id] = r; });
                return map;
            },

            // Không gian đang xem; rơi về Kho tài liệu nếu vai trò hiện tại không có không gian đó.
            get activeScope() {
                var t = this.scopeTab;
                if (t === 'mine' && !this.scope.hasLibrary) { return 'catalog'; }
                if (t === 'assigned' && !this.scope.canViewAssigned) { return 'catalog'; }
                if (t === 'managed' && !this.scope.canManage) { return 'catalog'; }
                return ['catalog', 'mine', 'assigned', 'managed'].indexOf(t) === -1 ? 'catalog' : t;
            },
            get isAssignmentView() { return this.activeScope === 'assigned' || this.activeScope === 'managed'; },

            get tabs() {
                var self = this;
                var inCatalog = this.rows.filter(function (r) { return r.inCatalog; });
                var list = [{ id: 'catalog', label: 'Kho tài liệu', icon: 'book', count: inCatalog.length }];
                if (this.scope.hasLibrary) {
                    list.push({ id: 'mine', label: 'Tài liệu của tôi', icon: 'key', count: inCatalog.filter(function (r) { return r.owned || r.expired; }).length });
                }
                if (this.scope.canViewAssigned) {
                    list.push({ id: 'assigned', label: 'Tài liệu được giao', icon: 'file', count: this.assignedRows.filter(function (a) { return self.byId[a.productId]; }).length });
                }
                if (this.scope.canManage) {
                    list.push({ id: 'managed', label: 'Tài liệu đã giao', icon: 'send', count: this.managedRows.filter(function (a) { return self.byId[a.productId]; }).length });
                }
                return list;
            },

            get hasFilters() {
                return !!this.query.trim() || this.category !== 'all' || this.difficulty !== 'all' || this.price !== 'all'
                    || (this.canFilterAccess && this.access !== 'all') || (this.isAssignmentView && this.status !== 'all');
            },
            get canReset() { return this.hasFilters || this.sort !== 'default'; },

            resetFilters() {
                this.query = ''; this.category = 'all'; this.difficulty = 'all'; this.price = 'all';
                this.access = 'all'; this.status = 'all'; this.sort = 'default';
            },

            setScope(id) { this.scopeTab = id; this.status = 'all'; },

            // Mũi tên trái/phải/Home/End chuyển tab như bản mẫu.
            tabKey(event, index) {
                var keys = ['ArrowLeft', 'ArrowRight', 'Home', 'End'];
                if (keys.indexOf(event.key) === -1) { return; }
                event.preventDefault();
                var n = this.tabs.length;
                var next = event.key === 'Home' ? 0 : event.key === 'End' ? n - 1 : (index + (event.key === 'ArrowRight' ? 1 : -1) + n) % n;
                this.setScope(this.tabs[next].id);
                var el = document.getElementById('mp-tab-' + this.tabs[next].id);
                if (el) { el.focus(); }
            },

            // ── Lọc ──
            matches(item, extra) {
                var q = norm(this.query);
                if (q) {
                    var hay = item.search + ' ' + norm(extra || '');
                    if (!q.split(/\s+/).every(function (w) { return hay.indexOf(w) !== -1; })) { return false; }
                }
                if (this.category !== 'all' && item.category !== this.category) { return false; }
                if (this.difficulty !== 'all' && item.difficultyLevel !== Number(this.difficulty)) { return false; }
                var cost = item.priceValue || 0;
                if (this.price === 'under100' && cost >= 100000) { return false; }
                if (this.price === '100to200' && (cost < 100000 || cost > 200000)) { return false; }
                if (this.price === 'over200' && cost <= 200000) { return false; }
                if (this.canFilterAccess) {
                    if (this.access === 'active' && !item.owned) { return false; }
                    if (this.access === 'expired' && !item.expired) { return false; }
                    if (this.access === 'locked' && (item.owned || item.expired)) { return false; }
                }
                return true;
            },

            sortEntries(list, assigned) {
                var sort = (!assigned && this.sort === 'deadline') ? 'default' : this.sort;
                var titleCmp = function (a, b) { return a.title.localeCompare(b.title, 'vi', { numeric: true, sensitivity: 'base' }); };
                var catIdx = function (id) { return CATEGORY_ORDER.indexOf(id); };
                return list.slice().sort(function (l, r) {
                    var a = l.item || l;
                    var b = r.item || r;
                    var d = 0;
                    switch (sort) {
                        case 'difficulty-asc': d = (a.difficultyLevel || 0) - (b.difficultyLevel || 0); break;
                        case 'difficulty-desc': d = (b.difficultyLevel || 0) - (a.difficultyLevel || 0); break;
                        case 'price-asc': d = (a.priceValue || 0) - (b.priceValue || 0); break;
                        case 'price-desc': d = (b.priceValue || 0) - (a.priceValue || 0); break;
                        case 'rating': d = (b.average || 0) - (a.average || 0) || (b.count || 0) - (a.count || 0); break;
                        case 'category': d = catIdx(a.category) - catIdx(b.category); break;
                        case 'title': return titleCmp(a, b);
                        case 'deadline': d = l.deadlineTs - r.deadlineTs; break;
                        default:
                            return assigned ? (r.assignedTs - l.assignedTs) : ((catIdx(a.category) - catIdx(b.category)) || (a._i - b._i));
                    }
                    return d || titleCmp(a, b);
                });
            },

            // Danh sách thẻ (Kho tài liệu / Tài liệu của tôi) sau lọc + sắp xếp.
            get cardEntries() {
                var self = this;
                var mine = this.activeScope === 'mine';
                var list = this.rows.filter(function (r) {
                    return r.inCatalog && (!mine || r.owned || r.expired) && self.matches(r);
                });
                return this.sortEntries(list, false);
            },

            // Danh sách dòng giao (được giao / đã giao) sau lọc + sắp xếp.
            get assignEntries() {
                if (!this.isAssignmentView) { return []; }
                var self = this;
                var source = this.activeScope === 'managed' ? this.managedRows : this.assignedRows;
                var list = [];
                source.forEach(function (a) {
                    var item = self.byId[a.productId];
                    if (!item) { return; }
                    var extra = (a.studentName || '') + ' ' + (a.account || '') + ' ' + (a.teacher || '') + ' ' + (a.note || '');
                    if (!self.matches(item, extra)) { return; }
                    if (self.status !== 'all' && a.status !== self.status) { return; }
                    list.push(Object.assign({}, a, { item: item }));
                });
                return this.sortEntries(list, true);
            },

            get entries() { return this.isAssignmentView ? this.assignEntries : this.cardEntries; },
            get total() { return this.entries.length; },
            get pageSize() { return this.isAssignmentView ? PAGE_ASSIGN : PAGE_CARD; },
            get totalPages() { return Math.max(1, Math.ceil(this.total / this.pageSize)); },
            get page() { return Math.min(this.pageIndex, this.totalPages); },
            get pageEntries() { var start = (this.page - 1) * this.pageSize; return this.entries.slice(start, start + this.pageSize); },

            // id các thẻ hiển thị (thẻ dựng sẵn ở máy chủ cho SEO, ẩn/hiện bằng x-show) theo thứ tự đã sắp.
            get visibleIds() { return this.isAssignmentView ? [] : this.pageEntries.map(function (r) { return r.id; }); },

            get panelTitle() {
                var id = this.activeScope;
                var t = this.tabs.filter(function (x) { return x.id === id; })[0];
                return t ? t.label : '';
            },

            get emptyTitle() {
                if (this.hasFilters) { return 'Không có tài liệu phù hợp'; }
                return { managed: 'Chưa có tài liệu đã giao', assigned: 'Bạn chưa được giao tài liệu', mine: 'Thư viện của bạn đang trống' }[this.activeScope] || 'Không có tài liệu';
            },
            get emptyHint() {
                return {
                    managed: 'Chọn tài liệu trong kho, bấm Giao tài liệu rồi chọn học sinh, hạn đọc và thời hạn sử dụng.',
                    assigned: 'Tài liệu do giáo viên hoặc quản trị giao cho tài khoản của bạn sẽ xuất hiện tại đây.',
                }[this.activeScope] || 'Thử thay đổi bộ lọc hoặc chọn tài liệu trong kho để bắt đầu.';
            },

            // Đồng bộ ?scope= & ?category= lên thanh địa chỉ (tải lại / gửi link vẫn đúng chỗ). Lỗi thì bỏ qua.
            syncUrl() {
                try {
                    var url = new URL(window.location.href);
                    ['tab', 'scope', 'category'].forEach(function (k) { url.searchParams.delete(k); });
                    if (this.activeScope !== 'catalog') { url.searchParams.set('scope', this.activeScope); }
                    if (this.category !== 'all') { url.searchParams.set('category', this.category); }
                    window.history.replaceState(null, '', url.toString());
                } catch (e) { /* bỏ qua */ }
            },

            // ── Hộp chi tiết giá & quyền sử dụng ──
            openDetail(id) { this.selected = this.byId[id] || null; },
            closeDetail() { this.selected = null; },
            statusClass(s) { return 'is-' + s; },

            // Số sao tô (0..100%) cho ngôi sao thứ i của điểm đánh giá lẻ (4,3 = 4 sao đầy + 30% sao thứ 5).
            starFill(rating, i) { return Math.max(0, Math.min(100, ((rating || 0) - i) * 100)); },
            fmtRating(v) { return v === null || v === undefined ? '' : Number(v).toFixed(1).replace('.', ','); },

            // ── Popup Giao tài liệu ──
            get assignLabel() { return 'Giao tài liệu'; },

            nowLocal() {
                var d = new Date(Date.now() - new Date().getTimezoneOffset() * 60000);
                return d.toISOString().slice(0, 16);
            },

            openAssign(id) {
                if (!this.canManage) { return; }
                var item = this.byId[id];
                if (!item) { return; }
                this.selected = null;
                this.assign = { open: true, id: id, title: item.title, students: [], deadline: '', accessDays: '30', note: '', error: '', saving: false, saved: null };
            },

            closeAssign() {
                var wasSaved = !!this.assign.saved;
                this.assign.open = false;
                // Giao xong thì tải lại để danh sách "Tài liệu đã giao" cập nhật số liệu mới.
                if (wasSaved) { window.location.reload(); }
            },

            async submitAssign() {
                var a = this.assign;
                if (a.saving || a.saved) { return; }

                if (!a.students.length) { a.error = 'Chọn ít nhất 1 học sinh.'; return; }
                var deadlineMs = new Date(a.deadline).getTime();
                if (!a.deadline || !isFinite(deadlineMs)) { a.error = 'Vui lòng chọn ngày và giờ hạn đọc hợp lệ.'; return; }
                if (deadlineMs <= Date.now()) { a.error = 'Hạn đọc phải ở sau thời điểm hiện tại.'; return; }
                if (deadlineMs > Date.now() + Number(a.accessDays) * 86400000) { a.error = 'Hạn đọc phải nằm trong thời hạn được cấp quyền.'; return; }

                a.saving = true;
                a.error = '';

                try {
                    var res = await fetch(this.assignUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': this.csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({
                            product_id: a.id,
                            student_ids: a.students.map(function (s) { return s.id; }),
                            deadline: a.deadline,
                            access_days: Number(a.accessDays),
                            note: a.note,
                        }),
                    });
                    var data = await res.json().catch(function () { return {}; });

                    if (res.ok && data.ok) {
                        a.saved = data;
                    } else if (res.status === 422 && data.errors) {
                        var first = Object.values(data.errors)[0];
                        a.error = Array.isArray(first) ? first[0] : 'Dữ liệu chưa hợp lệ.';
                    } else if (res.status === 419) {
                        a.error = 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang rồi thử lại.';
                    } else if (res.status === 401 || res.status === 403) {
                        a.error = 'Bạn không có quyền giao nội dung này.';
                    } else {
                        a.error = 'Chưa lưu được lượt giao. Vui lòng thử lại.';
                    }
                } catch (e) {
                    a.error = 'Chưa lưu được lượt giao. Vui lòng kiểm tra kết nối và thử lại.';
                } finally {
                    a.saving = false;
                }
            },
        };
    }
</script>

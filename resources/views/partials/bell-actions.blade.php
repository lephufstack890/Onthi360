{{-- SỬA 4/11 (khách: "thêm nút đã đọc từng cái và đã đọc tất cả; đọc xong thì không hiển thị ngoài này nữa") — dùng chung cho
     chuông thanh trên trang công khai (partials.nav-public), chuông khu làm việc (partials.admin-notifications-bell) và tab
     "Thông báo" trang chủ (welcome). Mỗi thông báo mang data-bell-id; bấm "Đã đọc" ở bất kỳ chỗ nào thì gỡ thông báo đó ở MỌI
     chỗ trên trang, rồi báo sự kiện oi-bell-changed để các khối cập nhật số đếm / dòng "đã xem hết". Chỉ in 1 lần mỗi trang. --}}
@once
    <style>
        .bel-item { position: relative; }
        .bel-item > a { display: block; padding-right: 30px !important; }
        .bel-x { position: absolute; top: 6px; right: 6px; display: grid; place-items: center; width: 24px; height: 24px; border-radius: 8px; border: 1px solid #D5E6EF; background: #fff; color: #2D7FA3; cursor: pointer; transition: background .15s, color .15s, border-color .15s; }
        .bel-x:hover { background: #E6F4EA; border-color: #A7D7B8; color: #1F7A55; }
        .bel-x svg { width: 13px; height: 13px; }
        .bel-all { display: inline-flex; align-items: center; gap: 4px; font-size: 12px; font-weight: 700; color: #1F7A55; cursor: pointer; background: none; border: 0; padding: 0; }
        .bel-all:hover { text-decoration: underline; }
        .bel-all svg { width: 13px; height: 13px; }
        .bel-done { padding: 14px 4px; text-align: center; font-size: 12.5px; color: #94A3B8; }
        .bel-done a { color: #2563EB; font-weight: 700; }
    </style>
    <script>
        (function () {
            var cfg = { one: @json(url('notifications')) + '/', all: @json(route('notifications.readAll')), csrf: @json(csrf_token()) };
            function post(url) {
                return fetch(url, {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': cfg.csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                }).then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); });
            }
            function drop(sel, unread) {
                document.querySelectorAll(sel).forEach(function (el) { el.remove(); });
                window.dispatchEvent(new CustomEvent('oi-bell-changed', { detail: { unread: unread } }));
            }
            window.oiBellRead = function (id) {
                document.querySelectorAll('[data-bell-id="' + id + '"]').forEach(function (el) { el.style.opacity = '.45'; });
                return post(cfg.one + encodeURIComponent(id) + '/mark-read')
                    .then(function (j) { drop('[data-bell-id="' + id + '"]', j.unread); })
                    .catch(function () { document.querySelectorAll('[data-bell-id="' + id + '"]').forEach(function (el) { el.style.opacity = ''; }); });
            };
            window.oiBellReadAll = function () {
                return post(cfg.all).then(function () { drop('[data-bell-id]', 0); }).catch(function () {});
            };
            // Dữ liệu Alpine cho một khối thông báo: bellCount = số chưa đọc, bellLeft = số dòng còn hiện trong khối.
            window.oiBell = function (count) {
                return {
                    bellCount: count, bellLeft: 0,
                    init: function () {
                        var self = this, root = this.$el;
                        self.bellLeft = root.querySelectorAll('[data-bell-id]').length;
                        window.addEventListener('oi-bell-changed', function (e) {
                            if (e.detail && typeof e.detail.unread === 'number') self.bellCount = e.detail.unread;
                            self.bellLeft = root.querySelectorAll('[data-bell-id]').length;
                        });
                    },
                    bellReadOne: function (id) { window.oiBellRead(id); },
                    bellReadAll: function () { window.oiBellReadAll(); },
                };
            };
        })();
    </script>
@endonce

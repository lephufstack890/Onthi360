{{-- SỬA 28/10 (khách: "không có quyền sửa xoá sao lại được thêm sửa xoá — ẩn đi nha") — ẨN NÚT theo phân quyền khu quản trị.

     Không phải sửa từng trang: trình duyệt dò mọi link / form / nút trên trang, cái nào trỏ tới route quản trị mà người
     đang đăng nhập KHÔNG có quyền (theo App\Support\AdminPermissions — cùng luật với middleware chặn thật) thì ẩn đi:
       · link hành động (Thêm, Sửa, Xoá, Duyệt… hoặc chỉ có icon) → ẩn; link là tên / tiêu đề → giữ chữ, bỏ bấm;
       · form gửi tới route không được phép (Xoá, Lưu, Đổi thứ tự…) → ẩn; form bọc cả bảng → chỉ ẩn nút gửi;
       · phần tử ghi data-oi-perm="mục.thao tác" (vd products.update; nhiều quyền cách bằng |) → ẩn khi không có quyền nào;
       · nhóm bao quanh mà mọi thứ bên trong đều đã ẩn → ẩn luôn cho khỏi trống.
     Nội dung Alpine vẽ sau (x-for, x-if) cũng được dò nhờ MutationObserver. Người có đủ quyền (Admin mặc định, Super Admin)
     thì khối này không in ra gì. Chặn thật vẫn là middleware EnsureAdminPermission — lớp này chỉ để giao diện gọn, đúng quyền. --}}
@php $oiPermUi = \App\Support\AdminPermissions::uiPayload(auth()->user()); @endphp
@if ($oiPermUi)
    <style>[data-oi-perm-off] { display: none !important; }</style>
    <script>
        (function () {
            var P = @json($oiPermUi);
            var R = [], OK = {};
            P.r.forEach(function (e) {
                try { R.push({ m: e[0], re: new RegExp('^' + e[1] + '/?$'), ok: e[2] === 1, s: e[3] === 1 }); } catch (err) {}
            });
            P.a.forEach(function (s) { OK[s] = 1; });

            // Chữ của link "hành động" — link không có quyền mà mang chữ này thì ẩn hẳn; link khác (tên, tiêu đề) chỉ bỏ bấm.
            var ACT = /^\s*(\+|Thêm|Tạo|Sửa|Chỉnh|Xo[áa]|Gỡ|Nhập|Tải lên|Tải gói|Duyệt|Từ chối|Phát hành|Lưu|Cập nhật|Đổi|Sao chép|Nhân bản|Khôi phục|Thu hồi|Ẩn|Hiện|Import|Đưa|Phân quyền|Cấp|Kích hoạt|Hủy|Huỷ|Trả lời|Tính lại|Xử lý|Nhận)/i;
            var STOP = /^(TD|TH|TR|TBODY|THEAD|TABLE|MAIN|BODY|HTML|SECTION|ARTICLE|ASIDE|NAV|HEADER|FOOTER|UL|OL|DIALOG)$/;

            function routeOk(url, method) {
                if (!url) return true;
                var u;
                try { u = new URL(url, location.href); } catch (err) { return true; }
                if (u.origin !== location.origin) return true;
                var path = u.pathname.replace(/\/+$/, '') || '/', i, hit = null;
                // Như Laravel: route tĩnh (không tham số) được khớp trước, rồi tới route có tham số theo thứ tự khai báo.
                for (i = 0; i < R.length && !hit; i++) if (R[i].s && R[i].m === method && R[i].re.test(path)) hit = R[i];
                for (i = 0; i < R.length && !hit; i++) if (!R[i].s && R[i].m === method && R[i].re.test(path)) hit = R[i];
                return hit ? hit.ok : true;
            }

            function visibleChild(c) {
                if (c.nodeType === 3) return c.nodeValue.trim() !== '';
                if (c.nodeType !== 1 || c.hasAttribute('data-oi-perm-off')) return false;
                if (/^(SCRIPT|TEMPLATE|STYLE)$/.test(c.tagName)) return false;
                if (c.tagName === 'INPUT' && (c.type || '').toLowerCase() === 'hidden') return false;
                return true;
            }
            function collapse(p, depth) {
                if (!p || depth <= 0 || p.nodeType !== 1 || STOP.test(p.tagName) || p.hasAttribute('data-oi-perm-off')) return;
                for (var c = p.firstChild; c; c = c.nextSibling) if (visibleChild(c)) return;
                p.setAttribute('data-oi-perm-off', '');
                collapse(p.parentElement, depth - 1);
            }
            function off(el) {
                if (!el || el.hasAttribute('data-oi-perm-off')) return;
                el.setAttribute('data-oi-perm-off', '');
                collapse(el.parentElement, 3);
            }

            function checkTag(el) {
                var need = (el.getAttribute('data-oi-perm') || '').split('|').filter(Boolean);
                if (need.length && !need.some(function (s) { return OK[s.trim()]; })) off(el);
            }
            function checkA(a) {
                var h = a.getAttribute('href');
                if (a.__oiH === h) return;
                a.__oiH = h;
                if (!h || h.charAt(0) === '#' || /^(javascript|mailto|tel):/i.test(h)) return;
                if (routeOk(h, 'GET')) return;
                var txt = (a.textContent || '').replace(/\s+/g, ' ').trim();
                if (txt.length <= 3 || ACT.test(txt) || /\b(btn|button)\b/i.test(a.className)) {
                    off(a);
                } else {
                    a.__oiH = null;
                    a.removeAttribute('href');
                    a.removeAttribute('target');
                    a.style.pointerEvents = 'none';
                    a.style.cursor = 'default';
                }
            }
            function formMethod(f) {
                var m = f.querySelector('input[name="_method"]');
                return ((m && m.value) || f.getAttribute('method') || 'GET').toUpperCase();
            }
            function submitsOf(f) {
                var list = [].slice.call(f.querySelectorAll('button:not([type]),button[type="submit"],input[type="submit"],input[type="image"]'));
                if (f.id) list = list.concat([].slice.call(document.querySelectorAll('[form="' + f.id.replace(/"/g, '') + '"]')));
                return list;
            }
            function checkForm(f) {
                var act = f.getAttribute('action') || location.href, method = formMethod(f), key = act + '|' + method;
                if (f.__oiK === key) return;
                f.__oiK = key;
                if (routeOk(act, method)) return;
                if (f.querySelector('table,[data-oi-perm-keep]')) submitsOf(f).forEach(off); else off(f);
            }
            function checkFA(b) {
                var f = b.form, method = (b.getAttribute('formmethod') || (f ? formMethod(f) : 'GET')).toUpperCase();
                if (!routeOk(b.getAttribute('formaction'), method)) off(b);
            }

            function each(root, sel, fn) {
                if (root.matches && root.matches(sel)) fn(root);
                [].forEach.call(root.querySelectorAll(sel), fn);
            }
            function scan(root) {
                if (!root || root.nodeType !== 1) return;
                each(root, '[data-oi-perm]', checkTag);
                each(root, 'form', checkForm);
                each(root, 'a[href]', checkA);
                each(root, 'button[formaction],input[formaction]', checkFA);
            }

            scan(document.documentElement);
            new MutationObserver(function (list) {
                list.forEach(function (m) {
                    if (m.type === 'attributes') scan(m.target);
                    else [].forEach.call(m.addedNodes, scan);
                });
            }).observe(document.documentElement, { childList: true, subtree: true, attributes: true, attributeFilter: ['href', 'action', 'data-oi-perm'] });
        })();
    </script>
@endif

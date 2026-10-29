{{--
    SỬA 29/10 (khách: "xem đề / tài liệu / sách để làm mà quay lại là về trang 1, mất hết điều kiện lọc, phân trang đang xem —
    quay lại thì tải lại trang nhưng giữ nguyên lọc + phân trang") — nút Quay lại về ĐÚNG trang danh sách vừa xem.

    Trang danh sách (Kho tài liệu, Luyện tập) ghi toàn bộ bộ lọc + số trang lên thanh địa chỉ và nhớ địa chỉ đó vào
    sessionStorage ('oi.list.<tên>'). Nút quay lại gắn data-oi-back="<tên>": nếu đã nhớ một địa chỉ CÙNG trang với đích mặc
    định của nút thì đổi đích sang địa chỉ đó (tải lại trang danh sách với đúng bộ lọc / trang cũ); chưa nhớ thì giữ đích cũ.
    Chỉ nhận đường dẫn nội bộ (bắt đầu bằng 1 dấu "/").
--}}
<script>
    (function () {
        function memo(key) {
            try {
                var v = window.sessionStorage.getItem('oi.list.' + key);
                return v && v.charAt(0) === '/' && v.charAt(1) !== '/' ? v : null;
            } catch (e) { return null; }
        }
        [].forEach.call(document.querySelectorAll('a[data-oi-back]'), function (a) {
            if (a.__oiBack) { return; }
            a.__oiBack = true;
            var v = memo(a.getAttribute('data-oi-back'));
            if (!v) { return; }
            try {
                var base = new URL(a.getAttribute('href') || '/', window.location.href);
                var saved = new URL(v, window.location.origin);
                if (base.origin === window.location.origin && base.pathname.replace(/\/+$/, '') === saved.pathname.replace(/\/+$/, '')) {
                    a.setAttribute('href', saved.pathname + saved.search);
                }
            } catch (e) { /* giữ đích cũ */ }
        });
    })();
</script>

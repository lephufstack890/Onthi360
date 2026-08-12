{{--
  Dùng chung cho mọi trang muốn 1 vài textarea thành rich text editor.
  Cách dùng: thêm thuộc tính data-rich-editor vào <textarea> muốn chuyển,
  rồi @include file này trong @push('scripts') của trang đó — KHÔNG include
  ở layout chung để tránh nạp CKEditor ở những trang không cần (VD: ô lý do
  từ chối/tạm dừng của Admin, bio ngắn khi đăng ký, hay textarea code của
  online judge — những chỗ đó CỐ Ý không chuyển, xem ghi chú khi giao việc).

  CKEditor 5 (build "classic" qua jsDelivr) — KHÔNG dùng CKEditor 4 (đã
  ngừng hỗ trợ miễn phí từ 2023, bản "-lts" là hàng CKSource bán riêng,
  không có trên CDN công khai). Ghim dòng bản 41.x qua jsDelivr (@41 tự lấy
  bản 41 mới nhất, không cần nhớ đúng số patch) — dòng cuối trước khi
  CKEditor 5 bắt buộc khai báo licenseKey, nên dùng miễn phí không phải lo.

  Lưu ý: nội dung các ô này sẽ lưu dưới dạng HTML. Nơi nào hiển thị lại ra
  ngoài cần dùng {!! !!} (không escape) và nên lọc HTML nguy hiểm phía
  server nếu field đó nhận input từ vai trò không tin cậy hoàn toàn.
--}}
<script src="https://cdn.jsdelivr.net/npm/@ckeditor/ckeditor5-build-classic@41/build/ckeditor.js"></script>
<style>
    .ck-editor__editable_inline { min-height: 320px; }
</style>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof ClassicEditor === 'undefined') return;
        document.querySelectorAll('textarea[data-rich-editor]').forEach(function (el) {
            ClassicEditor.create(el)
                .then(function (editor) {
                    var form = el.closest('form');
                    if (form) {
                        // CKEditor 5 không tự đồng bộ ngược vào textarea gốc khi gõ —
                        // phải gọi updateSourceElement() trước khi form submit thật.
                        form.addEventListener('submit', function () { editor.updateSourceElement(); });
                    }
                })
                .catch(function (error) { console.error('CKEditor không khởi tạo được:', error); });
        });
    });
</script>

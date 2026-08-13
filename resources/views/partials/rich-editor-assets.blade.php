
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

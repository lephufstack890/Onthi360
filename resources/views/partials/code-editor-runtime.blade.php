{{-- ═══════════ BỘ TÔ MÀU CÚ PHÁP + MÃ KHỞI TẠO (dùng chung) ═══════════
     SỬA 18/9 — chép nguyên từ bản mẫu education-main/src/components/AssessmentModal.jsx
     (codeTokenPattern / escapeCodeHtml / highlightCode / starterCodeByLanguage), bỏ phần cú
     pháp JSX. Tách ra partial vì HAI màn cùng dùng và phải tô màu giống hệt nhau:
       · student/assessment/take.blade.php      (phòng thi, đề nhiều câu)
       · student/practice/exercise-play.blade.php (luyện 1 bài)
     Tên lớp Tailwind giữ nguyên để màu chữ khớp bản mẫu; các lớp này nằm nguyên văn trong
     file nên Tailwind quét và sinh CSS bình thường. --}}
<script>
        // ══════════════════════════════════════════════════════════════════════════════
        // Bộ tô màu cú pháp — CHÉP NGUYÊN từ bản mẫu (education-main/src/components/
        // AssessmentModal.jsx: codeTokenPattern / escapeCodeHtml / highlightCode), chỉ bỏ
        // phần cú pháp JSX. Giữ nguyên tên lớp Tailwind để màu chữ giống hệt bản mẫu.
        // ══════════════════════════════════════════════════════════════════════════════
        var codeTokenPattern = /\/\/[^\n]*|#[^\n]*|\/\*[\s\S]*?\*\/|"(?:\\.|[^"\\])*"|'(?:\\.|[^'\\])*'|\b\d+(?:\.\d+)?\b|\b[A-Za-z_]\w*\b/g;

        function escapeCodeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        }

        function highlightCode(source, language, theme) {
            source = String(source == null ? '' : source);
            var isPython = String(language || '').toLowerCase().indexOf('python') !== -1;
            var palette = theme === 'dark'
                ? { plain: 'text-[#EAF5F8]', comment: 'text-[#7FA59B] italic', preprocessor: 'font-semibold text-[#D6B4FC]', string: 'text-[#E8C07A]', number: 'text-[#B9A7FF]', keyword: 'font-bold text-[#7FD7FF]', type: 'font-semibold text-[#78E0B0]', literal: 'font-semibold text-[#F7A8D8]', fn: 'text-[#D6B4FC]' }
                : { plain: 'text-[#243B4A]', comment: 'text-[#71869A] italic', preprocessor: 'font-semibold text-[#6D638E]', string: 'text-[#9A5F4A]', number: 'text-[#956F2D]', keyword: 'font-bold text-[#0F607E]', type: 'font-semibold text-[#32745F]', literal: 'font-semibold text-[#8B4F7A]', fn: 'text-[#7A4B9C]' };
            var keywords = new Set(isPython
                ? ['and', 'as', 'assert', 'break', 'class', 'continue', 'def', 'elif', 'else', 'for', 'from', 'if', 'import', 'in', 'is', 'lambda', 'not', 'or', 'pass', 'raise', 'return', 'try', 'while', 'with', 'yield']
                : ['alignas', 'auto', 'break', 'case', 'catch', 'class', 'const', 'continue', 'default', 'delete', 'do', 'else', 'for', 'if', 'include', 'namespace', 'new', 'return', 'struct', 'switch', 'template', 'throw', 'try', 'typedef', 'using', 'virtual', 'void', 'while']);
            var types = new Set(isPython
                ? ['bool', 'dict', 'float', 'int', 'list', 'set', 'str', 'tuple']
                : ['bool', 'char', 'double', 'float', 'int', 'long', 'size_t', 'string', 'unsigned', 'vector', 'set', 'map', 'pair']);
            var literals = new Set(['True', 'False', 'None', 'true', 'false', 'nullptr', 'NULL']);

            // Phần KHÔNG khớp token vẫn phải escape, nếu không mã của học sinh có thẻ HTML sẽ
            // chạy thật trong lớp tô màu. Bản mẫu dùng String.replace nên khoảng trắng giữa các
            // token được giữ nguyên — ở đây escape luôn phần đó.
            var out = '';
            var last = 0;
            var m;
            codeTokenPattern.lastIndex = 0;
            while ((m = codeTokenPattern.exec(source)) !== null) {
                var token = m[0];
                var offset = m.index;
                out += escapeCodeHtml(source.slice(last, offset));
                var cls = palette.plain;
                if (token.indexOf('//') === 0 || token.indexOf('/*') === 0 || (isPython && token.charAt(0) === '#')) cls = palette.comment;
                else if (!isPython && token.charAt(0) === '#') cls = palette.preprocessor;
                else if (token.charAt(0) === '"' || token.charAt(0) === "'") cls = palette.string;
                else if (/^\d/.test(token)) cls = palette.number;
                else if (keywords.has(token)) cls = palette.keyword;
                else if (types.has(token)) cls = palette.type;
                else if (literals.has(token)) cls = palette.literal;
                else if (/^\s*\(/.test(source.slice(offset + token.length, offset + token.length + 2))) cls = palette.fn;
                out += '<span class="' + cls + '">' + escapeCodeHtml(token) + '</span>';
                last = offset + token.length;
            }
            out += escapeCodeHtml(source.slice(last));
            return out + '\n';
        }

        // Mã khởi tạo theo ngôn ngữ — chép từ starterCodeByLanguage của bản mẫu.
        var STARTER_CODE = {
            cpp: '#include <iostream>\nusing namespace std;\n\nint main() {\n  cout << "Hello, World!" << endl;\n  return 0;\n}',
            python: 'print("Hello, World!")'
        };
</script>

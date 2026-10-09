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
        // SỬA 23/9 (khách: "code C++ mặc định dùng bits/stdc++.h") — dân thi lập trình quen
        // gộp 1 dòng include duy nhất, khỏi phải nhớ từng thư viện. Dấu { xuống dòng riêng
        // theo chuẩn khách yêu cầu (23/9).
        var STARTER_CODE = {
            cpp: '#include <bits/stdc++.h>\nusing namespace std;\n\nint main()\n{\n    ios_base::sync_with_stdio(false);\n    cin.tie(nullptr);\n\n    return 0;\n}',
            python: 'print("Hello, World!")'
        };

        // SỬA 9/10 (khách: đề kiểu HELLOWORLD bắt đọc HELLOWORLD.INP / ghi HELLOWORLD.OUT, thiếu freopen
        // thì chấm sai) — đề khai tên tệp vào/ra thì mã mẫu có sẵn dòng mở tệp đúng tên. fileIo =
        // { input, output } hoặc null; đề bình thường (null) trả về ĐÚNG mã mẫu cũ, không đổi gì.
        function starterCodeFor(language, fileIo) {
            var base = STARTER_CODE[language] || STARTER_CODE.cpp;
            var inName = fileIo && fileIo.input ? String(fileIo.input) : '';
            var outName = fileIo && fileIo.output ? String(fileIo.output) : '';
            if (!inName && !outName) return base;
            if (language === 'python') {
                var py = ['import sys'];
                if (inName) py.push('sys.stdin = open(' + JSON.stringify(inName) + ', "r")');
                if (outName) py.push('sys.stdout = open(' + JSON.stringify(outName) + ', "w")');
                return py.join('\n') + '\n\n' + base;
            }
            var lines = [];
            if (inName) lines.push('    freopen("' + inName + '", "r", stdin);');
            if (outName) lines.push('    freopen("' + outName + '", "w", stdout);');
            return base.replace('{\n', '{\n' + lines.join('\n') + '\n');
        }

        // Mã đang có còn là MÃ MẪU (bản thường hoặc bản có freopen) — chưa viết gì thêm -> đổi ngôn
        // ngữ/đặt lại thì được thay bằng mã mẫu mới, không xoá bài đang viết dở.
        function isStarterCode(text, fileIo) {
            var cur = String(text || '').trim();
            if (cur === '') return true;
            return [STARTER_CODE.cpp, STARTER_CODE.python, starterCodeFor('cpp', fileIo), starterCodeFor('python', fileIo)]
                .some(function (s) { return cur === String(s).trim(); });
        }

        // ══════════════════════════════════════════════════════════════════════════════
        // PHÍM TẮT CHO Ô SOẠN MÃ
        //
        // SỬA 30/9 (4) (khách: "soạn code bấm tab nó nhảy sang cái input khác")
        // SỬA 30/9 (5) (khách: "check kỹ hết luôn, còn thiếu thì bổ sung cho tôi khi họ code")
        //
        // Ô soạn mã ở đây là <textarea> trơn chồng lên lớp tô màu (đúng cách bản mẫu làm),
        // nên nó KHÔNG tự có những nết mà ai gõ code cũng coi là đương nhiên. Đoạn này bù
        // lại đúng những nết đó:
        //
        //   Tab              thụt dòng; bôi đen nhiều dòng thì thụt cả khối
        //   Shift+Tab        lùi lại một mức (không cần bôi đen cũng được)
        //   Enter            xuống dòng GIỮ NGUYÊN mức thụt của dòng trên; sau "{" (hoặc sau
        //                    ":" với Python) thì thụt thêm một mức; đang kẹp giữa "{" và "}"
        //                    thì mở ra ba dòng và đặt con trỏ ở dòng giữa
        //   Backspace        đang ở phần thụt đầu dòng thì xoá NGUYÊN một mức thay vì từng
        //                    dấu cách; đang kẹp giữa cặp ngoặc rỗng thì xoá cả cặp
        //   ( [ { " '        tự đóng; đang bôi đen thì BỌC phần bôi đen vào cặp đó
        //   ) ] } " '        gõ đúng dấu đóng đang nằm ngay trước mặt thì chỉ bước qua, không
        //                    sinh thêm dấu thừa
        //   Ctrl+/ (Cmd+/)   bật/tắt chú thích cho các dòng đang chọn (// hoặc # tuỳ ngôn ngữ)
        //   Alt+↑ / Alt+↓    đẩy dòng (hoặc khối đang chọn) lên/xuống
        //   Shift+Alt+↑/↓    nhân đôi dòng lên trên / xuống dưới
        //   Home             về ký tự đầu tiên KHÁC khoảng trắng; bấm lần nữa mới về cột 0
        //   Esc rồi Tab      Tab nhảy ô như biểu mẫu thường — lối thoát cho người dùng bàn
        //                    phím và trình đọc màn hình, để không bị kẹt cứng trong ô
        //
        // Ba điều quan trọng về cách cài:
        //   1. Nghe kiểu delegation ở document nên ăn cho CẢ BA màn dùng chung partial này
        //      (luyện 1 bài, phòng thi, cuộc thi) và vẫn chạy sau khi khối soạn mã bị thay
        //      mới bằng AJAX — không phải gọi lại hàm init nào.
        //   2. Mọi thay đổi đi qua execCommand chứ không gán thẳng textarea.value, vì hai lẽ:
        //      Ctrl+Z vẫn hoàn tác được (gán thẳng value là xoá sạch lịch sử hoàn tác), và
        //      trình duyệt tự bắn sự kiện 'input' — nhờ đó lớp tô màu vẽ lại và x-model của
        //      Alpine ở màn phòng thi cập nhật theo.
        //   3. Bỏ qua khi bộ gõ tiếng Việt đang ghép chữ (isComposing / keyCode 229), nếu
        //      không thì gõ dấu bằng Telex trong dòng chú thích sẽ loạn.
        // ══════════════════════════════════════════════════════════════════════════════
        (function () {
            if (window.__oiCodeKeysWired) return;
            window.__oiCodeKeysWired = true;

            var INDENT = '    ';
            var OPEN = { '(': ')', '[': ']', '{': '}', '"': '"', "'": "'" };
            var CLOSERS = ')]}"\'';
            var escaped = false;

            function isEditor(el) {
                return !!el && el.tagName === 'TEXTAREA' && el.hasAttribute('data-code-source')
                    && !el.disabled && !el.readOnly;
            }

            function norm(v) {
                return String(v || '').toLowerCase().indexOf('py') === 0 ? 'python' : 'cpp';
            }

            // Ngôn ngữ lấy từ ô chọn GẦN NHẤT — màn phòng thi có nhiều câu, mỗi câu một ô chọn.
            function langOf(ta) {
                var node = ta.parentElement;
                for (var i = 0; i < 8 && node; i++) {
                    var near = node.querySelector && node.querySelector('select[data-code-language]');
                    if (near) return norm(near.value);
                    node = node.parentElement;
                }
                var any = document.querySelector('select[data-code-language]');
                return any ? norm(any.value) : 'cpp';
            }

            function escapeForRegExp(text) {
                return text.replace(/[.*+?^${}()|[\]\\\/]/g, '\\$&');
            }

            function replaceRange(ta, from, to, text, selFrom, selTo) {
                ta.selectionStart = from;
                ta.selectionEnd = to;

                var ok = false;
                try {
                    ok = text === ''
                        ? document.execCommand('delete')
                        : document.execCommand('insertText', false, text);
                } catch (e) {
                    ok = false;
                }

                if (!ok) {
                    // Trình duyệt không cho execCommand: gán tay rồi tự bắn 'input'.
                    ta.value = ta.value.slice(0, from) + text + ta.value.slice(to);
                    ta.dispatchEvent(new Event('input', { bubbles: true }));
                }

                if (selFrom !== undefined) {
                    ta.selectionStart = selFrom;
                    ta.selectionEnd = selTo === undefined ? selFrom : selTo;
                }
            }

            function lineStart(v, pos) { return v.lastIndexOf('\n', pos - 1) + 1; }
            function lineEnd(v, pos) { var i = v.indexOf('\n', pos); return i === -1 ? v.length : i; }
            function indentOf(line) { var m = line.match(/^[ \t]*/); return m ? m[0] : ''; }

            // ─────────────────── Tab / Shift+Tab: thụt cả khối ───────────────────
            function indentBlock(ta, outdent) {
                var v = ta.value, start = ta.selectionStart, end = ta.selectionEnd;
                var from = lineStart(v, start), to = lineEnd(v, end);
                var lines = v.slice(from, to).split('\n');
                var firstDelta = 0, total = 0;

                var out = lines.map(function (line, i) {
                    var changed;
                    if (outdent) {
                        var m = line.match(/^ {1,4}|^\t/);
                        changed = m ? line.slice(m[0].length) : line;
                    } else {
                        // Dòng trống giữa khối để yên, khỏi sinh khoảng trắng thừa.
                        changed = (line === '' && lines.length > 1) ? line : INDENT + line;
                    }
                    var d = changed.length - line.length;
                    if (i === 0) firstDelta = d;
                    total += d;
                    return changed;
                }).join('\n');

                var a = Math.max(from, start + firstDelta);
                replaceRange(ta, from, to, out, a, Math.max(a, end + total));
            }

            // ─────────────────── Enter: giữ mức thụt ───────────────────
            function smartEnter(ta, lang) {
                var v = ta.value, start = ta.selectionStart, end = ta.selectionEnd;
                var from = lineStart(v, start);
                var indent = indentOf(v.slice(from, start));
                var before = v.slice(from, start).replace(/\s+$/, '');

                var opensBlock = /[{([]$/.test(before) || (lang === 'python' && /:$/.test(before));
                var inner = opensBlock ? indent + INDENT : indent;
                var next = v.charAt(end);

                // Kẹp đúng giữa cặp ngoặc -> mở ra ba dòng, con trỏ nằm ở dòng giữa.
                if (opensBlock && (next === '}' || next === ')' || next === ']')) {
                    replaceRange(ta, start, end, '\n' + inner + '\n' + indent, start + 1 + inner.length);
                    return;
                }

                replaceRange(ta, start, end, '\n' + inner, start + 1 + inner.length);
            }

            // ─────────────────── Backspace ───────────────────
            function smartBackspace(ta) {
                var v = ta.value, pos = ta.selectionStart;
                var head = v.slice(lineStart(v, pos), pos);

                // Đang ở phần thụt đầu dòng -> xoá nguyên một mức.
                if (head.length > 0 && /^ +$/.test(head)) {
                    var back = head.length % INDENT.length || INDENT.length;
                    replaceRange(ta, pos - back, pos, '', pos - back);
                    return true;
                }

                // Kẹp giữa một cặp ngoặc rỗng -> xoá cả cặp.
                var prev = v.charAt(pos - 1);
                if (OPEN[prev] && OPEN[prev] === v.charAt(pos)) {
                    replaceRange(ta, pos - 1, pos + 1, '', pos - 1);
                    return true;
                }

                return false;
            }

            // ─────────────────── Ngoặc và nháy ───────────────────
            function autoPair(ta, ch) {
                var v = ta.value, start = ta.selectionStart, end = ta.selectionEnd;

                // Gõ đúng dấu đóng đang nằm ngay trước mặt -> chỉ bước qua.
                if (start === end && CLOSERS.indexOf(ch) >= 0 && v.charAt(start) === ch) {
                    ta.selectionStart = ta.selectionEnd = start + 1;
                    return true;
                }

                if (!OPEN[ch]) return false;

                // Đang bôi đen -> bọc phần bôi đen vào cặp đó.
                if (start !== end) {
                    replaceRange(ta, start, end, ch + v.slice(start, end) + OPEN[ch], start + 1, end + 1);
                    return true;
                }

                var prev = v.charAt(start - 1), next = v.charAt(start);

                // Nháy đơn/kép: không tự đóng khi đang dính vào một chữ — gõ "don't" trong dòng
                // chú thích mà thành "don''t" thì phiền hơn là tiện.
                if ((ch === '"' || ch === "'") && (/[\w\\]/.test(prev) || /\w/.test(next))) return false;

                // Chỉ tự đóng khi phía sau là hết dòng, khoảng trắng hoặc một dấu đóng khác.
                if (next !== '' && !/[\s)\]},;]/.test(next)) return false;

                replaceRange(ta, start, start, ch + OPEN[ch], start + 1);
                return true;
            }

            // ─────────────────── Ctrl+/ bật tắt chú thích ───────────────────
            function toggleComment(ta, lang) {
                var token = lang === 'python' ? '#' : '//';
                var v = ta.value, start = ta.selectionStart, end = ta.selectionEnd;
                var from = lineStart(v, start), to = lineEnd(v, end);
                var lines = v.slice(from, to).split('\n');
                var live = lines.filter(function (l) { return l.trim() !== ''; });
                if (live.length === 0) return;

                var allCommented = live.every(function (l) { return l.trim().indexOf(token) === 0; });
                var strip = new RegExp('^(\\s*)' + escapeForRegExp(token) + ' ?');
                var pad = live.reduce(function (min, l) {
                    var n = indentOf(l).length;
                    return n < min ? n : min;
                }, Infinity);

                var out = lines.map(function (line) {
                    if (line.trim() === '') return line;
                    return allCommented
                        ? line.replace(strip, '$1')
                        : line.slice(0, pad) + token + ' ' + line.slice(pad);
                }).join('\n');

                replaceRange(ta, from, to, out, from, from + out.length);
            }

            // ─────────────────── Alt+↑/↓ đẩy dòng, Shift+Alt nhân đôi ───────────────────
            function moveLines(ta, dir, duplicate) {
                var v = ta.value, start = ta.selectionStart, end = ta.selectionEnd;
                var from = lineStart(v, start), to = lineEnd(v, end);
                var block = v.slice(from, to);

                if (duplicate) {
                    var at = dir < 0 ? from : to;
                    var copy = dir < 0 ? block + '\n' : '\n' + block;
                    var shift = dir < 0 ? 0 : block.length + 1;
                    replaceRange(ta, at, at, copy, start + shift, end + shift);
                    return;
                }

                if (dir < 0) {
                    if (from === 0) return;
                    var prevFrom = lineStart(v, from - 1);
                    var prev = v.slice(prevFrom, from - 1);
                    replaceRange(ta, prevFrom, to, block + '\n' + prev,
                        start - (prev.length + 1), end - (prev.length + 1));
                } else {
                    if (to >= v.length) return;
                    var nextTo = lineEnd(v, to + 1);
                    var after = v.slice(to + 1, nextTo);
                    replaceRange(ta, from, nextTo, after + '\n' + block,
                        start + (after.length + 1), end + (after.length + 1));
                }
            }

            // ─────────────────── Home thông minh ───────────────────
            function smartHome(ta, extend) {
                var v = ta.value, pos = ta.selectionStart;
                var from = lineStart(v, pos);
                var first = from + indentOf(v.slice(from, lineEnd(v, pos))).length;
                var target = (pos === first) ? from : first;

                if (extend) {
                    ta.setSelectionRange(Math.min(target, ta.selectionEnd), Math.max(target, ta.selectionEnd));
                } else {
                    ta.setSelectionRange(target, target);
                }
            }

            document.addEventListener('keydown', function (event) {
                var ta = event.target;
                if (!isEditor(ta)) return;
                if (event.isComposing || event.keyCode === 229) return;   // bộ gõ tiếng Việt

                var key = event.key;
                var ctrl = event.ctrlKey || event.metaKey;

                if (key === 'Escape') { escaped = true; return; }

                if (key === 'Tab') {
                    if (escaped) { escaped = false; return; }   // để Tab nhảy ô
                    if (ctrl || event.altKey) return;
                    event.preventDefault();

                    var v = ta.value, s = ta.selectionStart, e = ta.selectionEnd;
                    if (event.shiftKey || v.slice(s, e).indexOf('\n') >= 0) {
                        indentBlock(ta, event.shiftKey);
                    } else {
                        replaceRange(ta, s, e, INDENT, s + INDENT.length);
                    }
                    return;
                }

                escaped = false;

                if (key === 'Enter' && !ctrl && !event.altKey && !event.shiftKey) {
                    event.preventDefault();
                    smartEnter(ta, langOf(ta));
                    return;
                }

                if (key === 'Backspace' && !ctrl && !event.altKey && ta.selectionStart === ta.selectionEnd) {
                    if (smartBackspace(ta)) event.preventDefault();
                    return;
                }

                if (ctrl && (key === '/' || key === '?')) {
                    event.preventDefault();
                    toggleComment(ta, langOf(ta));
                    return;
                }

                if (event.altKey && !ctrl && (key === 'ArrowUp' || key === 'ArrowDown')) {
                    event.preventDefault();
                    moveLines(ta, key === 'ArrowUp' ? -1 : 1, event.shiftKey);
                    return;
                }

                if (key === 'Home' && !ctrl && !event.altKey) {
                    event.preventDefault();
                    smartHome(ta, event.shiftKey);
                    return;
                }

                if (!ctrl && !event.altKey && key.length === 1
                    && (OPEN[key] || CLOSERS.indexOf(key) >= 0)) {
                    if (autoPair(ta, key)) event.preventDefault();
                }
            });
        })();
</script>

{{-- ═══════════ CỘT SỐ DÒNG KIỂU VS CODE (chỉ là GIAO DIỆN) ═══════════
     SỬA 7/10 (khách: "cho UI hiển thị số dòng giống kiểu VS Code cho đẹp") — thêm một cột số dòng
     bên trái ô soạn mã, ở MỌI nơi dùng ô soạn mã kiểu "textarea trong suốt + lớp tô màu":
     luyện 1 bài (exercise-play), phòng thi (take) và thi cuộc thi (competitions/exam).

     KHÔNG ĐỤNG LOGIC: không sửa textarea, không đổi tên/giá trị/sự kiện của nó, không chặn hay
     thêm gì vào luồng nộp bài. Đoạn này chỉ ĐỌC textarea[data-code-source] (giá trị, vị trí con
     trỏ, scrollTop) rồi vẽ thêm vài thẻ trang trí bên cạnh:
       · cột số dòng (số dòng hiện tại đậm hơn);
       · dải sáng mờ ở dòng đang đứng (chỉ khi ô đang được trỏ vào).
     Tự gắn cho mọi ô mã, kể cả ô được dựng lại sau khi chấm bài hay sau khi đổi câu, nên không
     phải sửa từng màn. Các ô này đã có sẵn lớp lệnh/lớp tô màu nên chỉ cần dịch chúng sang phải
     bằng style.left; ô vẫn co giãn theo khung như cũ.
     Màu chữ/đường kẻ bám theo html.theme-dark giống phần còn lại của khu soạn mã. --}}
<style>
    .oi-code-gutter {
        position: absolute; top: 0; bottom: 0; left: 0; z-index: 30; overflow: hidden;
        box-sizing: border-box; border-right: 1px solid #DDEAF0; color: #9DB1BF;
        text-align: right; pointer-events: none; -webkit-user-select: none; user-select: none;
    }
    .oi-code-gutter__inner { position: absolute; top: 0; left: 0; right: 0; will-change: transform; }
    .oi-code-gutter__ln { display: block; padding: 0 .75rem 0 .5rem; white-space: pre; }
    .oi-code-gutter__ln.is-active { color: #123B68; font-weight: 700; }
    .oi-code-curline {
        position: absolute; left: 0; right: 0; z-index: 0; display: none;
        background: rgba(18, 111, 145, .07); pointer-events: none;
    }
    /* Viền focus của site (.assessment-modal textarea:focus-visible) vốn bị khung che đi vì ô mã phủ
       kín khung; giờ ô mã dịch sang phải nhường chỗ cho cột số dòng nên viền lộ ra thành một
       đường xanh dài. Ô mã đã có dải sáng ở dòng đang đứng làm dấu focus, bỏ viền đi. */
    textarea[data-code-source][data-gutter]:focus,
    textarea[data-code-source][data-gutter]:focus-visible { outline: none !important; box-shadow: none !important; }
    /* Con trỏ: ẩn con trỏ gốc của trình duyệt (mỗi trình duyệt vẽ lệch một kiểu so với lớp tô màu
       bên dưới) và vẽ con trỏ riêng, tính từ CÙNG số đo với cột số dòng nên luôn thẳng hàng. */
    textarea[data-code-source][data-gutter] { caret-color: transparent !important; }
    .oi-code-caretclip { position: absolute; top: 0; right: 0; bottom: 0; z-index: 25; overflow: hidden; pointer-events: none; }
    .oi-code-caret { position: absolute; top: 0; left: 0; width: 2px; margin-left: -1px; border-radius: 1px; background: #126F91; display: none; animation: oi-code-caret-blink 1.06s steps(1) infinite; }
    @keyframes oi-code-caret-blink { 0%, 55% { opacity: 1; } 56%, 100% { opacity: 0; } }
    html.theme-dark .oi-code-caret { background: #EAF5F8; }
    html.theme-dark .oi-code-gutter { border-right-color: #2B4352; color: #5E7A8C; }
    html.theme-dark .oi-code-gutter__ln.is-active { color: #D6E6F0; }
    html.theme-dark .oi-code-curline { background: rgba(120, 190, 220, .09); }
</style>
<script>
    (function () {
        function attach(ta) {
            var host = ta.parentElement;
            if (!host) return;
            ta.dataset.gutter = '1';

            var pre = host.querySelector('pre');
            var gutter = document.createElement('div');
            gutter.className = 'oi-code-gutter';
            gutter.setAttribute('aria-hidden', 'true');
            var inner = document.createElement('div');
            inner.className = 'oi-code-gutter__inner';
            gutter.appendChild(inner);
            var band = document.createElement('div');
            band.className = 'oi-code-curline';
            host.insertBefore(band, host.firstChild);
            host.appendChild(gutter);
            var caretClip = document.createElement('div');
            caretClip.className = 'oi-code-caretclip';
            var caret = document.createElement('div');
            caret.className = 'oi-code-caret';
            caretClip.appendChild(caret);
            host.appendChild(caretClip);
            // Thước đo độ rộng chữ: cùng phông với ô mã, white-space:pre để giữ nguyên dấu cách/tab.
            var ruler = document.createElement('span');
            ruler.setAttribute('aria-hidden', 'true');
            ruler.style.cssText = 'position:absolute;left:0;top:0;visibility:hidden;pointer-events:none;white-space:pre;';

            var cs = window.getComputedStyle(ta);
            var lineHeight = parseFloat(cs.lineHeight);
            if (isNaN(lineHeight)) lineHeight = (parseFloat(cs.fontSize) || 12) * 1.5;
            var padTop = parseFloat(cs.paddingTop) || 0;
            var padLeft = parseFloat(cs.paddingLeft) || 0;
            ruler.style.fontFamily = cs.fontFamily;
            ruler.style.fontSize = cs.fontSize;
            ruler.style.fontWeight = cs.fontWeight;
            ruler.style.fontStyle = cs.fontStyle;
            ruler.style.letterSpacing = cs.letterSpacing;
            ruler.style.tabSize = cs.tabSize;
            host.appendChild(ruler);
            caret.style.height = lineHeight + 'px';
            var caretKey = '';
            gutter.style.fontFamily = cs.fontFamily;
            gutter.style.fontSize = cs.fontSize;
            gutter.style.lineHeight = lineHeight + 'px';
            band.style.height = lineHeight + 'px';

            var lastCount = 0;
            var lastDigits = 0;
            var activeLine = -1;

            function caretLine(value) {
                var pos = ta.selectionStart || 0;
                var n = 0;
                for (var i = value.indexOf('\n'); i !== -1 && i < pos; i = value.indexOf('\n', i + 1)) n++;
                return n;
            }

            function refresh() {
                var value = ta.value || '';
                var count = 1;
                for (var i = value.indexOf('\n'); i !== -1; i = value.indexOf('\n', i + 1)) count++;

                if (count !== lastCount) {
                    var html = '';
                    for (var n = 1; n <= count; n++) html += '<span class="oi-code-gutter__ln">' + n + '</span>';
                    inner.innerHTML = html;
                    lastCount = count;
                    activeLine = -1;

                    // Cột rộng theo số chữ số của dòng cuối (tối thiểu 2), để 99 → 100 không giật chữ.
                    var digits = Math.max(2, String(count).length);
                    if (digits !== lastDigits) {
                        lastDigits = digits;
                        var width = 'calc(' + digits + 'ch + 1.25rem + 1px)';
                        gutter.style.width = width;
                        if (pre) pre.style.left = width;
                        ta.style.left = width;
                        ta.style.width = 'calc(100% - ' + width + ')';
                        band.style.left = '0';
                        // Đổi ra px theo vị trí thật của ô mã: đơn vị ch phụ thuộc phông của từng thẻ.
                        caretClip.style.left = (ta.getBoundingClientRect().left - host.getBoundingClientRect().left) + 'px';
                    }
                }

                var line = caretLine(value);
                if (line !== activeLine) {
                    var prev = inner.children[activeLine];
                    if (prev) prev.classList.remove('is-active');
                    var cur = inner.children[line];
                    if (cur) cur.classList.add('is-active');
                    activeLine = line;
                }

                var top = ta.scrollTop;
                inner.style.transform = 'translateY(' + (padTop - top) + 'px)';

                var focused = document.activeElement === ta && !ta.disabled;
                if (focused) {
                    band.style.display = 'block';
                    band.style.top = (padTop + line * lineHeight - top) + 'px';
                } else {
                    band.style.display = 'none';
                }

                // Con trỏ riêng: chỉ vẽ khi ô đang được trỏ vào và không đang bôi đen đoạn nào.
                if (focused && !ta.readOnly && ta.selectionStart === ta.selectionEnd) {
                    var pos = ta.selectionStart || 0;
                    var lineStart = pos > 0 ? value.lastIndexOf('\n', pos - 1) + 1 : 0;
                    ruler.textContent = value.slice(lineStart, pos);
                    var textWidth = ruler.getBoundingClientRect().width;
                    var x = padLeft + textWidth - ta.scrollLeft;
                    var y = padTop + line * lineHeight - top;
                    caret.style.transform = 'translate(' + x + 'px,' + y + 'px)';
                    caret.style.display = 'block';
                    // Con trỏ vừa dịch chuyển thì đang sáng ngay, rồi mới nhấp nháy tiếp (như trình soạn thật).
                    var key = pos + ':' + value.length;
                    if (key !== caretKey) {
                        caretKey = key;
                        caret.style.animation = 'none';
                        void caret.offsetWidth;
                        caret.style.animation = '';
                    }
                } else {
                    caret.style.display = 'none';
                }
            }

            var queued = false;
            function schedule() {
                if (queued) return;
                queued = true;
                window.requestAnimationFrame(function () { queued = false; refresh(); });
            }

            ta.__oiGutterRefresh = schedule;
            ['input', 'scroll', 'keyup', 'click', 'mouseup', 'focus', 'blur'].forEach(function (name) {
                ta.addEventListener(name, schedule);
            });

            // Mã đổi bằng code (mã mẫu, nạp file, Alpine x-model…) không bắn sự kiện 'input',
            // nhưng lớp tô màu bên cạnh luôn được vẽ lại → nghe nó để cập nhật số dòng.
            if (pre && window.MutationObserver) {
                new MutationObserver(schedule).observe(pre, { childList: true, characterData: true, subtree: true });
            }

            refresh();
        }

        function scan() {
            var list = document.querySelectorAll('textarea[data-code-source]');
            for (var i = 0; i < list.length; i++) {
                if (!list[i].dataset.gutter) attach(list[i]);
            }
        }

        var scanQueued = false;
        function queueScan() {
            if (scanQueued) return;
            scanQueued = true;
            window.requestAnimationFrame(function () { scanQueued = false; scan(); });
        }

        // Con trỏ di chuyển bằng phím mũi tên/chuột cũng đổi dòng đang đứng.
        document.addEventListener('selectionchange', function () {
            var ta = document.activeElement;
            if (ta && ta.__oiGutterRefresh) ta.__oiGutterRefresh();
        });

        document.addEventListener('DOMContentLoaded', scan);
        scan();
        // Ô mã được dựng mới sau khi chấm bài / đổi câu → tự gắn cột số dòng cho nó.
        if (window.MutationObserver) {
            new MutationObserver(queueScan).observe(document.documentElement, { childList: true, subtree: true });
        }
    })();
</script>

{{-- ═══════════ TẢI TEST SAI VỀ MÁY (dùng chung) ═══════════

     SỬA 30/9 (6) (khách: "Tải test nó phải là .in và .out nha") — trước đây nút này gộp mọi
     thứ vào MỘT tệp .txt có tiêu đề tiếng Việt xen giữa, tải về xong không chạy thử được:
     muốn thử lại bài trên máy thì phải tự tay cắt phần "Dữ liệu vào" ra một tệp, phần "Kết
     quả mong đợi" ra một tệp nữa.

     Giờ đúng quy ước của dân lập trình thi đấu:
       · 1 test  -> hai tệp test07.in và test07.out, chạy thẳng được:
                    ./a.out < test07.in > ket-qua.txt  rồi so với test07.out
       · nhiều test -> một tệp .zip gọn gồm đủ các cặp .in/.out
                    (KHÔNG bắn ra hàng chục tệp rời — trình duyệt sẽ hỏi cho phép tải nhiều
                     tệp và thư mục Tải về của học sinh thành bãi chiến trường).

     Tệp .zip dựng bằng tay ngay tại đây (định dạng "stored", không nén) nên KHÔNG phải tải
     thêm thư viện nào — máy chủ cũng đang chặn mấy CDN nên thêm thư viện là thêm rủi ro.

     Nghe kiểu delegation ở document: khối kết quả bị thay mới sau mỗi lần chấm bằng AJAX,
     gắn 1 lần ở document là luôn bắt được nút mới.

     Nút cần 2 thuộc tính:  data-download-failed-tests
                            data-question-id="{id câu}"
                            data-tests='[{index, input, expectedOutput, ...}]'
--}}
<script>
    (function () {
        if (window.__oiTestDownloadWired) return;
        window.__oiTestDownloadWired = true;

        function saveBlob(blob, filename) {
            var url = URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            a.remove();
            // Thu hồi muộn một nhịp: thu hồi ngay thì Safari huỷ luôn lượt tải đang chạy.
            setTimeout(function () { URL.revokeObjectURL(url); }, 4000);
        }

        function pad2(n) { return (n < 10 ? '0' : '') + n; }

        // Dữ liệu test phải kết thúc bằng một dấu xuống dòng — máy chấm nào cũng ghi vậy, và
        // thiếu nó thì nhiều bài đọc bằng cin >> sẽ treo ở dòng cuối khi học sinh chạy thử.
        function endLine(text) {
            text = String(text === null || text === undefined ? '' : text);
            return text === '' || text.slice(-1) === '\n' ? text : text + '\n';
        }

        // ── Dựng tệp ZIP kiểu "stored" (không nén) ──────────────────────────────────────
        var CRC_TABLE = (function () {
            var table = new Uint32Array(256);
            for (var n = 0; n < 256; n++) {
                var c = n;
                for (var k = 0; k < 8; k++) c = (c & 1) ? (0xEDB88320 ^ (c >>> 1)) : (c >>> 1);
                table[n] = c >>> 0;
            }
            return table;
        })();

        function crc32(bytes) {
            var c = 0xFFFFFFFF;
            for (var i = 0; i < bytes.length; i++) c = CRC_TABLE[(c ^ bytes[i]) & 0xFF] ^ (c >>> 8);
            return (c ^ 0xFFFFFFFF) >>> 0;
        }

        function makeZip(files) {
            var enc = new TextEncoder();
            var parts = [];        // các mảnh ghép thành tệp zip
            var central = [];      // mục lục trung tâm
            var offset = 0;

            function u16(v) { return [v & 0xFF, (v >>> 8) & 0xFF]; }
            function u32(v) { return [v & 0xFF, (v >>> 8) & 0xFF, (v >>> 16) & 0xFF, (v >>> 24) & 0xFF]; }

            files.forEach(function (file) {
                var nameBytes = enc.encode(file.name);
                var dataBytes = enc.encode(file.content);
                var sum = crc32(dataBytes);

                var local = [].concat(
                    u32(0x04034B50), u16(20), u16(0), u16(0), u16(0), u16(0),
                    u32(sum), u32(dataBytes.length), u32(dataBytes.length),
                    u16(nameBytes.length), u16(0)
                );

                parts.push(new Uint8Array(local), nameBytes, dataBytes);

                central.push(new Uint8Array([].concat(
                    u32(0x02014B50), u16(20), u16(20), u16(0), u16(0), u16(0), u16(0),
                    u32(sum), u32(dataBytes.length), u32(dataBytes.length),
                    u16(nameBytes.length), u16(0), u16(0), u16(0), u16(0), u32(0),
                    u32(offset)
                )), nameBytes);

                offset += local.length + nameBytes.length + dataBytes.length;
            });

            var centralSize = central.reduce(function (n, p) { return n + p.length; }, 0);
            var end = new Uint8Array([].concat(
                u32(0x06054B50), u16(0), u16(0),
                u16(files.length), u16(files.length),
                u32(centralSize), u32(offset), u16(0)
            ));

            return new Blob(parts.concat(central, [end]), { type: 'application/zip' });
        }

        document.addEventListener('click', function (event) {
            var btn = event.target.closest('[data-download-failed-tests]');
            if (!btn) return;

            var tests = [];
            try { tests = JSON.parse(btn.getAttribute('data-tests') || '[]'); } catch (e) { tests = []; }
            if (!tests.length) return;

            var qid = btn.getAttribute('data-question-id') || 'x';

            if (tests.length === 1) {
                var t = tests[0];
                var stem = 'test' + pad2(Number(t.index) || 1);
                saveBlob(new Blob([endLine(t.input)], { type: 'text/plain;charset=utf-8' }), stem + '.in');
                // Giãn một nhịp rồi mới tải tệp thứ hai: bắn hai lượt tải trong cùng một
                // khoảnh khắc thì một số trình duyệt bỏ qua lượt sau.
                setTimeout(function () {
                    saveBlob(new Blob([endLine(t.expectedOutput)], { type: 'text/plain;charset=utf-8' }), stem + '.out');
                }, 250);
                return;
            }

            var files = [];
            tests.forEach(function (test) {
                var name = 'test' + pad2(Number(test.index) || 0);
                files.push({ name: name + '.in', content: endLine(test.input) });
                files.push({ name: name + '.out', content: endLine(test.expectedOutput) });
            });

            saveBlob(makeZip(files), 'test-sai-cau-' + qid + '.zip');
        });
    })();
</script>

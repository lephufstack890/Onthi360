<?php

namespace App\Services;

use RuntimeException;
use ZipArchive;

/**
 * SỬA 24/9 (khách: "chấm lâu quá, muốn 3-6s/bài") — DỰNG GÓI + BÓC KẾT QUẢ cho cách CHẤM GỘP.
 *
 * Nền của vấn đề (đo thật trên máy chủ 24/9): cách chấm cũ gửi mỗi test một bài nộp, nên bài
 * 20 test bắt Judge0 biên dịch lại 20 lần cùng một file. 1 bài nộp mất 3,09 giây mà CHẠY
 * chương trình chỉ 0,01 giây — còn lại là biên dịch (~2s với bits/stdc++.h) và phụ phí dựng
 * hộp cách ly (~1s). Nhân 20 thành 28,9 giây.
 *
 * Gói này gửi ĐÚNG MỘT bài nộp chứa:
 *
 *     main.cpp | main.py   mã nguồn học sinh (đã chèn sẵn phần file_io nếu đề cần)
 *     compile              script biên dịch — chạy ĐÚNG 1 LẦN
 *     run                  script lặp qua từng test, chạy chương trình đã biên dịch
 *     t1.in … tN.in        dữ liệu vào của từng test
 *
 * ĐÁP ÁN ĐÚNG KHÔNG NẰM TRONG GÓI. Trước đây expected_output được gửi lên Judge0 để nó tự so;
 * giờ chỉ lấy về những gì chương trình in ra rồi so ở máy chủ mình — nhanh như nhau mà đáp án
 * không bao giờ rời khỏi máy chủ.
 *
 * Giới hạn đã biết, ghi ra đây để sau khỏi tưởng là lỗi:
 *   · KHÔNG đo được bộ nhớ từng test (Judge0 chỉ báo mức cao nhất của cả lượt chạy) — cột bộ
 *     nhớ của từng test để trống. Giới hạn bộ nhớ vẫn áp cho CẢ gói nên máy chủ vẫn được bảo vệ.
 *   · Quá thời gian ở một test được nhận ra bằng mã thoát của `timeout` (124/137), không phải
 *     do Judge0 báo.
 */
class BundledJudgePackage
{
    /** Mốc phân tách trong stdout — ngẫu nhiên mỗi lượt để không đụng nội dung bài in ra. */
    private string $mark;

    public function __construct(?string $mark = null)
    {
        $this->mark = $mark ?? '@@O360-'.bin2hex(random_bytes(6));
    }

    public function mark(): string
    {
        return $this->mark;
    }

    /** Chỉ 2 ngôn ngữ máy chấm nhận (xem config/judge0.php). */
    public static function supports(?string $langKey): bool
    {
        return in_array($langKey, ['cpp', 'python'], true);
    }

    public static function available(): bool
    {
        return class_exists(ZipArchive::class);
    }

    /**
     * Dựng gói ZIP, trả về nội dung nhị phân.
     *
     * @param  array<int, array{input:string, expected_output:string}>  $testCases
     *
     * @throws RuntimeException khi máy chủ thiếu ext-zip hoặc không ghi được tệp tạm.
     */
    public function build(string $sourceCode, string $langKey, array $testCases, int $perTestSeconds, int $runBudgetSeconds, int $outputCapBytes): string
    {
        if (! self::available()) {
            throw new RuntimeException('Máy chủ chưa bật phần mở rộng PHP "zip" nên không dựng được gói chấm.');
        }

        $sourceName = $langKey === 'python' ? 'main.py' : 'main.cpp';
        $tmp = tempnam(sys_get_temp_dir(), 'o360bundle');

        if ($tmp === false) {
            throw new RuntimeException('Không tạo được tệp tạm để dựng gói chấm.');
        }

        $zip = new ZipArchive();

        if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($tmp);

            throw new RuntimeException('Không mở được tệp ZIP để dựng gói chấm.');
        }

        $zip->addFromString($sourceName, $sourceCode);
        $this->addScript($zip, 'compile', $this->compileScript($langKey));
        $this->addScript($zip, 'run', $this->runScript($langKey, count($testCases), $perTestSeconds, $runBudgetSeconds, $outputCapBytes));

        foreach (array_values($testCases) as $i => $tc) {
            $zip->addFromString('t'.($i + 1).'.in', (string) ($tc['input'] ?? ''));
        }

        $zip->close();

        $binary = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $binary;
    }

    /**
     * Bóc stdout của gói thành kết quả từng test.
     *
     * @return array<int, array{index:int, exitCode:int, ms:int, bytes:int, output:string}>
     */
    public function parse(string $stdout): array
    {
        $quoted = preg_quote($this->mark, '/');
        $parts = preg_split(
            '/^'.$quoted.' T (\d+) (-?\d+) (-?\d+) (\d+)$\R/m',
            $stdout,
            -1,
            PREG_SPLIT_DELIM_CAPTURE
        );

        if ($parts === false || count($parts) < 6) {
            return [];
        }

        $out = [];
        // $parts[0] là phần đầu (thường rỗng); sau đó cứ 5 phần tử là 1 test.
        for ($i = 1; isset($parts[$i + 4]); $i += 5) {
            $body = (string) $parts[$i + 4];

            // Cắt bỏ dòng mốc kết thúc và dấu xuống dòng mà script tự thêm vào.
            $endPos = strpos($body, $this->mark.' E ');
            if ($endPos !== false) {
                $body = substr($body, 0, $endPos);
                $body = preg_replace('/\R$/', '', $body) ?? $body;
            }

            $out[] = [
                'index' => (int) $parts[$i],
                'exitCode' => (int) $parts[$i + 1],
                'ms' => (int) $parts[$i + 2],
                // Kích thước THẬT của những gì bài in ra; 'output' có thể đã bị cắt bớt.
                'bytes' => (int) $parts[$i + 3],
                'output' => $body,
            ];
        }

        return $out;
    }

    private function addScript(ZipArchive $zip, string $name, string $body): void
    {
        $zip->addFromString($name, $body);
        // Judge0 chạy thẳng ./compile và ./run nên cờ thực thi PHẢI được ghi vào trong ZIP —
        // giải nén ra mà thiếu cờ này là lượt chấm hỏng ngay từ bước biên dịch.
        $zip->setExternalAttributesName($name, ZipArchive::OPSYS_UNIX, (0100755 << 16));
    }

    /**
     * Script biên dịch — chạy ĐÚNG 1 LẦN cho cả bài.
     *
     * Dò đường dẫn trình biên dịch thay vì gán cứng: ảnh Judge0 để các bộ biên dịch trong thư
     * mục /usr/local/gcc-<phiên bản>/bin chứ không phải lúc nào cũng có sẵn trong PATH, và số
     * phiên bản đổi theo từng bản ảnh.
     */
    private function compileScript(string $langKey): string
    {
        if ($langKey === 'python') {
            return <<<'SH'
#!/bin/sh
PY=$(command -v python3 2>/dev/null)
[ -z "$PY" ] && PY=$(ls -d /usr/local/python-*/bin/python3 2>/dev/null | tail -1)
[ -z "$PY" ] && { echo "Khong tim thay python3 tren may cham." >&2; exit 1; }
echo "$PY" > .pybin
"$PY" -m py_compile main.py || exit 1
exit 0
SH;
        }

        return <<<'SH'
#!/bin/sh
CXX=$(command -v g++ 2>/dev/null)
[ -z "$CXX" ] && CXX=$(ls -d /usr/local/gcc-*/bin/g++ 2>/dev/null | tail -1)
[ -z "$CXX" ] && { echo "Khong tim thay g++ tren may cham." >&2; exit 1; }
"$CXX" -O2 -std=c++17 -o main main.cpp || exit 1
exit 0
SH;
    }

    /**
     * Script chạy — lặp qua từng test, in kết quả kèm mốc phân tách.
     *
     * Hai điều bắt buộc phải làm, vì gộp 20 test vào MỘT lượt chạy làm mất hai thứ mà cách chấm
     * cũ vốn được Judge0 cho không:
     *
     * 1. MỖI TEST MỘT THƯ MỤC RIÊNG. Cách cũ mỗi test là một hộp cách ly mới tinh nên luôn sạch.
     *    Gộp lại thì 20 test dùng chung một thư mục, mà đề kiểu HSG có file_io (TONG.INP/
     *    TONG.OUT) thì chương trình GHI RA FILE: test 7 ghi TONG.OUT xong, test 8 sập trước khi
     *    kịp ghi, thế là file cũ của test 7 bị lấy làm kết quả của test 8 — chấm sai mà không
     *    dấu vết. Chạy trong thư mục con rồi xoá đi là hết đường lẫn.
     *
     * 2. MỖI TEST MỘT ĐỒNG HỒ RIÊNG. Giới hạn thời gian của Judge0 giờ áp cho CẢ gói, nên không
     *    tự chặn từng test thì một bài lặp vô hạn ăn sạch ngân sách của 19 test còn lại.
     *
     *    SỬA 24/9 (khách: "lỡ học sinh viết chạy vô hạn thì sao, chắc lag máy luôn") — tự canh
     *    giờ bằng tiến trình nền `sleep` + `kill -9` thay vì gọi `timeout`. Không phải vì
     *    `timeout` tồi, mà vì nó là MỘT ĐIỀU KIỆN CÓ THỂ THIẾU: hộp cách ly của Judge0 không
     *    hứa có coreutils, mà nhánh "không có timeout" thì gần như không bao giờ chạy nên hỏng
     *    lúc nào không ai biết. Một đường chạy duy nhất, thử được, hơn hai đường mà một đường
     *    chỉ nằm chờ. `kill -9` là SIGKILL — chương trình KHÔNG bắt hay lờ đi được.
     *
     * 3. CHẶN LƯỢNG IN RA (`ulimit -f`). Vòng lặp vô hạn NGUY HIỂM NHẤT không phải loại treo im
     *    mà loại vừa lặp vừa in: `stdout.txt` phình tới lúc đầy đĩa của hộp cách ly, Judge0 hỏng
     *    cả lượt chấm, và máy chủ è cổ ghi vài GB rác. Vượt hạn mức thì hệ điều hành bắn SIGXFSZ
     *    giết ngay tiến trình đó, các test còn lại vẫn chạy bình thường.
     *
     * 4. NGÂN SÁCH CHUNG CHO CẢ LƯỢT. Nếu vì lý do nào đó các test vẫn chạy lâu hơn dự tính, hết
     *    ngân sách là ghi nốt các test còn lại thành "quá thời gian" rồi thoát ĐẸP. Thà trả về
     *    kết quả đọc được còn hơn để Judge0 giết ngang cả gói — lúc đó không đọc được test nào
     *    và phải chấm lại từ đầu bằng đường cũ, tốn gấp nhiều lần.
     */
    private function runScript(string $langKey, int $count, int $perTestSeconds, int $runBudgetSeconds, int $outputCapBytes): string
    {
        // Chạy từ trong thư mục con nên đường dẫn phải lùi một cấp.
        $exec = $langKey === 'python' ? '"$PY" ../main.py' : '../main';

        $prepare = $langKey === 'python'
            ? <<<'SH'
PY=$(cat .pybin 2>/dev/null)
[ -z "$PY" ] && PY=$(command -v python3 2>/dev/null)
[ -z "$PY" ] && PY=$(ls -d /usr/local/python-*/bin/python3 2>/dev/null | tail -1)
SH
            : '';

        $template = <<<'SH'
#!/bin/sh
MARK='__MARK__'
TL=__TL__
N=__N__
BUDGET=__BUDGET__
FLIMIT=__FLIMIT__
OUTCAP=__OUTCAP__
__PREPARE__

START=$(date +%s 2>/dev/null)
case "$START" in ''|*[!0-9]*) START=0 ;; esac

# Ghi nốt các test chưa chạy thành "quá thời gian" (mã 124) rồi thoát đẹp.
emit_rest() {
    k=$1
    while [ "$k" -le "$N" ]; do
        printf '%s T %s 124 -1 0\n' "$MARK" "$k"
        printf '\n%s E %s\n' "$MARK" "$k"
        k=$((k + 1))
    done
}

i=1
while [ "$i" -le "$N" ]; do
    NOW=$(date +%s 2>/dev/null)
    case "$NOW" in ''|*[!0-9]*) NOW=$START ;; esac

    if [ "$START" -gt 0 ] && [ $((NOW - START)) -ge "$BUDGET" ]; then
        emit_rest "$i"
        break
    fi

    W="w$i"
    rm -rf "$W" 2>/dev/null
    mkdir -p "$W" || exit 1

    S=$(date +%s%N 2>/dev/null)

    # ulimit -f tính theo khối 512 byte; exec để $! trỏ ĐÚNG tiến trình bài làm,
    # không phải cái vỏ shell bọc ngoài — có vậy kill -9 mới trúng.
    (
        cd "$W" || exit 1
        ulimit -f "$FLIMIT" 2>/dev/null
        exec __EXEC__ < "../t$i.in" > stdout.txt 2> stderr.txt
    ) &
    PID=$!

    ( sleep "$TL"; kill -9 "$PID" 2>/dev/null ) &
    KILLER=$!

    wait "$PID"
    RC=$?

    kill -9 "$KILLER" 2>/dev/null
    wait "$KILLER" 2>/dev/null

    E=$(date +%s%N 2>/dev/null)
    MS=-1
    case "$S" in ''|*[!0-9]*) ;; *) case "$E" in ''|*[!0-9]*) ;; *) MS=$(( (E - S) / 1000000 )) ;; esac ;; esac

    # Đo NGUYÊN kích thước nhưng chỉ in phần đầu: bài lặp vô hạn mà in có thể đẻ ra hàng MB,
    # nhồi hết vào kết quả trả về là ép Judge0 và máy chủ cõng đống rác. Con số SZ để phía PHP
    # biết bài đã in vượt mức mà xử đúng, thay vì so nhầm một chuỗi bị cắt dở.
    SZ=$(wc -c < "$W/stdout.txt" 2>/dev/null | tr -d ' ')
    case "$SZ" in ''|*[!0-9]*) SZ=0 ;; esac

    printf '%s T %s %s %s %s\n' "$MARK" "$i" "$RC" "$MS" "$SZ"
    head -c "$OUTCAP" "$W/stdout.txt" 2>/dev/null
    printf '\n%s E %s\n' "$MARK" "$i"

    rm -rf "$W" 2>/dev/null
    i=$((i + 1))
done
exit 0
SH;

        return str_replace(
            ['__MARK__', '__TL__', '__N__', '__BUDGET__', '__FLIMIT__', '__OUTCAP__', '__PREPARE__', '__EXEC__'],
            [
                $this->mark,
                (string) max(1, $perTestSeconds),
                (string) $count,
                (string) max(5, $runBudgetSeconds),
                // 8192 khối × 512 byte = 4 MB cho mỗi test. Bài in ra đúng đắn không bao giờ
                // chạm tới mức này; chạm tới nghĩa là đang lặp vô hạn mà in.
                '8192',
                (string) max(4096, $outputCapBytes),
                $prepare,
                $exec,
            ],
            $template
        );
    }
}

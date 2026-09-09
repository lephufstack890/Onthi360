<?php

namespace App\Support;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * SỬA 9/9 (khách: "thêm nút tải file Excel mẫu về để điền" + "nút upload file Excel đáp án") —
 * bộ GHI và ĐỌC tệp .xlsx tối giản, KHÔNG dùng thư viện ngoài.
 *
 * Vì sao không cài phpoffice/phpspreadsheet: server triển khai chạy `composer install` từ
 * composer.lock (không `composer require` trực tiếp trên máy chủ), thêm 1 gói nặng ~10MB kèm
 * phụ thuộc chỉ để đọc 3 cột A/B/C là không đáng. Tệp .xlsx thực chất là 1 gói ZIP chứa XML —
 * ZipArchive + SimpleXML đều là thứ dự án đã dùng sẵn (xem ContentService nhập gói ZIP câu hỏi).
 *
 * PHẠM VI CÓ Ý THỨC — lớp này CHỈ làm đúng phần cần cho phiếu đáp án, không phải bộ Excel đầy đủ:
 *   - Ghi: 1 sheet, toàn bộ ô là chuỗi nội tuyến (inlineStr) nên không cần bảng sharedStrings;
 *     hàng đầu in đậm; có đặt độ rộng cột cho dễ đọc.
 *   - Đọc: lấy sheet ĐẦU TIÊN theo thứ tự trong workbook; hiểu ô chuỗi dùng chung (t="s"),
 *     chuỗi nội tuyến (t="inlineStr"), chuỗi công thức (t="str") và số; công thức thì lấy giá trị
 *     đã tính sẵn (<v>), không tự tính lại; KHÔNG xử lý ngày tháng theo định dạng (không cần).
 *   - Không đọc .xls đời cũ (định dạng nhị phân khác hẳn) — chỗ gọi phải tự chặn đuôi tệp.
 */
class SimpleXlsx
{
    /**
     * Dựng nội dung nhị phân của 1 tệp .xlsx từ mảng hàng (mỗi hàng là mảng ô, mọi ô ghi dạng chuỗi).
     *
     * @param  array<int, array<int, string|int|float|null>>  $rows  hàng đầu tiên được coi là tiêu đề (in đậm)
     * @param  array<int, float>  $columnWidths  độ rộng cột (theo thứ tự A, B, C…), tuỳ chọn
     */
    public static function write(array $rows, string $sheetName = 'Sheet1', array $columnWidths = []): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        if ($tmp === false) {
            throw new RuntimeException('Không tạo được tệp tạm để dựng Excel.');
        }

        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Không mở được tệp tạm để dựng Excel.');
        }

        $zip->addFromString('[Content_Types].xml', self::contentTypesXml());
        $zip->addFromString('_rels/.rels', self::rootRelsXml());
        $zip->addFromString('xl/workbook.xml', self::workbookXml($sheetName));
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::workbookRelsXml());
        $zip->addFromString('xl/styles.xml', self::stylesXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', self::sheetXml($rows, $columnWidths));
        $zip->close();

        $binary = file_get_contents($tmp);
        @unlink($tmp);

        if ($binary === false) {
            throw new RuntimeException('Không đọc lại được tệp Excel vừa dựng.');
        }

        return $binary;
    }

    /**
     * Đọc sheet đầu tiên của 1 tệp .xlsx thành mảng hàng; ô trống trả về chuỗi rỗng, và mỗi hàng
     * được đệm cho đủ số cột của hàng dài nhất để chỗ gọi truy cập $row[0..n] không bị lỗi thiếu khoá.
     *
     * @return array<int, array<int, string>>
     */
    public static function readRows(string $absolutePath): array
    {
        $zip = new ZipArchive();
        if ($zip->open($absolutePath) !== true) {
            throw new RuntimeException('Không mở được tệp Excel — tệp có thể hỏng hoặc không phải .xlsx.');
        }

        $sheetPath = self::firstSheetPath($zip);
        $sheetXml = $zip->getFromName($sheetPath);
        if ($sheetXml === false) {
            $zip->close();
            throw new RuntimeException('Tệp Excel không có dữ liệu bảng tính nào đọc được.');
        }

        $shared = self::sharedStrings($zip);
        $zip->close();

        $sheet = @simplexml_load_string($sheetXml);
        if ($sheet === false) {
            throw new RuntimeException('Nội dung bảng tính trong tệp Excel không đọc được.');
        }

        $rows = [];
        $maxCols = 0;

        foreach ($sheet->sheetData->row ?? [] as $rowNode) {
            $cells = [];
            foreach ($rowNode->c ?? [] as $cellNode) {
                $index = self::columnIndex((string) $cellNode['r']);
                $cells[$index] = self::cellValue($cellNode, $shared);
            }

            if ($cells === []) {
                $rows[] = [];

                continue;
            }

            $maxCols = max($maxCols, max(array_keys($cells)) + 1);
            $rows[] = $cells;
        }

        // Đệm ô trống cho đủ cột + sắp lại theo đúng thứ tự cột (XML có thể bỏ qua ô rỗng).
        return array_map(function (array $cells) use ($maxCols) {
            $full = array_fill(0, $maxCols, '');
            foreach ($cells as $i => $v) {
                $full[$i] = $v;
            }

            return $full;
        }, $rows);
    }

    // ───────────────────────── đọc ─────────────────────────

    /** Đường dẫn sheet ĐẦU TIÊN theo thứ tự khai báo trong workbook (không đoán "sheet1.xml"). */
    private static function firstSheetPath(ZipArchive $zip): string
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($workbook !== false && $rels !== false) {
            $wb = @simplexml_load_string($workbook);
            $rl = @simplexml_load_string($rels);

            if ($wb !== false && $rl !== false) {
                $firstSheet = $wb->sheets->sheet[0] ?? null;
                if ($firstSheet !== null) {
                    $rid = (string) $firstSheet->attributes('r', true)['id'];
                    foreach ($rl->Relationship as $rel) {
                        if ((string) $rel['Id'] === $rid) {
                            $target = ltrim((string) $rel['Target'], '/');

                            return str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
                        }
                    }
                }
            }
        }

        return 'xl/worksheets/sheet1.xml';
    }

    /** @return array<int, string> */
    private static function sharedStrings(ZipArchive $zip): array
    {
        $raw = $zip->getFromName('xl/sharedStrings.xml');
        if ($raw === false) {
            return [];
        }

        $xml = @simplexml_load_string($raw);
        if ($xml === false) {
            return [];
        }

        $out = [];
        foreach ($xml->si as $si) {
            // Chuỗi có thể bị chẻ thành nhiều đoạn <r><t> khi định dạng khác nhau giữa các ký tự.
            $out[] = isset($si->t) && count($si->r ?? []) === 0
                ? (string) $si->t
                : implode('', array_map(fn ($r) => (string) $r->t, iterator_to_array($si->r ?? [])));
        }

        return $out;
    }

    /** @param  array<int, string>  $shared */
    private static function cellValue(SimpleXMLElement $cell, array $shared): string
    {
        $type = (string) ($cell['t'] ?? '');

        if ($type === 's') {
            $i = (int) ((string) $cell->v);

            return $shared[$i] ?? '';
        }

        if ($type === 'inlineStr') {
            return isset($cell->is->t)
                ? (string) $cell->is->t
                : implode('', array_map(fn ($r) => (string) $r->t, iterator_to_array($cell->is->r ?? [])));
        }

        // 'str' (kết quả công thức dạng chuỗi), số, hoặc không khai báo kiểu -> lấy thẳng <v>.
        return isset($cell->v) ? (string) $cell->v : '';
    }

    /** "B7" -> 1 (chỉ số cột bắt đầu từ 0). */
    private static function columnIndex(string $ref): int
    {
        $letters = preg_replace('/[^A-Za-z]/', '', $ref) ?? '';
        $letters = strtoupper($letters);

        $index = 0;
        foreach (str_split($letters) as $ch) {
            $index = $index * 26 + (ord($ch) - 64);
        }

        return max(0, $index - 1);
    }

    // ───────────────────────── ghi ─────────────────────────

    /** @param array<int, array<int, string|int|float|null>> $rows */
    private static function sheetXml(array $rows, array $columnWidths): string
    {
        $cols = '';
        if ($columnWidths !== []) {
            $cols .= '<cols>';
            foreach (array_values($columnWidths) as $i => $w) {
                $cols .= '<col min="'.($i + 1).'" max="'.($i + 1).'" width="'.$w.'" customWidth="1"/>';
            }
            $cols .= '</cols>';
        }

        $body = '';
        foreach (array_values($rows) as $r => $row) {
            $rowNo = $r + 1;
            $body .= '<row r="'.$rowNo.'">';
            foreach (array_values($row) as $c => $value) {
                $ref = self::columnLetters($c).$rowNo;
                $style = $r === 0 ? ' s="1"' : '';
                $text = htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_XML1, 'UTF-8');
                // Mọi ô ghi dạng chuỗi nội tuyến: giữ nguyên "0.25", "01", "Đ S Đ S" không bị Excel
                // tự đổi thành số/ngày khi mở lên.
                $body .= '<c r="'.$ref.'"'.$style.' t="inlineStr"><is><t xml:space="preserve">'.$text.'</t></is></c>';
            }
            $body .= '</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .$cols
            .'<sheetData>'.$body.'</sheetData>'
            .'</worksheet>';
    }

    private static function columnLetters(int $index): string
    {
        $letters = '';
        $index++;
        while ($index > 0) {
            $rem = ($index - 1) % 26;
            $letters = chr(65 + $rem).$letters;
            $index = intdiv($index - 1, 26);
        }

        return $letters;
    }

    private static function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';
    }

    private static function rootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private static function workbookXml(string $sheetName): string
    {
        $name = htmlspecialchars(mb_substr($sheetName, 0, 31), ENT_QUOTES | ENT_XML1, 'UTF-8');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.$name.'" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';
    }

    private static function workbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    /**
     * 2 kiểu ô: 0 = thường, 1 = in đậm (hàng tiêu đề).
     * Khai báo đầy đủ fills/borders/cellStyles theo đúng chuẩn OOXML — thiếu mấy phần này Excel
     * bản Windows có thể báo "cần sửa chữa tệp" khi mở, dù công cụ khác vẫn đọc bình thường.
     */
    private static function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2"><font><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/></font>'
            .'<font><b/><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/></font></fonts>'
            .'<fills count="2"><fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="2">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }
}

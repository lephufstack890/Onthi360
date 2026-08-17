<?php

namespace App\Support;

use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\ExecutableFinder;

/**
 * Trích xuất văn bản thật từ file .docx / .pdf cho luồng "Nhập đề" (6.4).
 *
 * .docx: tự đọc trực tiếp (ZipArchive + XML) — không phụ thuộc gói ngoài.
 * .pdf có lớp văn bản: dùng `pdftotext` (poppler-utils).
 * .pdf scan/ảnh: rasterize từng trang bằng `pdftoppm` rồi OCR bằng `tesseract`
 * (bắt buộc gói ngôn ngữ "vie" — nếu máy chủ chưa cài, báo lỗi rõ ràng thay vì
 * âm thầm OCR sai bằng tiếng Anh).
 *
 * LƯU Ý VẬN HÀNH: pdftotext/pdftoppm/tesseract là chương trình ngoài cần được
 * cài sẵn trên máy chủ chạy PHP (không phải máy phát triển). Nếu thiếu, mỗi
 * hàm bên dưới sẽ ném RuntimeException với thông điệp tiếng Việt đủ rõ để cài
 * đặt đúng gói, thay vì để tài liệu "treo" không rõ lý do.
 */
class DocumentTextExtractor
{
    /** @return array{text: string, usedOcr: bool, pages?: array} */
    public function extractDocx(string $absolutePath): array
    {
        $zip = new \ZipArchive();

        if ($zip->open($absolutePath) !== true) {
            throw new \RuntimeException('Không mở được tệp .docx — tệp có thể bị hỏng hoặc không đúng định dạng.');
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            throw new \RuntimeException('Tệp .docx không có cấu trúc chuẩn (thiếu word/document.xml).');
        }

        // Giữ ranh giới đoạn/dòng trước khi bóc thẻ XML, nếu không mọi câu sẽ dính liền nhau.
        $xml = str_replace(['</w:p>', '</w:tr>'], ["</w:p>\n", "</w:tr>\n"], $xml);
        $xml = preg_replace('/<w:tab\/>/', "\t", $xml) ?? $xml;
        $xml = preg_replace('/<w:br\/>/', "\n", $xml) ?? $xml;

        $text = strip_tags($xml);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $text = preg_replace('/[ \t]+\n/', "\n", $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return ['text' => trim($text), 'usedOcr' => false];
    }

    /** @return array{text: string, usedOcr: bool, pages?: array} */
    public function extractPdf(string $absolutePath): array
    {
        $pdftotext = $this->resolve('pdftotext');

        if ($pdftotext === null) {
            throw new \RuntimeException('Thiếu công cụ "pdftotext" (gói poppler-utils) trên máy chủ — cài đặt rồi thử lại (vd: apt-get install poppler-utils, hoặc brew install poppler).');
        }

        $result = Process::timeout(60)->run([$pdftotext, '-layout', '-enc', 'UTF-8', $absolutePath, '-']);
        $text = $result->successful() ? trim($result->output()) : '';

        $pageCount = $this->countPages($absolutePath);
        $charsPerPage = $pageCount > 0 ? mb_strlen($text) / $pageCount : mb_strlen($text);

        // PDF có lớp văn bản thật thường cho hàng trăm ký tự/trang; dưới ngưỡng này
        // nhiều khả năng là PDF scan/ảnh (chỉ có hình, không có lớp text) → chuyển OCR.
        if ($charsPerPage >= 20) {
            return ['text' => $text, 'usedOcr' => false];
        }

        return $this->ocrPdf($absolutePath, $pageCount);
    }

    /** @return array{text: string, usedOcr: bool, pages: array} */
    private function ocrPdf(string $absolutePath, int $pageCount): array
    {
        $pdftoppm = $this->resolve('pdftoppm');
        $tesseract = $this->resolve('tesseract');

        if ($pdftoppm === null || $tesseract === null) {
            throw new \RuntimeException('Tệp có vẻ là PDF scan/ảnh (không có lớp văn bản) nên cần OCR, nhưng máy chủ đang thiếu "pdftoppm" và/hoặc "tesseract" — cài đặt rồi thử lại.');
        }

        if (! $this->tesseractHasVietnamese($tesseract)) {
            throw new \RuntimeException('Tệp cần OCR tiếng Việt nhưng máy chủ chưa cài gói ngôn ngữ "vie" cho Tesseract (cài "tesseract-ocr-vie" trên Linux hoặc "tesseract-lang" qua Homebrew trên macOS) — OCR bằng tiếng Anh sẽ cho ra kết quả sai, nên tạm dừng thay vì tạo dữ liệu rác.');
        }

        $tmpDir = sys_get_temp_dir().'/onthi360_import_'.bin2hex(random_bytes(6));
        mkdir($tmpDir, 0700, true);

        try {
            $rasterize = Process::timeout(180)->run([$pdftoppm, '-r', '200', '-png', $absolutePath, $tmpDir.'/page']);

            if (! $rasterize->successful()) {
                throw new \RuntimeException('Không tách được ảnh từ PDF để chạy OCR ('.trim($rasterize->errorOutput()).').');
            }

            $images = glob($tmpDir.'/page-*.png') ?: [];
            sort($images);

            if ($images === []) {
                throw new \RuntimeException('Không tách được trang nào từ PDF để chạy OCR.');
            }

            $pagesText = [];
            $pagesMeta = [];
            foreach ($images as $i => $image) {
                $ocr = Process::timeout(120)->run([$tesseract, $image, 'stdout', '-l', 'vie']);
                $pageText = $ocr->successful() ? trim($ocr->output()) : '';
                $pagesText[] = $pageText;
                $pagesMeta[] = ['page' => $i + 1, 'chars' => mb_strlen($pageText)];
            }

            return [
                'text' => trim(implode("\n\n", $pagesText)),
                'usedOcr' => true,
                'pages' => $pagesMeta,
            ];
        } finally {
            foreach (glob($tmpDir.'/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($tmpDir);
        }
    }

    private function countPages(string $absolutePath): int
    {
        $pdfinfo = $this->resolve('pdfinfo');

        if ($pdfinfo === null) {
            return 1;
        }

        $result = Process::timeout(20)->run([$pdfinfo, $absolutePath]);

        if ($result->successful() && preg_match('/Pages:\s*(\d+)/', $result->output(), $m)) {
            return max(1, (int) $m[1]);
        }

        return 1;
    }

    private function tesseractHasVietnamese(string $tesseractBin): bool
    {
        $result = Process::timeout(15)->run([$tesseractBin, '--list-langs']);

        return $result->successful() && str_contains($result->output(), 'vie');
    }

    private function resolve(string $binary): ?string
    {
        return (new ExecutableFinder())->find($binary);
    }
}

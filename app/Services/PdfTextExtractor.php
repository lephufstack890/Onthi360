<?php

namespace App\Services;

use Smalot\PdfParser\Parser;
use Throwable;

/**
 * SỬA 3/9 (khách chốt: "hiển thị thẳng đề bài dạng text, khỏi hiển thị file") — trích chữ thô
 * từ statement.pdf của câu hỏi nhập ZIP để dùng THẲNG làm Question::body, thay vì chỉ ghi 1
 * dòng ghi chú bắt học sinh mở file riêng (xem Admin\ContentService::placeholderBodyForZipImport()
 * /Teacher\QuestionService::placeholderBodyForZipImport() — 2 nơi DUY NHẤT gọi lớp này).
 *
 * Dùng smalot/pdfparser (thư viện PHP thuần, không cần cài binary ngoài như poppler/pdftotext)
 * — CHỈ trích được PDF có lớp text thật (PDF xuất từ Word/LaTeX/CKEditor...), KHÔNG đọc được
 * PDF chỉ là ảnh scan (cần OCR, ngoài phạm vi tính năng này). Trích lỗi/rỗng → trả về null,
 * bên gọi tự quyết định dùng dòng ghi chú cũ làm phương án dự phòng thay vì để body rỗng (rỗng
 * sẽ bị QuestionPublishGuard chặn phát hành).
 *
 * YÊU CẦU: chạy `composer require smalot/pdfparser` trước khi dùng lớp này — chưa cài thì
 * extractText() luôn trả về null (catch Throwable bắt luôn cả lỗi "Class not found").
 */
class PdfTextExtractor
{
    public function extractText(string $pdfContent): ?string
    {
        try {
            $text = trim((new Parser())->parseContent($pdfContent)->getText());
        } catch (Throwable) {
            return null;
        }

        return $text !== '' ? $text : null;
    }

    /**
     * Chuyển text thô (mỗi đoạn cách nhau 1 dòng trống) thành HTML an toàn để lưu thẳng vào
     * Question::body — nơi HIỂN THỊ luôn coi body là HTML dựng sẵn ({!! !!}, xem by-question-
     * play.blade.php/exercise-play.blade.php...), KHÔNG tự escape lại, nên PHẢI escape ở đây
     * trước khi trả về (tránh PDF có ký tự "<"/"&" phá layout, hoặc tệ hơn là lỗi tự chèn HTML/
     * script nếu ai đó cố tình đặt tên/nội dung PDF chứa mã độc).
     */
    public function toBodyHtml(string $plainText): string
    {
        $paragraphs = preg_split('/\n{2,}/', trim($plainText)) ?: [];

        return collect($paragraphs)
            ->map(fn ($p) => trim($p))
            ->filter(fn ($p) => $p !== '')
            ->map(fn ($p) => '<p>'.nl2br(e($p)).'</p>')
            ->implode('');
    }
}

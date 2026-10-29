<?php

namespace App\Enums;

enum AssessmentContentMode: string
{
    case Structured = 'structured';

    case PdfAnswerSheet = 'pdf_answer_sheet';

    case Programming = 'programming';

    public function label(): string
    {
        return match ($this) {
            self::Structured => 'Câu hỏi rời (kho câu hỏi)',
            self::PdfAnswerSheet => 'PDF + phiếu đáp án',
            self::Programming => 'Lập trình (online judge)',
        };
    }
}

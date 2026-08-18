<?php

namespace App\Enums;

enum AssessmentType: string
{
    case Practice = 'practice';
    case Assignment = 'assignment';
    case Exam = 'exam';
    case CompetitionPaper = 'competition_paper';

    /**
     * Nhãn tiếng Việt hiển thị ở badge/icon (VD. resources/views/student/practice/index.blade.php)
     * — trước đây view tự map icon bằng 1 mảng key theo nhãn CÂU HỎI ('Lập trình'/'Trắc
     * nghiệm'/'Điền đáp án', tức App\Enums\QuestionType) trong khi giá trị thật truyền vào lại
     * là AssessmentType ('practice'/'assignment'/'exam'/'competition_paper') — 2 enum khác
     * nhau nên lookup luôn trượt, badge hiển thị thẳng string thô ("practice") thay vì tiếng
     * Việt. Đưa nhãn/icon vào enum để chỉ có 1 nguồn sự thật, dùng lại được ở bất kỳ view nào.
     */
    public function label(): string
    {
        return match ($this) {
            self::Practice => 'Tự luyện',
            self::Assignment => 'Bài tập',
            self::Exam => 'Đề thi',
            self::CompetitionPaper => 'Đề thi cuộc thi',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Practice => '📝',
            self::Assignment => '📗',
            self::Exam => '🧾',
            self::CompetitionPaper => '🏆',
        };
    }
}

<?php

namespace App\Enums;

/**
 * Loại tài nguyên gắn vào 1 buổi học cụ thể (note họp 13/8, mục 3): tài liệu/câu hỏi/đề thi
 * tham chiếu bản ghi có sẵn (đã được teacher tạo/gắn lớp từ trước); video/link/note nhập tay.
 */
enum SessionResourceType: string
{
    case Material = 'material';
    case Question = 'question';
    case Assessment = 'assessment';
    case Video = 'video';
    case Link = 'link';
    case Note = 'note';

    public function label(): string
    {
        return match ($this) {
            self::Material => 'Tài liệu',
            self::Question => 'Câu hỏi',
            self::Assessment => 'Đề thi / bài tập',
            self::Video => 'Video',
            self::Link => 'Link',
            self::Note => 'Ghi chú',
        };
    }

    /** true nếu loại này tham chiếu 1 bản ghi có sẵn (material/question/assessment) thay vì nhập tay (video/link/note). */
    public function isCatalogReference(): bool
    {
        return match ($this) {
            self::Material, self::Question, self::Assessment => true,
            default => false,
        };
    }
}

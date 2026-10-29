<?php

namespace App\Enums;

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

    public function isCatalogReference(): bool
    {
        return match ($this) {
            self::Material, self::Question, self::Assessment => true,
            default => false,
        };
    }
}

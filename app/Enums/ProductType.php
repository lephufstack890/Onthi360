<?php

namespace App\Enums;

enum ProductType: string
{
    case Book = 'book';
    case Topic = 'topic';
    case Exam = 'exam';
    case Course = 'course';

    public function chapterLabel(): ?string
    {
        return match ($this) {
            self::Book => 'Chương',
            self::Topic => 'Phần',
            self::Exam => 'Đề',
            self::Course => null,
        };
    }
}

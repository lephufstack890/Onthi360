<?php

namespace App\Enums;

enum ProductType: string
{
    case Book = 'book';
    case Topic = 'topic';
    case Exam = 'exam';
    case Course = 'course';
}

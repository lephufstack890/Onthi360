<?php

namespace App\Enums;

enum PublishAnswerRule: string
{
    case Never = 'never';
    case AfterDeadline = 'after_deadline';
    case Immediately = 'immediately';
}

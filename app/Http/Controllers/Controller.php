<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * AuthorizesRequests thêm $this->authorize()/$this->authorizeForUser() cho MỌI controller
 * (Laravel 11+ không còn tự gắn trait này vào Controller gốc mặc định). Cần cho
 * ReviewController::form() gọi ReviewPolicy::update() khi học sinh mở lại form để SỬA đánh
 * giá cũ (trước đây ReviewPolicy tồn tại nhưng chưa nơi nào trong app thực sự gọi tới).
 */
abstract class Controller
{
    use AuthorizesRequests;
}

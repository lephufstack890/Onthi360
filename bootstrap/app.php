<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureHasRole::class,
            'permission' => \App\Http\Middleware\EnsureHasPermission::class,
            'teacher.approved' => \App\Http\Middleware\EnsureTeacherApproved::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Form Liên hệ (info.contact.store) có throttle:5,1 (routes/web.php) để chặn spam —
        // khi bị chặn, quay lại đúng trang Thông tin kèm toast dễ hiểu thay vì trang lỗi 429
        // mặc định của Laravel. Trả về null (không xử lý) cho mọi route khác để giữ hành vi
        // mặc định nếu sau này có thêm throttle ở nơi khác.
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if ($request->routeIs('info.contact.store')) {
                return redirect(route('info.index').'#lien-he')->with('status', 'contact-throttled');
            }

            return null;
        });
    })->create();

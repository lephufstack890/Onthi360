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
        /*
         * SỬA 18/9 (khách báo: bấm "Chạy test"/"Ghi nhận bài làm" trên site thật báo không gửi
         * được yêu cầu) — site chạy SAU PROXY: proxy kết thúc TLS rồi nói chuyện với PHP bằng
         * http. Không tin proxy thì Laravel tưởng mọi request đều là http và sinh URL tuyệt đối
         * "http://tinhoc..." ngay giữa trang https. Trình duyệt CHẶN CỨNG fetch() từ trang https
         * sang http (mixed content) nên yêu cầu không bao giờ rời trình duyệt — vì vậy cả nhật ký
         * nginx lẫn laravel.log đều sạch trơn, rất khó lần ra.
         *
         * Tin proxy còn trả lại IP THẬT của người dùng cho log/throttle (trước đó mọi request
         * đều mang IP của proxy, nên throttle:5,1 ở form Liên hệ thực chất đang đếm chung cho
         * tất cả mọi người).
         *
         * at: '*' = tin mọi proxy. Đúng cho hệ thống này vì PHP chỉ tiếp nhận request đi qua
         * proxy; nếu sau này mở cho truy cập thẳng thì phải đổi thành danh sách IP proxy cụ thể,
         * không thì client tự đặt X-Forwarded-Proto để nói dối được.
         */
        $middleware->trustProxies(at: '*');

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

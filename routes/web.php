<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Khung route theo vai trò
|--------------------------------------------------------------------------
| Đây là khung tối giản minh họa cách nhóm route theo không gian (4.2 của
| BA spec): public / student / teacher / parent / admin. Nhóm admin đã có
| đủ route trả view (chưa nối controller/DB thật — xem TODO trong từng
| file resources/views/admin/**).
|
| Middleware 'role:...' cần đăng ký alias 1 lần trong bootstrap/app.php:
|   use App\Http\Middleware\EnsureHasRole;
|   ->withMiddleware(function (Middleware $middleware) {
|       $middleware->alias(['role' => EnsureHasRole::class]);
|   })
*/

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', function () {
        // TODO: điều hướng theo vai trò hiện tại của user (role switcher — 4.3).
        return view('dashboard');
    })->name('dashboard');

    Route::middleware(['role:teacher'])->prefix('teacher')->name('teacher.')->group(function () {
        // Route::resource('classes', TeacherClassController::class);
        // Route::post('classes/{classRoom}/materials/{material}', AttachMaterialController::class)
        //     ->name('classes.materials.attach');
    });

    Route::middleware(['role:parent'])->prefix('parent')->name('parent.')->group(function () {
        // Route::get('children', ChildrenController::class)->name('children');
    });

    Route::middleware(['role:admin,super_admin'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', fn () => view('admin.dashboard'))->name('dashboard');

        // Người dùng + phê duyệt giáo viên (ADM-02, 3.3)
        Route::get('users', fn () => view('admin.users.index'))->name('users.index');
        Route::get('users/{user}', fn ($user) => view('admin.users.show'))->name('users.show');
        Route::get('teacher-approvals', fn () => view('admin.teacher-approvals.index'))->name('teacher-approvals.index');
        Route::get('teacher-approvals/{teacherApproval}', fn ($teacherApproval) => view('admin.teacher-approvals.show'))->name('teacher-approvals.show');

        // Nội dung (ADM-03, 6.2/6.4/6.5)
        Route::get('content', fn () => view('admin.content.index'))->name('content.index');
        Route::get('content/{content}', fn ($content) => view('admin.content.show'))->name('content.show');

        // Khóa & Lớp (8.1)
        Route::get('courses', fn () => view('admin.courses.index'))->name('courses.index');

        // Sản phẩm & Quyền (ADM-03, 5.1, 7.1-7.5)
        Route::get('products', fn () => view('admin.products.index'))->name('products.index');
        Route::get('products/{product}', fn ($product) => view('admin.products.show'))->name('products.show');
        Route::get('access-rights', fn () => view('admin.access-rights.index'))->name('access-rights.index');

        // Đơn hàng + Mã kích hoạt (ADM-04, 7.4)
        Route::get('orders', fn () => view('admin.orders.index'))->name('orders.index');
        Route::get('orders/{order}', fn ($order) => view('admin.orders.show'))->name('orders.show');
        Route::get('activation-codes', fn () => view('admin.activation-codes.index'))->name('activation-codes.index');

        // Đánh giá (ADM-06, 9.4)
        Route::get('reviews', fn () => view('admin.reviews.index'))->name('reviews.index');
        Route::get('reviews/{review}', fn ($review) => view('admin.reviews.show'))->name('reviews.show');

        // Cuộc thi, Giáo viên tiêu biểu, Bảng xếp hạng (ADM-05, 11.1/11.2)
        Route::get('competitions', fn () => view('admin.competitions.index'))->name('competitions.index');
        Route::get('featured-teachers', fn () => view('admin.featured-teachers.index'))->name('featured-teachers.index');
        Route::get('ranking', fn () => view('admin.ranking.index'))->name('ranking.index');

        // Báo cáo + Cấu hình
        Route::get('reports', fn () => view('admin.reports.index'))->name('reports.index');
        Route::get('settings', fn () => view('admin.settings.index'))->name('settings.index');
    });
});

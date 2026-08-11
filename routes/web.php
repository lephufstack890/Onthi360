<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Khung route theo vai trò
|--------------------------------------------------------------------------
| Đây là khung tối giản minh họa cách nhóm route theo không gian (4.2 của
| BA spec): public / student / teacher / parent / admin. Controller thật
| sẽ được thêm dần theo Chặng 1-5 (2.4).
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
        // Route::resource('teacher-approvals', TeacherApprovalController::class);
        // Route::resource('orders', AdminOrderController::class);
        // Route::resource('reviews', ReviewModerationController::class);
    });
});

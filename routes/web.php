<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

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
|
| Auth login/register/logout dưới đây là bản tối giản (chưa tách
| Controller riêng) chỉ để có luồng đăng nhập thật, phục vụ xem UI các
| khu vực sau đăng nhập. Khi viết Controller thật, chuyển các closure này
| sang App\Http\Controllers\Auth\* theo chuẩn Laravel, giữ nguyên route
| name để không phải sửa view.
*/

// -- Công khai (4.1) — khách xem được, không cần đăng nhập ------------------
Route::get('/', fn () => view('welcome'))->name('home');
Route::get('/khoa-hoc', fn () => view('public.courses.index'))->name('courses.index');
Route::get('/khoa-hoc/{course}', fn ($course) => view('public.courses.show'))->name('courses.show');
Route::get('/luyen-tap', fn () => view('public.practice.index'))->name('practice.index');
Route::get('/tai-lieu', fn () => view('public.materials.index'))->name('materials.index');
Route::get('/tai-lieu/{material}', fn ($material) => view('public.materials.show'))->name('materials.show');
Route::get('/cuoc-thi', fn () => view('public.competitions.index'))->name('competitions.index');
Route::get('/cuoc-thi/{competition}', fn ($competition) => view('public.competitions.show'))->name('competitions.show');
Route::get('/bang-xep-hang', fn () => view('public.leaderboard.index'))->name('leaderboard.index');
Route::get('/giao-vien-tieu-bieu', fn () => view('public.teachers.index'))->name('teachers.index');
Route::get('/giao-vien-tieu-bieu/{teacher}', fn ($teacher) => view('public.teachers.show'))->name('teachers.show');
Route::get('/thong-tin', fn () => view('public.info.index'))->name('info.index');

// -- Xác thực (ACC-01) -------------------------------------------------------
Route::middleware(['guest'])->group(function () {
    Route::get('/login', fn () => view('auth.login'))->name('login');

    Route::post('/login', function (Request $request) {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'Email hoặc mật khẩu không đúng.',
            ]);
        }

        $request->session()->regenerate();

        $user = Auth::user();

        // TODO: khi có role switcher thật (4.3), thay điều hướng cứng này
        // bằng màn chọn không gian nếu user có nhiều role.
        if ($user->hasAnyRole(Role::ADMIN, Role::SUPER_ADMIN)) {
            return redirect()->intended(route('admin.dashboard'));
        }

        return redirect()->intended(route('dashboard'));
    });

    Route::get('/register', fn () => view('auth.register'))->name('register');

    Route::post('/register', function (Request $request) {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        // TODO: gán role ban đầu theo lựa chọn thật của người dùng (3.1);
        // mặc định tạm gán Học sinh để không tạo user không có role nào.
        $user->assignRole(Role::STUDENT);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    });
});

Route::post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('home');
})->name('logout')->middleware('auth');

// -- Sau đăng nhập (4.2) ------------------------------------------------------
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

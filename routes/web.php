<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Student\AssessmentController as StudentAssessmentController;
use App\Http\Controllers\Student\ClassRoomController as StudentClassRoomController;
use App\Http\Controllers\Student\CourseController as StudentCourseController;
use App\Http\Controllers\Student\NotificationController as StudentNotificationController;
use App\Http\Controllers\Student\PracticeController as StudentPracticeController;
use App\Http\Controllers\Student\ProfileController as StudentProfileController;
use App\Http\Controllers\Teacher\AssessmentController as TeacherAssessmentController;
use App\Http\Controllers\Teacher\ClassRoomController as TeacherClassRoomController;
use App\Http\Controllers\Teacher\DashboardController as TeacherDashboardController;
use App\Http\Controllers\Teacher\QuestionController as TeacherQuestionController;
use App\Http\Controllers\Teacher\ResultController as TeacherResultController;
use App\Http\Controllers\Parent\ChildController as ParentChildController;
use App\Http\Controllers\Parent\DashboardController as ParentDashboardController;
use App\Http\Controllers\Access\AccessController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\Admin\AccessRightController as AdminAccessRightController;
use App\Http\Controllers\Admin\ProfileController as AdminProfileController;
use App\Http\Controllers\Admin\ActivationCodeController as AdminActivationCodeController;
use App\Http\Controllers\Admin\CompetitionController as AdminCompetitionController;
use App\Http\Controllers\Admin\ContentController as AdminContentController;
use App\Http\Controllers\Admin\CourseController as AdminCourseController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\FeaturedTeacherController as AdminFeaturedTeacherController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\RankingController as AdminRankingController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Admin\ReviewController as AdminReviewController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\TeacherApprovalController as AdminTeacherApprovalController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Khung route theo vai trò
|--------------------------------------------------------------------------
| public / student / teacher / parent / admin (4.2 BA spec). Auth,
| Dashboard và toàn bộ khu Học sinh đã nối Controller thật (Eloquent).
| Các khu Giáo viên/Phụ huynh/Đánh giá/Quyền truy cập/Admin còn lại vẫn
| trả view() trực tiếp qua closure — xem TODO trong từng file
| resources/views/** — sẽ nối Controller thật lần lượt tiếp theo.
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
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// -- Sau đăng nhập (4.2) ------------------------------------------------------
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // -- Học sinh (10.1) -----------------------------------------------------
    Route::middleware(['role:student'])->prefix('student')->name('student.')->group(function () {
        Route::get('courses', [StudentCourseController::class, 'index'])->name('courses.index');
        Route::get('classes/{class}', [StudentClassRoomController::class, 'show'])->name('classes.show');
        Route::get('practice', [StudentPracticeController::class, 'index'])->name('practice.index');
        Route::get('assessments/{assessment}/take', [StudentAssessmentController::class, 'take'])->name('assessment.take');
        Route::get('assessments/{question}/oj', [StudentAssessmentController::class, 'oj'])->name('assessment.oj');
        Route::get('attempts/{attempt}/result', [StudentAssessmentController::class, 'result'])->name('assessment.result');
        Route::get('notifications', [StudentNotificationController::class, 'index'])->name('notifications');
        Route::get('profile', [StudentProfileController::class, 'show'])->name('profile');
        Route::put('profile', [StudentProfileController::class, 'update'])->name('profile.update');
    });

    // -- Giáo viên (10.2) -----------------------------------------------------
    Route::middleware(['role:teacher'])->prefix('teacher')->name('teacher.')->group(function () {
        Route::get('/', [TeacherDashboardController::class, 'index'])->name('dashboard');
        Route::get('classes', [TeacherClassRoomController::class, 'index'])->name('classes.index');
        Route::middleware('teacher.approved')->group(function () {
            Route::get('classes/create', [TeacherClassRoomController::class, 'create'])->name('classes.create');
            Route::post('classes', [TeacherClassRoomController::class, 'store'])->name('classes.store');
        });
        Route::get('classes/{class}', [TeacherClassRoomController::class, 'show'])->name('classes.show');
        Route::get('questions', [TeacherQuestionController::class, 'index'])->name('questions.index');
        Route::get('questions/create', [TeacherQuestionController::class, 'create'])->name('questions.create');
        Route::get('assessments/create', [TeacherAssessmentController::class, 'create'])->name('assessments.create');
        Route::get('assessments/import', [TeacherAssessmentController::class, 'import'])->name('assessments.import');
        Route::get('assessments/review-draft', [TeacherAssessmentController::class, 'reviewDraft'])->name('assessments.reviewDraft');
        Route::get('results', [TeacherResultController::class, 'index'])->name('results.index');
    });

    // -- Phụ huynh (10.3) -----------------------------------------------------
    Route::middleware(['role:parent'])->prefix('parent')->name('parent.')->group(function () {
        Route::get('/', [ParentDashboardController::class, 'index'])->name('dashboard');
        Route::get('children', [ParentChildController::class, 'index'])->name('children.index');
        Route::get('children/{child}', [ParentChildController::class, 'show'])->name('children.show');
    });

    // -- Đánh giá sao / nhận xét trải nghiệm (mục 9) — dùng chung mọi vai trò --
    Route::prefix('danh-gia')->name('reviews.')->group(function () {
        Route::get('/', [ReviewController::class, 'index'])->name('index');
        Route::get('/viet', [ReviewController::class, 'form'])->name('form');
        Route::get('/chua-du-dieu-kien', [ReviewController::class, 'ineligible'])->name('ineligible');
        Route::get('/cua-toi', [ReviewController::class, 'myReviews'])->name('myReviews');
    });

    // -- Quyền truy cập / thanh toán (mục 7) ------------------------------------
    Route::prefix('quyen')->name('access.')->group(function () {
        Route::get('/dat-don/{product}', [AccessController::class, 'checkout'])->name('checkout');
        Route::get('/kich-hoat', [AccessController::class, 'activate'])->name('activate');
        Route::get('/cua-toi', [AccessController::class, 'myAccess'])->name('myAccess');
        Route::get('/khoa/{material}', [AccessController::class, 'blocked'])->name('blocked');
    });

    // -- Admin/Editor (4.2) -------------------------------------------------
    Route::middleware(['role:admin,super_admin'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

        // Người dùng + phê duyệt giáo viên (ADM-02, 3.3)
        Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('users/{user}', [AdminUserController::class, 'show'])->name('users.show');
        Route::get('teacher-approvals', [AdminTeacherApprovalController::class, 'index'])->name('teacher-approvals.index');
        Route::get('teacher-approvals/{teacherApproval}', [AdminTeacherApprovalController::class, 'show'])->name('teacher-approvals.show');
        Route::post('teacher-approvals/{teacherApproval}/approve', [AdminTeacherApprovalController::class, 'approve'])->name('teacher-approvals.approve');
        Route::post('teacher-approvals/{teacherApproval}/reject', [AdminTeacherApprovalController::class, 'reject'])->name('teacher-approvals.reject');
        Route::post('teacher-approvals/{teacherApproval}/suspend', [AdminTeacherApprovalController::class, 'suspend'])->name('teacher-approvals.suspend');
        Route::post('teacher-approvals/{teacherApproval}/reinstate', [AdminTeacherApprovalController::class, 'reinstate'])->name('teacher-approvals.reinstate');

        // Nội dung (ADM-03, 6.2/6.4/6.5)
        Route::get('content', [AdminContentController::class, 'index'])->name('content.index');

        Route::get('content/materials/create', [AdminContentController::class, 'materialsCreate'])->name('content.materials.create');
        Route::post('content/materials', [AdminContentController::class, 'materialsStore'])->name('content.materials.store');
        Route::get('content/materials/{material}/edit', [AdminContentController::class, 'materialsEdit'])->name('content.materials.edit');
        Route::put('content/materials/{material}', [AdminContentController::class, 'materialsUpdate'])->name('content.materials.update');
        Route::post('content/materials/{material}/publish', [AdminContentController::class, 'materialsPublish'])->name('content.materials.publish');
        Route::post('content/materials/{material}/reject', [AdminContentController::class, 'materialsReject'])->name('content.materials.reject');
        Route::post('content/materials/{material}/archive', [AdminContentController::class, 'materialsArchive'])->name('content.materials.archive');

        Route::get('content/questions/create', [AdminContentController::class, 'questionsCreate'])->name('content.questions.create');
        Route::post('content/questions', [AdminContentController::class, 'questionsStore'])->name('content.questions.store');
        Route::get('content/questions/{question}/edit', [AdminContentController::class, 'questionsEdit'])->name('content.questions.edit');
        Route::put('content/questions/{question}', [AdminContentController::class, 'questionsUpdate'])->name('content.questions.update');
        Route::post('content/questions/{question}/new-version', [AdminContentController::class, 'questionsNewVersion'])->name('content.questions.newVersion');
        Route::post('content/questions/{question}/publish', [AdminContentController::class, 'questionsPublish'])->name('content.questions.publish');
        Route::post('content/questions/{question}/reject', [AdminContentController::class, 'questionsReject'])->name('content.questions.reject');
        Route::post('content/questions/{question}/archive', [AdminContentController::class, 'questionsArchive'])->name('content.questions.archive');

        Route::get('content/assessments/create', [AdminContentController::class, 'assessmentsCreate'])->name('content.assessments.create');
        Route::post('content/assessments', [AdminContentController::class, 'assessmentsStore'])->name('content.assessments.store');
        Route::get('content/assessments/{assessment}/edit', [AdminContentController::class, 'assessmentsEdit'])->name('content.assessments.edit');
        Route::put('content/assessments/{assessment}', [AdminContentController::class, 'assessmentsUpdate'])->name('content.assessments.update');
        Route::post('content/assessments/{assessment}/publish', [AdminContentController::class, 'assessmentsPublish'])->name('content.assessments.publish');
        Route::post('content/assessments/{assessment}/reject', [AdminContentController::class, 'assessmentsReject'])->name('content.assessments.reject');
        Route::post('content/assessments/{assessment}/archive', [AdminContentController::class, 'assessmentsArchive'])->name('content.assessments.archive');

        Route::get('content/{content}', [AdminContentController::class, 'show'])->name('content.show');

        // Khóa & Lớp (8.1)
        Route::get('courses', [AdminCourseController::class, 'index'])->name('courses.index');
        Route::get('courses/create', [AdminCourseController::class, 'create'])->name('courses.create');
        Route::post('courses', [AdminCourseController::class, 'store'])->name('courses.store');
        Route::get('courses/{course}', [AdminCourseController::class, 'show'])->name('courses.show');
        Route::get('courses/{course}/edit', [AdminCourseController::class, 'edit'])->name('courses.edit');
        Route::put('courses/{course}', [AdminCourseController::class, 'update'])->name('courses.update');
        Route::delete('courses/{course}', [AdminCourseController::class, 'destroy'])->name('courses.destroy');
        Route::get('courses/{course}/classes/create', [AdminCourseController::class, 'classesCreate'])->name('courses.classes.create');
        Route::post('courses/{course}/classes', [AdminCourseController::class, 'classesStore'])->name('courses.classes.store');
        Route::get('classes/{classRoom}/edit', [AdminCourseController::class, 'classesEdit'])->name('classes.edit');
        Route::put('classes/{classRoom}', [AdminCourseController::class, 'classesUpdate'])->name('classes.update');
        Route::delete('classes/{classRoom}', [AdminCourseController::class, 'classesDestroy'])->name('classes.destroy');

        // Sản phẩm & Quyền (ADM-03, 5.1, 7.1-7.5)
        Route::get('products', [AdminProductController::class, 'index'])->name('products.index');
        Route::get('products/create', [AdminProductController::class, 'create'])->name('products.create');
        Route::post('products', [AdminProductController::class, 'store'])->name('products.store');
        Route::get('products/{product}/edit', [AdminProductController::class, 'edit'])->name('products.edit');
        Route::put('products/{product}', [AdminProductController::class, 'update'])->name('products.update');
        Route::delete('products/{product}', [AdminProductController::class, 'destroy'])->name('products.destroy');
        Route::get('products/{product}', [AdminProductController::class, 'show'])->name('products.show');

        Route::get('access-rights', [AdminAccessRightController::class, 'index'])->name('access-rights.index');
        Route::get('access-rights/create', [AdminAccessRightController::class, 'create'])->name('access-rights.create');
        Route::post('access-rights', [AdminAccessRightController::class, 'store'])->name('access-rights.store');
        Route::post('access-rights/{accessRight}/revoke', [AdminAccessRightController::class, 'revoke'])->name('access-rights.revoke');
        Route::get('access-rights/{accessRight}', [AdminAccessRightController::class, 'show'])->name('access-rights.show');

        // Đơn hàng + Mã kích hoạt (ADM-04, 7.4)
        Route::get('orders', [AdminOrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
        Route::get('activation-codes', [AdminActivationCodeController::class, 'index'])->name('activation-codes.index');

        // Đánh giá (ADM-06, 9.4)
        Route::get('reviews', [AdminReviewController::class, 'index'])->name('reviews.index');
        Route::get('reviews/{review}', [AdminReviewController::class, 'show'])->name('reviews.show');

        // Cuộc thi, Giáo viên tiêu biểu, Bảng xếp hạng (ADM-05, 11.1/11.2)
        Route::get('competitions', [AdminCompetitionController::class, 'index'])->name('competitions.index');
        Route::get('featured-teachers', [AdminFeaturedTeacherController::class, 'index'])->name('featured-teachers.index');
        Route::post('featured-teachers/{featuredTeacher}/feature', [AdminFeaturedTeacherController::class, 'feature'])->name('featured-teachers.feature');
        Route::post('featured-teachers/{featuredTeacher}/unfeature', [AdminFeaturedTeacherController::class, 'unfeature'])->name('featured-teachers.unfeature');
        Route::get('ranking', [AdminRankingController::class, 'index'])->name('ranking.index');

        // Báo cáo + Cấu hình
        Route::get('reports', [AdminReportController::class, 'index'])->name('reports.index');
        Route::get('settings', [AdminSettingsController::class, 'index'])->name('settings.index');

        // Tài khoản admin (hồ sơ + đổi mật khẩu) — ACC-01/ACC-02 áp cho khu Admin.
        Route::get('profile', [AdminProfileController::class, 'show'])->name('profile.show');
        Route::put('profile', [AdminProfileController::class, 'update'])->name('profile.update');
        Route::put('profile/password', [AdminProfileController::class, 'updatePassword'])->name('profile.password');
    });
});

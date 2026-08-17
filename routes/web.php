<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Public\CompetitionController as PublicCompetitionController;
use App\Http\Controllers\Public\CourseController as PublicCourseController;
use App\Http\Controllers\Public\HomeController as PublicHomeController;
use App\Http\Controllers\Public\InfoController as PublicInfoController;
use App\Http\Controllers\Public\ContactController as PublicContactController;
use App\Http\Controllers\Public\LeaderboardController as PublicLeaderboardController;
use App\Http\Controllers\Public\MaterialController as PublicMaterialController;
use App\Http\Controllers\Public\PracticeController as PublicPracticeController;
use App\Http\Controllers\Public\TeacherController as PublicTeacherController;
use App\Http\Controllers\Student\AssessmentController as StudentAssessmentController;
use App\Http\Controllers\Student\ClassRoomController as StudentClassRoomController;
use App\Http\Controllers\Student\CourseController as StudentCourseController;
use App\Http\Controllers\Student\NotificationController as StudentNotificationController;
use App\Http\Controllers\Student\PracticeController as StudentPracticeController;
use App\Http\Controllers\Student\ProfileController as StudentProfileController;
use App\Http\Controllers\Student\ScheduleController as StudentScheduleController;
use App\Http\Controllers\Teacher\AssessmentController as TeacherAssessmentController;
use App\Http\Controllers\Teacher\ClassRoomController as TeacherClassRoomController;
use App\Http\Controllers\Teacher\DashboardController as TeacherDashboardController;
use App\Http\Controllers\Teacher\QuestionController as TeacherQuestionController;
use App\Http\Controllers\Teacher\ResultController as TeacherResultController;
use App\Http\Controllers\Teacher\ScheduleController as TeacherScheduleController;
use App\Http\Controllers\Teacher\NotificationController as TeacherNotificationController;
use App\Http\Controllers\Teacher\ProfileController as TeacherProfileController;
use App\Http\Controllers\Parent\ChildController as ParentChildController;
use App\Http\Controllers\Parent\DashboardController as ParentDashboardController;
use App\Http\Controllers\Parent\NotificationController as ParentNotificationController;
use App\Http\Controllers\Parent\ProfileController as ParentProfileController;
use App\Http\Controllers\Parent\ResultController as ParentResultController;
use App\Http\Controllers\Parent\ScheduleController as ParentScheduleController;
use App\Http\Controllers\Access\AccessController;
use App\Http\Controllers\Access\WalletController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\Admin\AccessRightController as AdminAccessRightController;
use App\Http\Controllers\Admin\ProfileController as AdminProfileController;
use App\Http\Controllers\Admin\ActivationCodeController as AdminActivationCodeController;
use App\Http\Controllers\Admin\CompetitionController as AdminCompetitionController;
use App\Http\Controllers\Admin\ContactMessageController as AdminContactMessageController;
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
| public (mục 4.1: Khóa học, Luyện tập, Tài liệu, Cuộc thi) / student /
| teacher / parent / admin (4.2 BA spec). Auth, Dashboard, Học sinh, Giáo
| viên, Phụ huynh, Đánh giá, Quyền truy cập, Admin, 4 khu công khai chính
| (Khóa học/Luyện tập/Tài liệu/Cuộc thi), Bảng xếp hạng (11.2), Trang chủ
| Giáo viên tiêu biểu và Thông tin đã nối Controller thật (Eloquent). Toàn
| bộ 4 khu công khai + Trang chủ + Giáo viên tiêu biểu + Thông tin đều đã
| có Controller/Service thật đứng sau, không còn closure tĩnh.
*/

// -- Công khai (4.1) — khách xem được, không cần đăng nhập ------------------
Route::get('/', [PublicHomeController::class, 'index'])->name('home');
Route::get('/khoa-hoc', [PublicCourseController::class, 'index'])->name('courses.index');
Route::get('/khoa-hoc/{course}', [PublicCourseController::class, 'show'])->name('courses.show');
Route::get('/luyen-tap', [PublicPracticeController::class, 'index'])->name('practice.index');
Route::get('/tai-lieu', [PublicMaterialController::class, 'index'])->name('materials.index');
Route::get('/tai-lieu/{material}', [PublicMaterialController::class, 'show'])->name('materials.show');
Route::get('/cuoc-thi', [PublicCompetitionController::class, 'index'])->name('competitions.index');
Route::get('/cuoc-thi/{competition}', [PublicCompetitionController::class, 'show'])->name('competitions.show');
Route::get('/bang-xep-hang', [PublicLeaderboardController::class, 'index'])->name('leaderboard.index');
Route::get('/giao-vien-tieu-bieu', [PublicTeacherController::class, 'index'])->name('teachers.index');
Route::get('/giao-vien-tieu-bieu/{teacher}', fn ($teacher) => view('public.teachers.show'))->name('teachers.show');
Route::get('/thong-tin', [PublicInfoController::class, 'index'])->name('info.index');
// Form Liên hệ (4.1 mục Liên hệ) — khách chưa đăng nhập vẫn gửi được, không có middleware auth.
Route::post('/thong-tin/lien-he', [PublicContactController::class, 'store'])->name('info.contact.store');
Route::get('/thong-tin/chinh-sach/{slug}', [PublicInfoController::class, 'policy'])
    ->whereIn('slug', ['bao-mat', 'dieu-khoan', 'hoan-tien'])
    ->name('info.policies.show');

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

    // -- Thông báo (chuông toàn cục, dùng chung mọi vai trò) --------------------
    Route::get('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.readAll');

    // -- Học sinh (10.1) -----------------------------------------------------
    Route::middleware(['role:student'])->prefix('student')->name('student.')->group(function () {
        Route::get('courses', [StudentCourseController::class, 'index'])->name('courses.index');
        Route::post('classes/join', [StudentClassRoomController::class, 'join'])->name('classes.join');
        Route::get('classes/{class}', [StudentClassRoomController::class, 'show'])->name('classes.show');
        Route::get('schedule', [StudentScheduleController::class, 'index'])->name('schedule.index');
        Route::get('practice', [StudentPracticeController::class, 'index'])->name('practice.index');
        Route::get('assessments/{assessment}/take', [StudentAssessmentController::class, 'take'])->name('assessment.take');
        Route::get('assessments/{question}/oj', [StudentAssessmentController::class, 'oj'])->name('assessment.oj');
        Route::get('attempts/{attempt}/result', [StudentAssessmentController::class, 'result'])->name('assessment.result');
        Route::post('attempts/{attempt}/answers', [StudentAssessmentController::class, 'saveAnswers'])->name('assessment.take.save');
        Route::post('attempts/{attempt}/submit', [StudentAssessmentController::class, 'submit'])->name('assessment.take.submit');
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
        Route::post('classes/{class}/materials', [TeacherClassRoomController::class, 'attachMaterial'])->name('classes.materials.attach');
        Route::delete('classes/{class}/materials/{classMaterial}', [TeacherClassRoomController::class, 'detachMaterial'])->name('classes.materials.detach');
        Route::get('classes/{class}', [TeacherClassRoomController::class, 'show'])->name('classes.show');
        Route::get('questions', [TeacherQuestionController::class, 'index'])->name('questions.index');
        Route::get('questions/create', [TeacherQuestionController::class, 'create'])->name('questions.create');
        Route::post('questions', [TeacherQuestionController::class, 'store'])->name('questions.store');
        Route::get('questions/{question}/edit', [TeacherQuestionController::class, 'edit'])->name('questions.edit');
        Route::put('questions/{question}', [TeacherQuestionController::class, 'update'])->name('questions.update');
        Route::post('questions/{question}/publish', [TeacherQuestionController::class, 'publish'])->name('questions.publish');
        Route::post('questions/{question}/archive', [TeacherQuestionController::class, 'archive'])->name('questions.archive');

        Route::get('assessments', [TeacherAssessmentController::class, 'index'])->name('assessments.index');
        Route::get('assessments/create', [TeacherAssessmentController::class, 'create'])->name('assessments.create');
        Route::post('assessments', [TeacherAssessmentController::class, 'store'])->name('assessments.store');
        Route::get('assessments/import', [TeacherAssessmentController::class, 'import'])->name('assessments.import');
        Route::get('assessments/review-draft', [TeacherAssessmentController::class, 'reviewDraft'])->name('assessments.reviewDraft');
        Route::post('assessments/import', [TeacherAssessmentController::class, 'importStore'])->name('assessments.import.store');
        Route::get('assessments/documents/{document}/file', [TeacherAssessmentController::class, 'downloadDocument'])->name('assessments.documents.download');
        Route::post('assessments/documents/{document}/promote', [TeacherAssessmentController::class, 'promote'])->name('assessments.documents.promote');
        Route::post('assessments/documents/{document}/drafts', [TeacherAssessmentController::class, 'draftStore'])->name('assessments.drafts.store');
        Route::post('assessments/drafts/{draft}', [TeacherAssessmentController::class, 'draftUpdate'])->name('assessments.drafts.update');
        Route::post('assessments/drafts/{draft}/merge', [TeacherAssessmentController::class, 'draftMerge'])->name('assessments.drafts.merge');
        Route::post('assessments/drafts/{draft}/discard', [TeacherAssessmentController::class, 'draftDiscard'])->name('assessments.drafts.discard');
        Route::post('assessments/{assessment}/publish', [TeacherAssessmentController::class, 'publish'])->name('assessments.publish');
        Route::post('assessments/{assessment}/assign', [TeacherAssessmentController::class, 'assign'])->name('assessments.assign');

        Route::get('results', [TeacherResultController::class, 'index'])->name('results.index');
        Route::get('results/export', [TeacherResultController::class, 'export'])->name('results.export');
        Route::get('results/attempts/{attempt}', [TeacherResultController::class, 'attempt'])->name('results.attempt');

        Route::get('schedule', [TeacherScheduleController::class, 'index'])->name('schedule.index');
        Route::post('schedule', [TeacherScheduleController::class, 'store'])->name('schedule.store');
        Route::get('schedule/{session}/attendance', [TeacherScheduleController::class, 'attendance'])->name('schedule.attendance');
        Route::post('schedule/{session}/attendance', [TeacherScheduleController::class, 'saveAttendance'])->name('schedule.attendance.save');
        Route::post('schedule/{session}/summary', [TeacherScheduleController::class, 'saveSummary'])->name('schedule.summary.save');
        Route::post('schedule/{session}/resources', [TeacherScheduleController::class, 'addResource'])->name('schedule.resources.save');
        Route::delete('schedule/{session}/resources/{resource}', [TeacherScheduleController::class, 'removeResource'])->name('schedule.resources.delete');

        Route::get('notifications', [TeacherNotificationController::class, 'index'])->name('notifications.index');

        Route::get('profile', [TeacherProfileController::class, 'show'])->name('profile.show');
        Route::put('profile', [TeacherProfileController::class, 'update'])->name('profile.update');
        Route::put('profile/teacher-profile', [TeacherProfileController::class, 'updateTeacherProfile'])->name('profile.teacherProfile.update');
        Route::put('profile/password', [TeacherProfileController::class, 'updatePassword'])->name('profile.password');
    });

    // -- Phụ huynh (10.3) -----------------------------------------------------
    Route::middleware(['role:parent'])->prefix('parent')->name('parent.')->group(function () {
        Route::get('/', [ParentDashboardController::class, 'index'])->name('dashboard');
        Route::get('children', [ParentChildController::class, 'index'])->name('children.index');
        Route::post('children/link-requests', [ParentChildController::class, 'storeLinkRequest'])->name('children.linkRequest');
        Route::get('children/{child}', [ParentChildController::class, 'show'])->name('children.show');
        Route::get('schedule', [ParentScheduleController::class, 'index'])->name('schedule.index');
        Route::get('results', [ParentResultController::class, 'index'])->name('results.index');
        Route::get('notifications', [ParentNotificationController::class, 'index'])->name('notifications.index');
        Route::get('profile', [ParentProfileController::class, 'show'])->name('profile');
        Route::put('profile', [ParentProfileController::class, 'update'])->name('profile.update');
    });

    // -- Đánh giá sao / nhận xét trải nghiệm (mục 9) — dùng chung mọi vai trò --
    Route::prefix('danh-gia')->name('reviews.')->group(function () {
        Route::get('/', [ReviewController::class, 'index'])->name('index');
        Route::get('/viet', [ReviewController::class, 'form'])->name('form');
        Route::post('/viet', [ReviewController::class, 'store'])->name('store');
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

    // -- Ví token: "Nộp tiền thành token" + QR ngân hàng (note họp 13/8, mục 7-8) --------
    Route::prefix('vi')->name('wallet.')->group(function () {
        Route::get('/', [WalletController::class, 'index'])->name('index');
        Route::post('/nap', [WalletController::class, 'requestTopup'])->name('request');
    });

    // -- Admin/Editor (4.2) -------------------------------------------------
    Route::middleware(['role:admin,super_admin'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

        // Người dùng + phê duyệt giáo viên (ADM-02, 3.3)
        Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('users/create', [AdminUserController::class, 'create'])->name('users.create');
        Route::post('users', [AdminUserController::class, 'store'])->name('users.store');
        Route::get('users/{user}/edit', [AdminUserController::class, 'edit'])->name('users.edit');
        Route::put('users/{user}', [AdminUserController::class, 'update'])->name('users.update');
        Route::put('users/{user}/roles', [AdminUserController::class, 'updateRoles'])->name('users.roles.update');
        Route::put('users/{user}/password', [AdminUserController::class, 'resetPassword'])->name('users.password.update');
        Route::get('users/{user}', [AdminUserController::class, 'show'])->name('users.show');
        Route::post('parent-links/{parentLink}/approve', [AdminUserController::class, 'approveParentLink'])->name('parent-links.approve');
        Route::post('parent-links/{parentLink}/reject', [AdminUserController::class, 'rejectParentLink'])->name('parent-links.reject');
        Route::get('teacher-approvals', [AdminTeacherApprovalController::class, 'index'])->name('teacher-approvals.index');
        Route::get('teacher-approvals/{teacherApproval}', [AdminTeacherApprovalController::class, 'show'])->name('teacher-approvals.show');
        Route::post('teacher-approvals/{teacherApproval}/approve', [AdminTeacherApprovalController::class, 'approve'])->name('teacher-approvals.approve');
        Route::post('teacher-approvals/{teacherApproval}/reject', [AdminTeacherApprovalController::class, 'reject'])->name('teacher-approvals.reject');
        Route::post('teacher-approvals/{teacherApproval}/suspend', [AdminTeacherApprovalController::class, 'suspend'])->name('teacher-approvals.suspend');
        Route::post('teacher-approvals/{teacherApproval}/reinstate', [AdminTeacherApprovalController::class, 'reinstate'])->name('teacher-approvals.reinstate');

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
        Route::post('orders/{order}/approve', [AdminOrderController::class, 'approve'])->name('orders.approve');
        Route::post('orders/{order}/reject', [AdminOrderController::class, 'reject'])->name('orders.reject');
        Route::get('orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
        Route::get('activation-codes', [AdminActivationCodeController::class, 'index'])->name('activation-codes.index');
        Route::post('activation-codes/{activationCode}/revoke', [AdminActivationCodeController::class, 'revoke'])->name('activation-codes.revoke');

        // Yêu cầu nạp token (note họp 13/8, mục 7-8) — gộp chung trang Đơn hàng (7.4), không thêm mục điều hướng riêng.
        Route::post('orders/token-topups/{tokenTopup}/approve', [AdminOrderController::class, 'approveTopup'])->name('orders.token-topups.approve');
        Route::post('orders/token-topups/{tokenTopup}/reject', [AdminOrderController::class, 'rejectTopup'])->name('orders.token-topups.reject');

        // Đánh giá (ADM-06, 9.4)
        Route::get('reviews', [AdminReviewController::class, 'index'])->name('reviews.index');
        Route::post('reviews/{review}/publish', [AdminReviewController::class, 'publish'])->name('reviews.publish');
        Route::post('reviews/{review}/reject', [AdminReviewController::class, 'reject'])->name('reviews.reject');
        Route::post('reviews/{review}/request-revision', [AdminReviewController::class, 'requestRevision'])->name('reviews.request-revision');
        Route::post('reviews/{review}/hide', [AdminReviewController::class, 'hide'])->name('reviews.hide');
        Route::post('reviews/{review}/reply', [AdminReviewController::class, 'reply'])->name('reviews.reply');
        Route::get('reviews/{review}', [AdminReviewController::class, 'show'])->name('reviews.show');

        // Tin nhắn liên hệ (PUB-11, 4.1) — gộp chung nhóm điều hướng với Đánh giá (không thêm mục nav riêng).
        Route::get('contact-messages', [AdminContactMessageController::class, 'index'])->name('contact-messages.index');
        Route::post('contact-messages/{contactMessage}/resolve', [AdminContactMessageController::class, 'resolve'])->name('contact-messages.resolve');

        // Cuộc thi, Giáo viên tiêu biểu, Bảng xếp hạng (ADM-05, 11.1/11.2)
        Route::get('competitions', [AdminCompetitionController::class, 'index'])->name('competitions.index');
        Route::get('competitions/create', [AdminCompetitionController::class, 'create'])->name('competitions.create');
        Route::post('competitions', [AdminCompetitionController::class, 'store'])->name('competitions.store');
        Route::get('competitions/{competition}/edit', [AdminCompetitionController::class, 'edit'])->name('competitions.edit');
        Route::put('competitions/{competition}', [AdminCompetitionController::class, 'update'])->name('competitions.update');
        Route::post('competitions/{competition}/archive', [AdminCompetitionController::class, 'archive'])->name('competitions.archive');
        Route::get('competitions/{competition}', [AdminCompetitionController::class, 'show'])->name('competitions.show');
        Route::get('featured-teachers', [AdminFeaturedTeacherController::class, 'index'])->name('featured-teachers.index');
        Route::post('featured-teachers/{featuredTeacher}/feature', [AdminFeaturedTeacherController::class, 'feature'])->name('featured-teachers.feature');
        Route::post('featured-teachers/{featuredTeacher}/unfeature', [AdminFeaturedTeacherController::class, 'unfeature'])->name('featured-teachers.unfeature');
        Route::get('ranking', [AdminRankingController::class, 'index'])->name('ranking.index');
        Route::get('ranking/{scope}/{id}', [AdminRankingController::class, 'show'])
            ->whereIn('scope', ['competition', 'class'])
            ->where('id', '[0-9]+')
            ->name('ranking.show');

        // Báo cáo (2.3: P0 chỉ báo cáo vận hành cơ kản, KHÔNG gồm báo cáo thương mại sâu/chiến dịch)
        Route::get('reports', [AdminReportController::class, 'index'])->name('reports.index');

        // Cấu hình hệ thống — CHỈ Super Admin (3.1: "Cấu hình hệ thống tối cao" không thuộc Admin thường).
        Route::middleware('role:super_admin')->group(function () {
            Route::get('settings', [AdminSettingsController::class, 'index'])->name('settings.index');
            Route::put('settings/rating-threshold', [AdminSettingsController::class, 'updateRatingThreshold'])->name('settings.rating-threshold.update');
            Route::put('settings/wallet-bank', [AdminSettingsController::class, 'updateWalletBankInfo'])->name('settings.wallet-bank.update');
        });
    });

    // -- Admin/Editor: Nội dung + Tài khoản (note họp 13/8, mục 5 — Editor "nghề up câu hỏi") --
    Route::middleware(['role:admin,super_admin,editor'])->prefix('admin')->name('admin.')->group(function () {
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

        // Nhập đề Word/PDF/OCR -> Kho chung (6.4) — tương đương teacher.assessments.import
        // nhưng đích đến là Kho chung (owner_type=shared) thay vì kho riêng giáo viên.
        Route::get('content/questions/import', [AdminContentController::class, 'questionsImport'])->name('content.questions.import');
        Route::post('content/questions/import', [AdminContentController::class, 'questionsImportStore'])->name('content.questions.import.store');
        Route::get('content/questions/review-draft', [AdminContentController::class, 'questionsReviewDraft'])->name('content.questions.reviewDraft');
        Route::get('content/documents/{document}/file', [AdminContentController::class, 'downloadDocument'])->name('content.documents.download');
        Route::post('content/documents/{document}/promote', [AdminContentController::class, 'promote'])->name('content.documents.promote');
        Route::post('content/documents/{document}/drafts', [AdminContentController::class, 'draftStore'])->name('content.drafts.store');
        Route::post('content/drafts/{draft}', [AdminContentController::class, 'draftUpdate'])->name('content.drafts.update');
        Route::post('content/drafts/{draft}/merge', [AdminContentController::class, 'draftMerge'])->name('content.drafts.merge');
        Route::post('content/drafts/{draft}/discard', [AdminContentController::class, 'draftDiscard'])->name('content.drafts.discard');

        Route::get('content/assessments/create', [AdminContentController::class, 'assessmentsCreate'])->name('content.assessments.create');
        Route::post('content/assessments', [AdminContentController::class, 'assessmentsStore'])->name('content.assessments.store');
        Route::get('content/assessments/{assessment}/edit', [AdminContentController::class, 'assessmentsEdit'])->name('content.assessments.edit');
        Route::put('content/assessments/{assessment}', [AdminContentController::class, 'assessmentsUpdate'])->name('content.assessments.update');
        Route::post('content/assessments/{assessment}/publish', [AdminContentController::class, 'assessmentsPublish'])->name('content.assessments.publish');
        Route::post('content/assessments/{assessment}/reject', [AdminContentController::class, 'assessmentsReject'])->name('content.assessments.reject');
        Route::post('content/assessments/{assessment}/archive', [AdminContentController::class, 'assessmentsArchive'])->name('content.assessments.archive');

        Route::get('content/{content}', [AdminContentController::class, 'show'])->name('content.show');

        // Tài khoản (hồ sơ + đổi mật khẩu) — ACC-01/ACC-02, dùng chung Admin/Editor.
        Route::get('profile', [AdminProfileController::class, 'show'])->name('profile.show');
        Route::put('profile', [AdminProfileController::class, 'update'])->name('profile.update');
        Route::put('profile/password', [AdminProfileController::class, 'updatePassword'])->name('profile.password');
    });
});

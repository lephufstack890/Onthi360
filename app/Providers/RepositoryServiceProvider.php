<?php

namespace App\Providers;

use App\Repositories\Contracts\AccessRightRepositoryInterface;
use App\Repositories\Contracts\ActivationCodeRepositoryInterface;
use App\Repositories\Contracts\AssessmentRepositoryInterface;
use App\Repositories\Contracts\AssignmentRepositoryInterface;
use App\Repositories\Contracts\AttemptAnswerRepositoryInterface;
use App\Repositories\Contracts\AttemptRepositoryInterface;
use App\Repositories\Contracts\AttendanceRepositoryInterface;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use App\Repositories\Contracts\ClassEnrollmentRepositoryInterface;
use App\Repositories\Contracts\ClassMaterialRepositoryInterface;
use App\Repositories\Contracts\ClassRoomRepositoryInterface;
use App\Repositories\Contracts\ClassSessionRepositoryInterface;
use App\Repositories\Contracts\CompetitionRepositoryInterface;
use App\Repositories\Contracts\CourseRepositoryInterface;
use App\Repositories\Contracts\DraftQuestionRepositoryInterface;
use App\Repositories\Contracts\LeaderboardEntryRepositoryInterface;
use App\Repositories\Contracts\MaterialRepositoryInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\ParentLinkRepositoryInterface;
use App\Repositories\Contracts\PermissionRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\QuestionRepositoryInterface;
use App\Repositories\Contracts\RatingSummaryRepositoryInterface;
use App\Repositories\Contracts\ReviewReportRepositoryInterface;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Repositories\Contracts\SystemSettingRepositoryInterface;
use App\Repositories\Contracts\TeacherProfileRepositoryInterface;
use App\Repositories\Contracts\UploadedDocumentRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Eloquent\AccessRightRepository;
use App\Repositories\Eloquent\ActivationCodeRepository;
use App\Repositories\Eloquent\AssessmentRepository;
use App\Repositories\Eloquent\AssignmentRepository;
use App\Repositories\Eloquent\AttemptAnswerRepository;
use App\Repositories\Eloquent\AttemptRepository;
use App\Repositories\Eloquent\AttendanceRepository;
use App\Repositories\Eloquent\AuditLogRepository;
use App\Repositories\Eloquent\ClassEnrollmentRepository;
use App\Repositories\Eloquent\ClassMaterialRepository;
use App\Repositories\Eloquent\ClassRoomRepository;
use App\Repositories\Eloquent\ClassSessionRepository;
use App\Repositories\Eloquent\CompetitionRepository;
use App\Repositories\Eloquent\CourseRepository;
use App\Repositories\Eloquent\DraftQuestionRepository;
use App\Repositories\Eloquent\LeaderboardEntryRepository;
use App\Repositories\Eloquent\MaterialRepository;
use App\Repositories\Eloquent\OrderRepository;
use App\Repositories\Eloquent\ParentLinkRepository;
use App\Repositories\Eloquent\PermissionRepository;
use App\Repositories\Eloquent\ProductRepository;
use App\Repositories\Eloquent\QuestionRepository;
use App\Repositories\Eloquent\RatingSummaryRepository;
use App\Repositories\Eloquent\ReviewReportRepository;
use App\Repositories\Eloquent\ReviewRepository;
use App\Repositories\Eloquent\RoleRepository;
use App\Repositories\Eloquent\SystemSettingRepository;
use App\Repositories\Eloquent\TeacherProfileRepository;
use App\Repositories\Eloquent\UploadedDocumentRepository;
use App\Repositories\Eloquent\UserRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Đăng ký toàn bộ binding Interface -> Implementation cho tầng Repository.
 *
 * Mục đích: Controller/Service chỉ phụ thuộc vào *Interface (Contracts\...),
 * không phụ thuộc trực tiếp vào Eloquent. Muốn đổi nguồn dữ liệu (cache,
 * API khác, ...) trong tương lai chỉ cần đổi binding ở đây.
 */
class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    private array $repositoryBindings = [
        UserRepositoryInterface::class => UserRepository::class,
        RoleRepositoryInterface::class => RoleRepository::class,
        ClassRoomRepositoryInterface::class => ClassRoomRepository::class,
        ClassEnrollmentRepositoryInterface::class => ClassEnrollmentRepository::class,
        ClassSessionRepositoryInterface::class => ClassSessionRepository::class,
        ClassMaterialRepositoryInterface::class => ClassMaterialRepository::class,
        AssignmentRepositoryInterface::class => AssignmentRepository::class,
        AssessmentRepositoryInterface::class => AssessmentRepository::class,
        QuestionRepositoryInterface::class => QuestionRepository::class,
        AttemptRepositoryInterface::class => AttemptRepository::class,
        AttemptAnswerRepositoryInterface::class => AttemptAnswerRepository::class,
        ParentLinkRepositoryInterface::class => ParentLinkRepository::class,
        PermissionRepositoryInterface::class => PermissionRepository::class,
        TeacherProfileRepositoryInterface::class => TeacherProfileRepository::class,
        AttendanceRepositoryInterface::class => AttendanceRepository::class,
        ReviewRepositoryInterface::class => ReviewRepository::class,
        ReviewReportRepositoryInterface::class => ReviewReportRepository::class,
        RatingSummaryRepositoryInterface::class => RatingSummaryRepository::class,
        AccessRightRepositoryInterface::class => AccessRightRepository::class,
        ProductRepositoryInterface::class => ProductRepository::class,
        UploadedDocumentRepositoryInterface::class => UploadedDocumentRepository::class,
        DraftQuestionRepositoryInterface::class => DraftQuestionRepository::class,
        OrderRepositoryInterface::class => OrderRepository::class,
        ActivationCodeRepositoryInterface::class => ActivationCodeRepository::class,
        CompetitionRepositoryInterface::class => CompetitionRepository::class,
        LeaderboardEntryRepositoryInterface::class => LeaderboardEntryRepository::class,
        AuditLogRepositoryInterface::class => AuditLogRepository::class,
        CourseRepositoryInterface::class => CourseRepository::class,
        MaterialRepositoryInterface::class => MaterialRepository::class,
        SystemSettingRepositoryInterface::class => SystemSettingRepository::class,
    ];

    public function register(): void
    {
        foreach ($this->repositoryBindings as $interface => $implementation) {
            $this->app->bind($interface, $implementation);
        }
    }
}

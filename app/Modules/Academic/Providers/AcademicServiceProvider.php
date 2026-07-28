<?php

declare(strict_types=1);

namespace App\Modules\Academic\Providers;

use App\Models\CourseRegistration;
use App\Modules\Academic\Actions\CompleteFinanceCancellationOperationAction;
use App\Modules\Academic\Actions\MarkCourseOfferingCompletedAction;
use App\Modules\Academic\Actions\SyncPaidExamResitAttemptsAction;
use App\Modules\Academic\Actions\SyncPaidRetakeRegistrationsAction;
use App\Modules\Academic\Catalog\Queries\GetStudentDirectoryFormOptionsQuery;
use App\Modules\Academic\Catalog\Support\EloquentAdmissionsIntentReader;
use App\Modules\Academic\Catalog\Support\EloquentCourseOfferingCatalogReader;
use App\Modules\Academic\Catalog\Support\EloquentCurriculumGraduationRequirementsReader;
use App\Modules\Academic\Catalog\Support\EloquentCurriculumModuleCompositionReader;
use App\Modules\Academic\Catalog\Support\EloquentProgramReferenceReader;
use App\Modules\Academic\Catalog\Support\SemesterAcademicPeriodReader;
use App\Modules\Academic\Delivery\Actions\CommitCourseResultsAndTranscriptEntriesAction;
use App\Modules\Academic\Delivery\Support\AssessmentGradeExcelService;
use App\Modules\Academic\Delivery\Support\DeliveryAssessmentDefinitionWriter;
use App\Modules\Academic\Delivery\Support\DeliveryInstructorAssignmentWriter;
use App\Modules\Academic\Delivery\Support\EloquentAcademicSpaceOccupancyReader;
use App\Modules\Academic\Delivery\Support\EloquentCourseOfferingSurveyContextReader;
use App\Modules\Academic\Delivery\Support\EloquentCourseResultProgressionReader;
use App\Modules\Academic\Delivery\Support\EloquentCourseRosterReader;
use App\Modules\Academic\Delivery\Support\EloquentLegacyTranscriptOutcomeReader;
use App\Modules\Academic\Delivery\Support\EloquentStudentHubAssessmentEvidenceReader;
use App\Modules\Academic\Delivery\Support\EloquentStudentHubCourseOutcomeEvidenceReader;
use App\Modules\Academic\Delivery\Support\EloquentStudentHubRegistrationEvidenceReader;
use App\Modules\Academic\Delivery\Support\EloquentStudentLifecycleCourseRegistrationGateway;
use App\Modules\Academic\FacultyWorkforce\Support\EloquentActiveLecturerReader;
use App\Modules\Academic\FacultyWorkforce\Support\EloquentAvailableLecturerReader;
use App\Modules\Academic\FacultyWorkforce\Support\EloquentLecturerTokenIssuer;
use App\Modules\Academic\FacultyWorkforce\Support\EloquentTeachingEligibilityReader;
use App\Modules\Academic\Observers\CourseRegistrationObserver;
use App\Modules\Academic\Progression\Actions\CommitCourseResultsToTranscriptAction;
use App\Modules\Academic\Progression\Queries\FilterStudentsByProgramEnrollmentStatus;
use App\Modules\Academic\Progression\Queries\GetStudentAcademicRecordsQuery;
use App\Modules\Academic\Progression\Queries\GetStudentGpaTrendQuery;
use App\Modules\Academic\Progression\Support\EloquentCourseOfferingAttemptWriter;
use App\Modules\Academic\Progression\Support\EloquentProgramEnrollmentLifecycleWriter;
use App\Modules\Academic\Progression\Support\EloquentProgramEnrollmentReader;
use App\Modules\Academic\Progression\Support\EloquentProgramEnrollmentWriter;
use App\Modules\Academic\Progression\Support\EloquentStudentAcademicHoldReader;
use App\Modules\Academic\Progression\Support\EloquentStudentDeferLifecycleReader;
use App\Modules\Academic\Progression\Support\EloquentStudentLifecycleActionReader;
use App\Modules\Academic\Progression\Support\EloquentTranscriptEntryGpaReader;
use App\Modules\Academic\Progression\Support\StudentLifecycleStatusReader as ModuleStudentLifecycleStatusReader;
use App\Modules\Academic\Queries\Reporting\GetAcademicReportQuery;
use App\Modules\Academic\Support\AcademicFinanceChargeSourceGateway as ModuleAcademicFinanceChargeSourceGateway;
use App\Modules\Academic\Support\AiAcademicEntitySearchReader as ModuleAiAcademicEntitySearchReader;
use App\Modules\Academic\Support\AiAcademicMetricReader as ModuleAiAcademicMetricReader;
use App\Modules\Academic\Support\AiAcademicStudentProfileReader as ModuleAiAcademicStudentProfileReader;
use App\Modules\Academic\Support\CampusBuildingCountReader as ModuleCampusBuildingCountReader;
use App\Services\V1\Student\DashboardService;
use App\Shared\Contracts\Academic\AcademicFinanceChargeSourceGateway;
use App\Shared\Contracts\Academic\AcademicPeriodReader;
use App\Shared\Contracts\Academic\AcademicReportReader;
use App\Shared\Contracts\Academic\AcademicSpaceOccupancyReader;
use App\Shared\Contracts\Academic\AdmissionsIntentReader;
use App\Shared\Contracts\Academic\AiAcademicEntitySearchReader;
use App\Shared\Contracts\Academic\AiAcademicMetricReader;
use App\Shared\Contracts\Academic\AiAcademicStudentProfileReader;
use App\Shared\Contracts\Academic\AssessmentDefinitionWriter;
use App\Shared\Contracts\Academic\AssessmentGradeWorkbook;
use App\Shared\Contracts\Academic\CampusBuildingCountReader;
use App\Shared\Contracts\Academic\CourseOfferingAttemptWriter;
use App\Shared\Contracts\Academic\CourseOfferingCatalogReader;
use App\Shared\Contracts\Academic\CourseOfferingFinalizer;
use App\Shared\Contracts\Academic\CourseOfferingSurveyContextReader;
use App\Shared\Contracts\Academic\CourseResultProgressionReader;
use App\Shared\Contracts\Academic\CourseResultTranscriptCommitter;
use App\Shared\Contracts\Academic\CourseResultTranscriptWriter;
use App\Shared\Contracts\Academic\CourseRosterReader;
use App\Shared\Contracts\Academic\CurriculumGraduationRequirementsReader;
use App\Shared\Contracts\Academic\CurriculumModuleCompositionReader;
use App\Shared\Contracts\Academic\ExamResitAttemptPaymentSyncer;
use App\Shared\Contracts\Academic\InstructorAssignmentWriter;
use App\Shared\Contracts\Academic\LecturerImpersonationTokenIssuer;
use App\Shared\Contracts\Academic\LegacyTranscriptOutcomeReader;
use App\Shared\Contracts\Academic\ProgramEnrollmentLifecycleWriter;
use App\Shared\Contracts\Academic\ProgramEnrollmentReader;
use App\Shared\Contracts\Academic\ProgramEnrollmentStatusFilter;
use App\Shared\Contracts\Academic\ProgramEnrollmentWriter;
use App\Shared\Contracts\Academic\ProgramReferenceReader;
use App\Shared\Contracts\Academic\RetakeRegistrationPaymentSyncer;
use App\Shared\Contracts\Academic\StudentAcademicHoldReader;
use App\Shared\Contracts\Academic\StudentAcademicRecordsReader;
use App\Shared\Contracts\Academic\StudentDashboardReader;
use App\Shared\Contracts\Academic\StudentDeferLifecycleReader;
use App\Shared\Contracts\Academic\StudentDirectoryFormOptionsReader;
use App\Shared\Contracts\Academic\StudentGpaTrendReader;
use App\Shared\Contracts\Academic\StudentHubAssessmentEvidenceReader;
use App\Shared\Contracts\Academic\StudentHubCourseOutcomeEvidenceReader;
use App\Shared\Contracts\Academic\StudentHubRegistrationEvidenceReader;
use App\Shared\Contracts\Academic\StudentLifecycleActionReader;
use App\Shared\Contracts\Academic\StudentLifecycleCourseRegistrationGateway;
use App\Shared\Contracts\Academic\StudentLifecycleStatusReader;
use App\Shared\Contracts\Academic\TeachingEligibilityReader;
use App\Shared\Contracts\Academic\TranscriptEntryGpaReader;
use App\Shared\Contracts\Finance\FinanceCancellationCompletionContract;
use App\Shared\Contracts\Identity\ActiveLecturerReader;
use App\Shared\Contracts\Identity\AvailableLecturerReader;
use App\Shared\Contracts\Identity\LecturerReferenceReader;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AcademicServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AdmissionsIntentReader::class, EloquentAdmissionsIntentReader::class);
        $this->app->bind(AcademicPeriodReader::class, SemesterAcademicPeriodReader::class);
        $this->app->bind(AcademicReportReader::class, GetAcademicReportQuery::class);
        $this->app->bind(AssessmentGradeWorkbook::class, AssessmentGradeExcelService::class);
        $this->app->bind(AssessmentDefinitionWriter::class, DeliveryAssessmentDefinitionWriter::class);
        $this->app->bind(AcademicSpaceOccupancyReader::class, EloquentAcademicSpaceOccupancyReader::class);
        $this->app->bind(CourseOfferingCatalogReader::class, EloquentCourseOfferingCatalogReader::class);
        $this->app->bind(CurriculumGraduationRequirementsReader::class, EloquentCurriculumGraduationRequirementsReader::class);
        $this->app->bind(CurriculumModuleCompositionReader::class, EloquentCurriculumModuleCompositionReader::class);
        $this->app->bind(ProgramReferenceReader::class, EloquentProgramReferenceReader::class);
        $this->app->bind(CourseOfferingFinalizer::class, MarkCourseOfferingCompletedAction::class);
        $this->app->bind(CourseOfferingSurveyContextReader::class, EloquentCourseOfferingSurveyContextReader::class);
        $this->app->bind(CourseOfferingAttemptWriter::class, EloquentCourseOfferingAttemptWriter::class);
        $this->app->bind(CourseRosterReader::class, EloquentCourseRosterReader::class);
        $this->app->bind(CourseResultTranscriptWriter::class, CommitCourseResultsToTranscriptAction::class);
        $this->app->bind(CourseResultTranscriptCommitter::class, CommitCourseResultsAndTranscriptEntriesAction::class);
        $this->app->bind(CourseResultProgressionReader::class, EloquentCourseResultProgressionReader::class);
        $this->app->bind(TranscriptEntryGpaReader::class, EloquentTranscriptEntryGpaReader::class);
        $this->app->bind(TeachingEligibilityReader::class, EloquentTeachingEligibilityReader::class);
        $this->app->bind(ActiveLecturerReader::class, EloquentActiveLecturerReader::class);
        $this->app->bind(AvailableLecturerReader::class, EloquentAvailableLecturerReader::class);
        $this->app->bind(LecturerReferenceReader::class, EloquentActiveLecturerReader::class);
        $this->app->bind(LecturerImpersonationTokenIssuer::class, EloquentLecturerTokenIssuer::class);
        $this->app->bind(LegacyTranscriptOutcomeReader::class, EloquentLegacyTranscriptOutcomeReader::class);
        $this->app->bind(InstructorAssignmentWriter::class, DeliveryInstructorAssignmentWriter::class);
        $this->app->bind(AiAcademicMetricReader::class, ModuleAiAcademicMetricReader::class);
        $this->app->bind(AiAcademicEntitySearchReader::class, ModuleAiAcademicEntitySearchReader::class);
        $this->app->bind(AiAcademicStudentProfileReader::class, ModuleAiAcademicStudentProfileReader::class);
        $this->app->bind(CampusBuildingCountReader::class, ModuleCampusBuildingCountReader::class);
        $this->app->bind(AcademicFinanceChargeSourceGateway::class, ModuleAcademicFinanceChargeSourceGateway::class);
        $this->app->bind(ProgramEnrollmentReader::class, EloquentProgramEnrollmentReader::class);
        $this->app->bind(ProgramEnrollmentStatusFilter::class, FilterStudentsByProgramEnrollmentStatus::class);
        $this->app->bind(StudentDirectoryFormOptionsReader::class, GetStudentDirectoryFormOptionsQuery::class);
        $this->app->bind(ProgramEnrollmentLifecycleWriter::class, EloquentProgramEnrollmentLifecycleWriter::class);
        $this->app->bind(ProgramEnrollmentWriter::class, EloquentProgramEnrollmentWriter::class);
        $this->app->bind(StudentLifecycleStatusReader::class, ModuleStudentLifecycleStatusReader::class);
        $this->app->bind(StudentLifecycleActionReader::class, EloquentStudentLifecycleActionReader::class);
        $this->app->bind(StudentDeferLifecycleReader::class, EloquentStudentDeferLifecycleReader::class);
        $this->app->bind(
            StudentLifecycleCourseRegistrationGateway::class,
            EloquentStudentLifecycleCourseRegistrationGateway::class,
        );
        $this->app->bind(StudentHubRegistrationEvidenceReader::class, EloquentStudentHubRegistrationEvidenceReader::class);
        $this->app->bind(StudentHubAssessmentEvidenceReader::class, EloquentStudentHubAssessmentEvidenceReader::class);
        $this->app->bind(StudentHubCourseOutcomeEvidenceReader::class, EloquentStudentHubCourseOutcomeEvidenceReader::class);
        $this->app->bind(StudentAcademicRecordsReader::class, GetStudentAcademicRecordsQuery::class);
        $this->app->bind(StudentDashboardReader::class, DashboardService::class);
        $this->app->bind(StudentAcademicHoldReader::class, EloquentStudentAcademicHoldReader::class);
        $this->app->bind(StudentGpaTrendReader::class, GetStudentGpaTrendQuery::class);
        $this->app->bind(RetakeRegistrationPaymentSyncer::class, SyncPaidRetakeRegistrationsAction::class);
        $this->app->bind(ExamResitAttemptPaymentSyncer::class, SyncPaidExamResitAttemptsAction::class);
        $this->app->bind(FinanceCancellationCompletionContract::class, CompleteFinanceCancellationOperationAction::class);
    }

    public function boot(): void
    {
        CourseRegistration::observe(CourseRegistrationObserver::class);

        Route::middleware('web')
            ->group(__DIR__.'/../routes/web.php');

        // API routes are registered in routes/api/admin.php (web middleware, campus-scoped)
    }
}

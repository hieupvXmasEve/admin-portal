<?php

declare(strict_types=1);

namespace App\Modules\StudentRegistry\Providers;

use App\Models\Student;
use App\Modules\StudentRegistry\Observers\PublishStudentRegistered;
use App\Modules\StudentRegistry\Queries\ListStudentsQuery;
use App\Modules\StudentRegistry\Support\EloquentCurriculumStudentSummaryReader;
use App\Modules\StudentRegistry\Support\EloquentSemesterEnrollmentEligibilityReader;
use App\Modules\StudentRegistry\Support\EloquentStudentCollectionEligibilityReader;
use App\Modules\StudentRegistry\Support\EloquentStudentGuardianRelationshipReader;
use App\Modules\StudentRegistry\Support\EloquentStudentGuardianRelationshipWriter;
use App\Modules\StudentRegistry\Support\EloquentStudentIdentityWriter;
use App\Modules\StudentRegistry\Support\EloquentStudentImpersonationTokenIssuer;
use App\Modules\StudentRegistry\Support\EloquentStudentPortalContextReader;
use App\Modules\StudentRegistry\Support\EloquentStudentPortalProfileReader;
use App\Modules\StudentRegistry\Support\EloquentStudentPortalTokenRefresher;
use App\Modules\StudentRegistry\Support\EloquentStudentProfileWriter;
use App\Modules\StudentRegistry\Support\EloquentStudentRegistryStore;
use App\Shared\Contracts\StudentRegistry\CurriculumStudentSummaryReader;
use App\Shared\Contracts\StudentRegistry\SemesterEnrollmentEligibilityReader;
use App\Shared\Contracts\StudentRegistry\StudentCollectionEligibilityReader;
use App\Shared\Contracts\StudentRegistry\StudentDirectoryReader;
use App\Shared\Contracts\StudentRegistry\StudentGuardianRelationshipReader;
use App\Shared\Contracts\StudentRegistry\StudentGuardianRelationshipWriter;
use App\Shared\Contracts\StudentRegistry\StudentIdentityWriter;
use App\Shared\Contracts\StudentRegistry\StudentImpersonationTokenIssuer;
use App\Shared\Contracts\StudentRegistry\StudentPortalContextReader;
use App\Shared\Contracts\StudentRegistry\StudentPortalProfileReader;
use App\Shared\Contracts\StudentRegistry\StudentPortalTokenRefresher;
use App\Shared\Contracts\StudentRegistry\StudentProfilePersistenceWriter;
use App\Shared\Contracts\StudentRegistry\StudentProfileReader;
use App\Shared\Contracts\StudentRegistry\StudentProfileWriter;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;
use App\Shared\Contracts\StudentRegistry\StudentSerializedReferenceReader;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class StudentRegistryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(StudentDirectoryReader::class, ListStudentsQuery::class);
        $this->app->bind(StudentReferenceReader::class, EloquentStudentRegistryStore::class);
        $this->app->bind(StudentSerializedReferenceReader::class, EloquentStudentRegistryStore::class);
        $this->app->bind(CurriculumStudentSummaryReader::class, EloquentCurriculumStudentSummaryReader::class);
        $this->app->bind(StudentCollectionEligibilityReader::class, EloquentStudentCollectionEligibilityReader::class);
        $this->app->bind(SemesterEnrollmentEligibilityReader::class, EloquentSemesterEnrollmentEligibilityReader::class);
        $this->app->bind(StudentGuardianRelationshipWriter::class, EloquentStudentGuardianRelationshipWriter::class);
        $this->app->bind(StudentIdentityWriter::class, EloquentStudentIdentityWriter::class);
        $this->app->bind(StudentProfileWriter::class, EloquentStudentProfileWriter::class);
        $this->app->bind(StudentProfilePersistenceWriter::class, EloquentStudentRegistryStore::class);
        $this->app->bind(StudentProfileReader::class, EloquentStudentRegistryStore::class);
        $this->app->bind(StudentPortalProfileReader::class, EloquentStudentPortalProfileReader::class);
        $this->app->bind(StudentPortalContextReader::class, EloquentStudentPortalContextReader::class);
        $this->app->bind(StudentPortalTokenRefresher::class, EloquentStudentPortalTokenRefresher::class);
        $this->app->bind(StudentImpersonationTokenIssuer::class, EloquentStudentImpersonationTokenIssuer::class);
        $this->app->bind(StudentGuardianRelationshipReader::class, EloquentStudentGuardianRelationshipReader::class);
    }

    public function boot(): void
    {
        Student::observe(PublishStudentRegistered::class);

        Route::middleware('web')
            ->group(__DIR__.'/../routes/web.php');
    }
}

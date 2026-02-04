# Naming Conventions

## 1. Backend Naming

### Modules

- **Format**: PascalCase, Singular.
- **Examples**: `Academic`, `Finance`, `Identity`.
- **Bad**: `Academics` (plural), `BillingSystem` (too verbose).

### Models

- **Format**: PascalCase, Singular.
- **Examples**: `Student`, `CourseOffering`, `AcademicRecord`.
- **Bad**: `TblStudent`, `StudentEntity`.

### Controllers

- **Web (Stateful)**: `Http/Web/Admin/{Entity}Controller.php`.
- **Api (Stateless)**: `Http/Api/Student/{Entity}Controller.php`.
- **Standard Methods**: `index`, `show`, `create`, `store`, `edit`, `update`, `destroy`.

### Actions (Business Logic)

- **Format**: `Verb + [Entity] + Action`.
- **Examples**:
    - `CreateEventAction`
    - `UpdateStudentProfileAction`
    - `CalculateGpaAction`
- **Entry Point**: `public static function run(array $data): mixed`

### Queries (Read-Only)

- **Format**: `[Verb/Get] + Entity + [Context] + Query`.
- **Examples**:
    - `ListAcademicRecordsQuery`
    - `GetStudentGpaQuery`
    - `FindEventByCodeQuery`
- **Entry Point**: `public function handle(...$args): mixed`

### Policies

- **Format**: `{Model}Policy`.
- **Examples**: `AcademicRecordPolicy`, `EventPolicy`.

### Routes

- **Web Format**: `{module}.{resource}.{action}` (e.g., `identity.login.show`, `academic.records.index`).
- **API Format**: `/api/v1/{portal}/{resource}/{action}` (e.g., `/api/v1/student/auth/login`).

## 2. Frontend Naming

### Pages

- **Mapping**: 1-1 with Route/Controller Structure.
- **Example**: `Academic/Records/Index.vue`.

### Components

- **Format**: PascalCase.
- **Examples**: `RecordTable.vue`, `EventForm.vue`.
- **Bad**: `Table.vue`, `Form.vue` (Too generic).

### Composables

- **Format**: `use` prefix + feature name.
- **Examples**: `useInertiaFilters.ts`, `usePermissions.ts`.

## 3. Contract Naming

- **Format**: `{Noun}{Verb}{Purpose}`.
- **Examples**:
    - `StudentAcademicReader` (Reads academic data for a student)
    - `WalletBalanceProvider` (Provides wallet balance)
    - `SurveyCompletionChecker` (Checks if survey is complete)
- **Bad**: `AcademicService` (Vague), `IAcademic` (Interface prefix style).

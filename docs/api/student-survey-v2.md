# Student Survey & Portal Gate API Documentation

This document describes the API endpoints and data structures used for student surveys and the portal gate system.

## 1. Data Structures (TypeScript)

### Common Enums & Constants

```typescript
type FormType = 'survey' | 'feedback' | 'query';
type FormStatus = 'active' | 'inactive' | 'closed';
type QuestionType = 'short_text' | 'long_text' | 'single_choice' | 'multi_choice' | 'likert' | 'rating' | 'date' | 'number' | 'file';
type ScopeType = 'global' | 'department' | 'semester' | 'course';
type Priority = 'low' | 'normal' | 'high';
type Origin = 'web' | 'mobile';

interface ApiResponse<T> {
    success: boolean;
    timestamp: string;
    message?: string;
    data: T;
    meta?: any;
}
```

### Core Interfaces

```typescript
interface Option {
    id: number;
    value: string;
    label: string;
    order_index: number;
    allows_free_text: boolean;
}

interface Question {
    id: number;
    code: string;
    text: string;
    type: QuestionType;
    is_required: boolean;
    help_text: string | null;
    order_index: number;
    options?: Option[] | null;
    validation_json?: any | null;
}

interface FormSection {
    id: number;
    title: string;
    description: string | null;
    order_index: number;
    questions: Question[];
}

interface FormVersion {
    id: number;
    version_no: number;
    sections?: FormSection[] | null;
    questions?: Question[] | null; // Questions not assigned to any section
}

interface Form {
    id: number;
    code: string;
    type: FormType;
    title: string;
    description: string | null;
    status: FormStatus;
}

interface FormDetail extends Form {
    current_version: FormVersion | null;
    targets: FormTarget[];
}

interface FormTarget {
    id: number;
    campus_id: number | null;
    campus_name?: string | null;
    scope_type: ScopeType;
    scope_id: any;
    start_at: string;
    end_at: string | null;
    is_mandatory: boolean;
}

interface MandatoryAssignment {
    id: number;
    form_title: string;
    due_date: string | null;
}
```

### Context & Settings

```typescript
interface StudentContext {
    student_id: number;
    survey_gate: {
        blocked: boolean;
        mandatory_assignments: MandatoryAssignment[];
    };
    settings: {
        ui: {
            theme: 'light' | 'dark' | 'system';
            language: 'vi' | 'en';
            compact_mode: boolean;
        };
        notifications: {
            email: boolean;
            push: boolean;
        };
    };
    feature_flags: Record<string, boolean>;
    permissions: Record<string, boolean>;
}
```

### Submission Data Structures

```typescript
interface SubmitAnswer {
    question_id: number;
    answer_text?: string;
    answer_number?: number;
    answer_date?: string;
    comment?: string;
    selected_options?: Array<{
        option_id: number;
        free_text?: string;
    }>;
}

interface FormSubmissionRequest {
    campus_id?: number | null;
    target_scope_type: ScopeType;
    target_scope_id?: any;
    anonymized?: boolean;
    origin?: Origin;
    answers: SubmitAnswer[];
}
```

## 2. API Endpoints

### 2.1 Get Student Context

Initializes the portal data including gate status, feature flags, and settings.

- **Endpoint**: `GET /api/v1/student/context`
- **Response**: `ApiResponse<StudentContext>`

### 2.2 Get Pending Mandatory Surveys

Retrieves detailed list of mandatory surveys blocking the portal.

- **Endpoint**: `GET /api/v1/student/forms/surveys/pending`
- **Response**: `ApiResponse<MandatoryAssignment[]>`
  _Note: In `pending`, the objects might contain more fields than `MandatoryAssignment` if returned directly from the model._

### 2.3 Get Survey Detail

Fetches the full form structure (sections, questions, options) for display.

- **Endpoint**: `GET /api/v1/student/forms/surveys/{form_id}`
- **Response**: `ApiResponse<FormDetail>`

### 2.4 Submit Survey

Persists student responses.

- **Endpoint**: `POST /api/v1/student/forms/surveys/{form_id}/submit`
- **Request Body**: `FormSubmissionRequest`
- **Response**: `ApiResponse<any>` (Standard success/failure response)

## 3. Usage Flow (Gate Logic)

1. On app load, call **2.1 Get Student Context**.
2. If `survey_gate.blocked` is `true`:
    - Show the mandatory survey overlay.
    - Use `survey_gate.mandatory_assignments` to list the surveys.
    - Redirect student to complete these surveys.
3. Once completed, the student can call **2.1** again to verify the block is lifted.

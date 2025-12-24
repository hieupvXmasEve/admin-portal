# Student Portal - Query Forms API Documentation

## Overview

APIs for managing and submitting query-type forms (yêu cầu/thắc mắc) in the Student Portal. Unlike surveys, query forms are generally accessible based on campus and time, with administrative scopes (department, semester) used primarily for routing and mandatory tracking.

**Base URL**: `/api/v1`  
**Authentication**: Bearer Token (Student)  
**Content-Type**: `application/json`

---

## TypeScript Types

```typescript
// Form Type
type FormType = 'query' | 'survey' | 'feedback';

// Question Types
type QuestionType = 'short_text' | 'long_text' | 'single_choice' | 'multi_choice' | 'rating' | 'date' | 'number' | 'file';

// Option for Choice Questions
interface QuestionOption {
    id: number;
    value: string;
    label: string;
    order_index: number;
    allows_free_text: boolean;
}

// Question Definition
interface Question {
    id: number;
    code: string;
    text: string;
    type: QuestionType;
    is_required: boolean;
    help_text: string | null;
    order_index: number;
    options: QuestionOption[] | null;
}

// Form Section
interface FormSection {
    id: number;
    title: string;
    description: string | null;
    order_index: number;
    questions: Question[];
}

// Form Target (Run/Deployment)
interface FormTarget {
    id: number;
    form_version_id: number;
    campus_id: number | null;
    campus_name: string | null;
    scope_type: 'global' | 'semester' | 'course' | 'department';
    scope_id: number | null;
    start_at: string;
    end_at: string | null;
    submission_limit_per_user: number | null;
    is_active: boolean;
}

// Minimal Form Info (List)
interface FormListItem {
    id: number;
    code: string;
    type: FormType;
    title: string;
    description: string | null;
    status: string;
    created_at: string;
    updated_at: string;
}

// Detailed Form Info
interface FormDetail {
    id: number;
    code: string;
    type: FormType;
    title: string;
    description: string | null;
    current_version: {
        id: number;
        version_no: number;
        sections: FormSection[] | null;
        questions: Question[] | null; // Standalone questions
    };
    targets: FormTarget[]; // Filtered eligible targets for the student
}

// Submit Response Request
interface SubmitQueryRequest {
    campus_id?: number; // Defaults to student campus
    target_scope_type?: string; // Optional: specify target scope
    target_scope_id?: number; // Optional: specify target scope
    answers: Array<{
        question_id: number;
        answer_text?: string | null;
        answer_number?: number | null;
        answer_date?: string | null;
        selected_options?: Array<{
            option_id: number;
            free_text?: string | null;
        }>;
    }>;
}

// API Generic Response
interface ApiResponse<T> {
    success: boolean;
    message: string;
    data: T;
}
```

---

## Endpoints

### 1. List Available Query Forms

**GET** `/student/forms?type=query`

Retrieve a list of available query forms. A form is available if it has at least one active "Run" (Target) for the student's campus.

**Query Parameters:**

- `campus_id` (optional, number): Specify campus ID. Defaults to student's campus.

**Response:**

```typescript
ApiResponse<FormListItem[]>;
```

---

### 2. Get Query Form Detail (With Active Runs)

**GET** `/student/forms/query/runs`

This is the primary endpoint for the "Submit a Request" UI. It returns forms that have active runs (targets), including the question structure and the specific runs available.

**Response:**

```typescript
ApiResponse<FormDetail[]>;
```

---

### 3. Get Specific Query Detail

**GET** `/student/forms/query/{form}`

Get the full structure of a specific query form.

**Path Parameters:**

- `form` (number): The Form ID.

**Response:**

```typescript
ApiResponse<FormDetail>;
```

---

### 4. Submit Query Response

**POST** `/student/forms/query/{form}/submit`

Submit a response for a specific query run.

**Path Parameters:**

- `form` (number): The Form ID.

**Request Body:**

```typescript
SubmitQueryRequest;
```

**Note on Target Identification:**
If multiple active targets exist for the same form (e.g., a Global run and a specific Semester run), the system will automatically pick the first eligible one unless `target_scope_type` and `target_scope_id` are explicitly provided in the request body.

**Response Example:**

```json
{
    "success": true,
    "message": "Form submitted successfully",
    "data": {
        "id": 456,
        "submitted_by_student_id": 123,
        "form_id": 10,
        "submitted_at": "2024-03-20T10:00:00Z"
    }
}
```

---

## Form Engine Integration Guide

### Mapping Answer Types

When building the dynamic form UI, map the values to the corresponding fields in the `answers` array:

| Question Type                   | Request Field      | Format                                                        |
| :------------------------------ | :----------------- | :------------------------------------------------------------ |
| `short_text`, `long_text`       | `answer_text`      | `string`                                                      |
| `number`, `rating`              | `answer_number`    | `number`                                                      |
| `date`                          | `answer_date`      | `YYYY-MM-DD`                                                  |
| `single_choice`, `multi_choice` | `selected_options` | `Array<{ option_id: number, free_text?: string }>`            |
| `file`                          | N/A                | Handled via separate upload and file reference (see File API) |

### Scope Privacy

For `type=query`, the `scope_type` (department, semester, etc.) provided in the `targets` array is mostly for administrative grouping. In the Student UI, you can usually just display the most relevant Run or the Form title itself.

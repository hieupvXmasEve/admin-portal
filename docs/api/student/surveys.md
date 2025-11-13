# Student Portal - Surveys API Documentation

## Overview

APIs for survey management in the Student Portal. Students can view pending surveys, complete surveys, and view their submitted responses.

**Base URL**: `/api/v1`  
**Authentication**: Bearer Token (Student)  
**Content-Type**: `application/json`

---

## TypeScript Types

```typescript
// Survey Status
type SurveyStatus = 'pending' | 'completed';

// Question Types
type QuestionType =
    | 'short_text'
    | 'long_text'
    | 'single_choice'
    | 'multi_choice'
    | 'likert'
    | 'rating'
    | 'date'
    | 'number'
    | 'file'
    | 'matrix'
    | 'yes_no';

// Semester
interface Semester {
    id: number;
    code: string;
    name: string;
}

// Course Offering
interface CourseOffering {
    id: number;
    course_code: string;
    course_title: string;
    semester?: Semester | null;
}

// Option
interface QuestionOption {
    id: number;
    text: string;
    value: string;
}

// Question
interface Question {
    id: number;
    code: string;
    text: string;
    type: QuestionType;
    is_required: boolean;
    help_text: string | null;
    options: QuestionOption[];
}

// Form Section
interface FormSection {
    id: number;
    title: string;
    order_index: number;
    questions: Question[];
}

// Form Version
interface FormVersion {
    id: number;
    version_no: number;
    sections: FormSection[];
    questions: Question[]; // Questions without section (section_id is null)
}

// Pending Survey (List Item)
interface PendingSurvey {
    id: number;
    form_survey_id: number;
    form_id: number;
    form_title: string;
    course_code: string;
    course_title: string;
    semester: string | null;
    status: SurveyStatus;
    created_at: string; // ISO 8601 datetime
}

// Completed Survey (List Item)
interface CompletedSurvey {
    id: number;
    form_survey_id: number;
    form_id: number;
    form_title: string;
    course_code: string;
    course_title: string;
    semester: string | null;
    completed_at: string; // ISO 8601 datetime
}

// Survey Detail
interface SurveyDetail {
    id: number;
    form_survey_id: number;
    form_id: number;
    form_version_id: number;
    form_title: string;
    form_description: string | null;
    course_code: string;
    course_title: string;
    semester: string | null;
    status: SurveyStatus;
    form_version: FormVersion;
}

// Answer Attachment
interface AnswerAttachment {
    id: number;
    file_name: string;
    file_path: string;
    file_size: number;
}

// Selected Option
interface SelectedOption {
    id: number;
    text: string;
    value: string;
}

// Survey Answer
interface SurveyAnswer {
    question_id: number;
    question_text: string;
    question_type: QuestionType;
    answer_text: string | null;
    answer_number: number | null;
    answer_date: string | null; // YYYY-MM-DD
    selected_options: SelectedOption[];
    attachments: AnswerAttachment[];
}

// Survey Response
interface SurveyResponse {
    id: number;
    submitted_at: string; // ISO 8601 datetime
    answers: SurveyAnswer[];
}

// Submit Answer Request
interface SubmitAnswer {
    question_id: number;
    answer_text?: string | null;
    answer_number?: number | null;
    answer_date?: string | null; // YYYY-MM-DD
    selected_options?: Array<{
        option_id: number;
        free_text?: string | null;
    }>;
    file?: File; // For file upload questions
    comment?: string | null;
}

// Submit Survey Request
interface SubmitSurveyRequest {
    answers: SubmitAnswer[];
}

// Submit Survey Response
interface SubmitSurveyResponse {
    response_id: number;
}

// API Response
interface ApiResponse<T = any> {
    success: boolean;
    message: string;
    data?: T;
    errors?: Record<string, string[]>;
}
```

---

## Endpoints

### 1. Get Pending Surveys

**GET** `/surveys/pending`

Get all pending (incomplete) surveys for the authenticated student.

**Query Parameters:**
- `campus_id` (number, optional) - Required when accessing as parent user to specify which child's surveys to retrieve

**Response:**
```typescript
ApiResponse<PendingSurvey[]>
```

**Example:**
```typescript
const { data } = await api.get('/surveys/pending');
// Returns array of pending surveys
```

**Response Example:**
```json
{
    "success": true,
    "message": "Pending surveys retrieved successfully.",
    "data": [
        {
            "id": 1,
            "form_survey_id": 10,
            "form_id": 5,
            "form_title": "Course Evaluation Survey",
            "course_code": "CS101",
            "course_title": "Introduction to Computer Science",
            "semester": "2024-Fall",
            "status": "pending",
            "created_at": "2024-01-15T10:30:00.000000Z"
        }
    ]
}
```

---

### 2. Get Completed Surveys

**GET** `/surveys/completed`

Get all completed surveys for the authenticated student.

**Query Parameters:**
- `campus_id` (number, optional) - Required when accessing as parent user to specify which child's surveys to retrieve

**Response:**
```typescript
ApiResponse<CompletedSurvey[]>
```

**Example:**
```typescript
const { data } = await api.get('/surveys/completed');
// Returns array of completed surveys, ordered by completed_at desc
```

**Response Example:**
```json
{
    "success": true,
    "message": "Completed surveys retrieved successfully.",
    "data": [
        {
            "id": 2,
            "form_survey_id": 11,
            "form_id": 5,
            "form_title": "Course Evaluation Survey",
            "course_code": "CS102",
            "course_title": "Data Structures",
            "semester": "2024-Fall",
            "completed_at": "2024-01-20T14:30:00.000000Z"
        }
    ]
}
```

---

### 3. Get Survey Details

**GET** `/surveys/{survey}`

Get detailed survey information including form version with sections and questions.

**Path Parameters:**
- `survey` (number) - StudentFormSurvey ID

**Query Parameters:**
- `campus_id` (number, optional) - Required when accessing as parent user to specify which child's surveys to retrieve

**Response:**
```typescript
ApiResponse<SurveyDetail>
```

**Example:**
```typescript
const { data } = await api.get(`/surveys/${surveyId}`);
// Returns full survey details with form version structure
```

**Response Example:**
```json
{
    "success": true,
    "message": "Survey retrieved successfully.",
    "data": {
        "id": 1,
        "form_survey_id": 10,
        "form_id": 5,
        "form_version_id": 8,
        "form_title": "Course Evaluation Survey",
        "form_description": "Please provide your feedback on this course",
        "course_code": "CS101",
        "course_title": "Introduction to Computer Science",
        "semester": "2024-Fall",
        "status": "pending",
        "form_version": {
            "id": 8,
            "version_no": 2,
            "sections": [
                {
                    "id": 1,
                    "title": "Course Content",
                    "order_index": 1,
                    "questions": [
                        {
                            "id": 10,
                            "code": "Q1",
                            "text": "How would you rate the course content?",
                            "type": "rating",
                            "is_required": true,
                            "help_text": "Rate from 1 to 5",
                            "options": [
                                {
                                    "id": 1,
                                    "text": "1 - Poor",
                                    "value": "1"
                                },
                                {
                                    "id": 2,
                                    "text": "5 - Excellent",
                                    "value": "5"
                                }
                            ]
                        }
                    ]
                }
            ],
            "questions": [
                {
                    "id": 15,
                    "code": "Q5",
                    "text": "Additional comments?",
                    "type": "long_text",
                    "is_required": false,
                    "help_text": null,
                    "options": []
                }
            ]
        }
    }
}
```

**Notes:**
- `form_version.sections` contains questions that belong to sections
- `form_version.questions` contains questions without a section (standalone questions)
- `semester` can be `null` if not associated with a semester
- `form_description` can be `null`
- `help_text` can be `null` for questions
- Questions in sections have `options` array (empty for non-choice questions)
- Standalone questions also have `options` array

---

### 4. Submit Survey

**POST** `/surveys/{survey}/submit`

Submit survey responses. All required questions must be answered.

**Path Parameters:**
- `survey` (number) - StudentFormSurvey ID

**Query Parameters:**
- `campus_id` (number, optional) - Required when accessing as parent user to specify which child's surveys to retrieve

**Request Body:**
```typescript
SubmitSurveyRequest
```

**Response:**
```typescript
ApiResponse<SubmitSurveyResponse>
```

**Example:**
```typescript
await api.post(`/surveys/${surveyId}/submit`, {
    answers: [
        {
            question_id: 10,
            answer_number: 5
        },
        {
            question_id: 11,
            selected_options: [
                { option_id: 3 }
            ]
        },
        {
            question_id: 12,
            answer_text: "Great course!"
        },
        {
            question_id: 13,
            answer_date: "2024-01-20"
        },
        {
            question_id: 14,
            selected_options: [
                { option_id: 5, free_text: "Other reason" }
            ]
        }
    ]
});
```

**Request Body Example:**
```json
{
    "answers": [
        {
            "question_id": 10,
            "answer_number": 5
        },
        {
            "question_id": 11,
            "selected_options": [
                { "option_id": 3 }
            ]
        },
        {
            "question_id": 12,
            "answer_text": "Great course!"
        },
        {
            "question_id": 13,
            "answer_date": "2024-01-20"
        },
        {
            "question_id": 14,
            "selected_options": [
                { "option_id": 5, "free_text": "Other reason" }
            ]
        },
        {
            "question_id": 15,
            "comment": "Optional comment"
        }
    ]
}
```

**Response Example:**
```json
{
    "success": true,
    "message": "Survey submitted successfully.",
    "data": {
        "response_id": 123
    }
}
```

**Answer Format by Question Type:**
- `short_text`, `long_text`, `yes_no`: Use `answer_text` (string)
- `number`, `rating`: Use `answer_number` (number)
- `date`: Use `answer_date` (string, format: "YYYY-MM-DD")
- `single_choice`, `multi_choice`, `likert`: Use `selected_options` array with `option_id`
- `matrix`: Use `answer_text` (JSON stringified object)
- `file`: Use `file` (multipart/form-data, handled separately)

**Validation Rules:**
- `answers` is required and must be an array
- Each answer must have `question_id` (required, integer, must exist in questions table)
- Required questions must be answered
- Selected options must belong to the question
- File uploads are handled separately (not in JSON body)

---

### 5. Get Survey Responses

**GET** `/surveys/{survey}/responses`

Get the submitted response for a completed survey. Only available for completed surveys.

**Path Parameters:**
- `survey` (number) - StudentFormSurvey ID

**Query Parameters:**
- `campus_id` (number, optional) - Required when accessing as parent user to specify which child's surveys to retrieve

**Response:**
```typescript
ApiResponse<{
    response: SurveyResponse;
}>
```

**Example:**
```typescript
const { data } = await api.get(`/surveys/${surveyId}/responses`);
// Returns the submitted response with all answers
```

**Response Example:**
```json
{
    "success": true,
    "message": "Response retrieved successfully.",
    "data": {
        "response": {
            "id": 123,
            "submitted_at": "2024-01-20T14:30:00.000000Z",
            "answers": [
                {
                    "question_id": 10,
                    "question_text": "How would you rate the course content?",
                    "question_type": "rating",
                    "answer_text": null,
                    "answer_number": 5,
                    "answer_date": null,
                    "selected_options": [],
                    "attachments": []
                },
                {
                    "question_id": 11,
                    "question_text": "What did you like most?",
                    "question_type": "multi_choice",
                    "answer_text": null,
                    "answer_number": null,
                    "answer_date": null,
                    "selected_options": [
                        {
                            "id": 3,
                            "text": "Clear explanations",
                            "value": "clear"
                        },
                        {
                            "id": 4,
                            "text": "Practical examples",
                            "value": "examples"
                        }
                    ],
                    "attachments": []
                },
                {
                    "question_id": 12,
                    "question_text": "Additional comments?",
                    "question_type": "long_text",
                    "answer_text": "Great course!",
                    "answer_number": null,
                    "answer_date": null,
                    "selected_options": [],
                    "attachments": []
                },
                {
                    "question_id": 13,
                    "question_text": "When did you complete the course?",
                    "question_type": "date",
                    "answer_text": null,
                    "answer_number": null,
                    "answer_date": "2024-01-20",
                    "selected_options": [],
                    "attachments": []
                },
                {
                    "question_id": 14,
                    "question_text": "Upload assignment file",
                    "question_type": "file",
                    "answer_text": null,
                    "answer_number": null,
                    "answer_date": null,
                    "selected_options": [],
                    "attachments": [
                        {
                            "id": 1,
                            "file_name": "assignment.pdf",
                            "file_path": "/uploads/form_attachment/assignment.pdf",
                            "file_size": 102400
                        }
                    ]
                }
            ]
        }
    }
}
```

**Notes:**
- Only available for completed surveys
- Returns all answers submitted for the survey
- Answer fields (`answer_text`, `answer_number`, `answer_date`) are `null` if not applicable to the question type
- `selected_options` is an empty array if question type doesn't use options
- `attachments` is an empty array if no files were uploaded for the answer
- `answer_date` format is "YYYY-MM-DD" (date only, no time)

---

## Error Responses

All endpoints may return error responses:

```typescript
// 400 Bad Request
{
    success: false,
    message: "Validation failed",
    errors: {
        field_name: ["Error message"]
    }
}

// 401 Unauthorized
{
    success: false,
    message: "Student not found"
}

// 403 Forbidden
{
    success: false,
    message: "Access denied. This survey does not belong to you."
}

// 404 Not Found
{
    success: false,
    message: "Survey not found"
}

// 422 Validation Error
{
    success: false,
    message: "Validation failed",
    errors: {
        answers: ["The answers field is required."],
        "answers.0.question_id": ["The question id field is required."]
    }
}

// 422 Business Logic Error
{
    success: false,
    message: "This survey has already been completed.",
    errors: {
        survey: ["This survey has already been completed."]
    }
}

// 422 Not Completed Error
{
    success: false,
    message: "Survey has not been completed yet.",
    errors: {
        survey: ["Survey has not been completed yet."]
    }
}

// 500 Server Error
{
    success: false,
    message: "Failed to retrieve surveys"
}
```

---

## Usage Examples

### Using with `useApi()` composable:

```typescript
import { useApi } from '@/composables/useApi';

const api = useApi();

// Get pending surveys
const { data: pendingSurveys } = await api.get('/surveys/pending');

// Get survey details
const { data: surveyDetail } = await api.get(`/surveys/${surveyId}`);

// Submit survey
await api.post(`/surveys/${surveyId}/submit`, {
    answers: [
        {
            question_id: 10,
            answer_number: 5
        },
        {
            question_id: 11,
            selected_options: [{ option_id: 3 }]
        }
    ]
});

// Get completed survey responses
const { data: response } = await api.get(`/surveys/${surveyId}/responses`);
```

### Using with Inertia.js router:

```typescript
import { router } from '@inertiajs/vue3';

router.visit('/surveys/pending', {
    preserveState: true,
    preserveScroll: true,
});
```

### Handling Parent User Access:

```typescript
// When accessing as parent user, include campus_id
const { data } = await api.get('/surveys/pending', {
    campus_id: childCampusId
});
```

---

## Notes

- All datetime strings are in ISO 8601 format (`YYYY-MM-DDTHH:mm:ss.000000Z`)
- Date-only fields (`answer_date`) use format `YYYY-MM-DD`
- `semester` field can be `null` if survey is not associated with a semester
- `form_description` can be `null`
- `help_text` can be `null` for questions
- Questions in `form_version.sections` belong to sections
- Questions in `form_version.questions` are standalone (no section)
- `options` array is empty for non-choice question types
- Answer fields are `null` when not applicable to the question type
- `selected_options` and `attachments` are empty arrays when not applicable
- Parent users can access surveys by providing `campus_id` query parameter
- Survey can only be submitted once (status must be `pending`)
- Response can only be viewed after survey is completed
- File uploads for `file` type questions are handled via multipart/form-data (not documented in JSON examples)


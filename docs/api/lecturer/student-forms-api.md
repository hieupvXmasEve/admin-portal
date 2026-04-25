# Student Forms API

## TypeScript Types

```typescript
type FormType = 'feedback' | 'survey' | 'query';
type FormStatus = 'active' | 'inactive' | 'archived';
type QuestionType = 'short_text' | 'long_text' | 'single_choice' | 'multi_choice' | 'likert' | 'rating' | 'date' | 'number' | 'file' | 'matrix' | 'yes_no';
type ScopeType = 'global' | 'course' | 'section' | 'class_session';
type Priority = 'low' | 'normal' | 'high';
type Origin = 'web' | 'mobile' | 'api';

interface Campus {
  id: number;
  name: string;
  code: string;
  address?: string;
}

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
  validation_json: any | null;
  visibility_condition_json: any | null;
  options: Option[];
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
  effective_from: string;
  effective_to: string | null;
  sections: FormSection[];
  questions: Question[];
}

interface FormTarget {
  id: number;
  form_version_id: number;
  campus_id: number | null;
  campus_name: string | null;
  scope_type: ScopeType;
  scope_id: number | null;
  start_at: string;
  end_at: string | null;
  submission_limit_per_user: number;
  is_active: boolean;
}

interface FormListItem {
  id: number;
  code: string;
  type: FormType;
  title: string;
  description: string | null;
  status: FormStatus;
  created_at: string;
  updated_at: string;
}

interface FormDetail {
  id: number;
  code: string;
  type: FormType;
  title: string;
  description: string | null;
  status: FormStatus;
  created_by: number;
  current_version: FormVersion;
  targets: FormTarget[];
  created_at: string;
  updated_at: string;
}

interface SelectedOption {
  option_id: number;
  free_text?: string;
}

interface FormAnswer {
  question_id: number;
  answer_text?: string;
  answer_number?: number;
  answer_date?: string;
  comment?: string;
  selected_options?: SelectedOption[];
}

interface QueryData {
  topic_id?: number;
  custom_topic_text?: string;
  priority?: Priority;
}

interface FormSubmissionRequest {
  campus_id?: number;
  target_scope_type: ScopeType;
  target_scope_id?: number;
  anonymized?: boolean;
  origin?: Origin;
  answers: FormAnswer[];
  query?: QueryData;
}
```

## Endpoints

### GET `/api/v1/student/forms`

**Query Parameters:**
```typescript
{
  type: FormType; // required
}
```

**Response:**
```typescript
{
  data: FormListItem[];
}
```

### GET `/api/v1/student/forms/{form_id}`

**Response:**
```typescript
{
  data: FormDetail;
}
```

### POST `/api/v1/student/forms/{form_id}`

**Request Body:**
```typescript
FormSubmissionRequest
```

**Response:**
```typescript
{
  message: string;
  data: {
    id: number;
    form: { id: number; title: string; type: FormType; };
    campus: Campus;
    target_scope: { type: ScopeType; id: number | null; };
    submitter: { id: number; name: string; student_id: string; };
    anonymized: boolean;
    status: string;
    origin: Origin;
    submitted_at: string;
    answers: any[];
  };
}
```

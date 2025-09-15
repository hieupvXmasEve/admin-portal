# Product & Engineering Spec-driven development — Feedback / Surveys / Queries (Multi-Campus)

## 1) Goals & Scope
* Collect structured inputs in three modes:
    1. **Feedback** (role-gated visibility, often anonymous)
    2. **Surveys** (auto-opened after each class session)
    3. **Queries** (student can submit anytime; includes topics, “Other”, replies, and optional files)
* **Flexible questions**: single/multi choice, free-text, rating, Likert, number, date, matrix, file.
* **Multi-campus** governance: who can see/submit/review depends on **`campus_user_role`**.
* **Versioning**: edits to a form never break old responses.
* **Privacy**: aggregate-only views with thresholds; optional anonymity.

## 2) Core Concepts

* **Dynamic Form Engine** shared by all three types.
* **Targets**: where/when a form is available (campus, course/section/session, global).
* **Visibility**: who may see/submit a form; who may see results, at what detail.
* **Responses**: each submission, with typed answers + attachments.
* **Queries Workflow** (optional): tickets, assignment, SLA-like statuses, threaded replies.

## 3) Data Model (ER overview)

* Tenants: `campuses`, `roles`, `campus_user_role (campus_id, user_id, role_id)`
* Forming: `forms → form_versions → sections → questions → options`
* Targeting: `form_targets (campus/context/time window)`
* Security: `form_visibility_roles`, `form_result_visibility`
* Collection: `responses → answers (+ answer_options) → attachments`
* Queries: `query_topics → queries_tickets → query_replies`

> Campus awareness:
> * **Access control** uses `campus_user_role`.
> * **Availability**: `form_targets.campus_id` (nullable to mean “all campuses”).
> * **Responses** denormalize `campus_id` (copied at submit time).

## 4) MySQL Schema (DDL — representative; adjust to your existing `users/courses/...`)
All tables **InnoDB**, **utf8mb4**, **MySQL 8.0+**.
### 4.1 Multi-campus RBAC

```sql
CREATE TABLE campuses (
  id            BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  code          VARCHAR(50) NOT NULL UNIQUE,
  name          VARCHAR(255) NOT NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE roles (
  id            BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  code          VARCHAR(50) NOT NULL UNIQUE,  -- e.g. student, instructor, advisor, qa, admin
  name          VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE campus_user_role (
  id            BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  campus_id     BIGINT UNSIGNED NOT NULL,
  user_id       BIGINT UNSIGNED NOT NULL,
  role_id       BIGINT UNSIGNED NOT NULL,
  status        ENUM('active','inactive') NOT NULL DEFAULT 'active',
  effective_from DATETIME NULL,
  effective_to   DATETIME NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_campus_user_role (campus_id, user_id, role_id),
  KEY idx_campus_user (campus_id, user_id),
  CONSTRAINT fk_cur_campus FOREIGN KEY (campus_id) REFERENCES campuses(id),
  CONSTRAINT fk_cur_role   FOREIGN KEY (role_id)   REFERENCES roles(id)
  -- fk to users(user_id) depending on your users table name
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 4.2 Form Engine

```sql
CREATE TABLE forms (
  id            BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  code          VARCHAR(100) NOT NULL UNIQUE,
  type          ENUM('feedback','survey','query') NOT NULL,
  title         VARCHAR(255) NOT NULL,
  description   TEXT NULL,
  status        ENUM('draft','active','archived') NOT NULL DEFAULT 'draft',
  created_by    BIGINT UNSIGNED NULL,  -- users.id
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE form_versions (
  id            BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  form_id       BIGINT UNSIGNED NOT NULL,
  version_no    INT NOT NULL,
  is_published  TINYINT(1) NOT NULL DEFAULT 0,
  effective_from DATETIME NULL,
  effective_to   DATETIME NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_form_version (form_id, version_no),
  CONSTRAINT fk_fv_form FOREIGN KEY (form_id) REFERENCES forms(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE form_sections (
  id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  form_version_id BIGINT UNSIGNED NOT NULL,
  title           VARCHAR(255) NOT NULL,
  order_index     INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_fs_fv FOREIGN KEY (form_version_id) REFERENCES form_versions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE questions (
  id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  form_version_id BIGINT UNSIGNED NOT NULL,
  section_id      BIGINT UNSIGNED NULL,
  code            VARCHAR(100) NOT NULL,  -- unique per version
  text            TEXT NOT NULL,
  type            ENUM('short_text','long_text','single_choice','multi_choice',
                       'likert','rating','date','number','file','matrix','yes_no') NOT NULL,
  is_required     TINYINT(1) NOT NULL DEFAULT 0,
  help_text       TEXT NULL,
  order_index     INT NOT NULL DEFAULT 0,
  validation_json JSON NULL,
  visibility_condition_json JSON NULL,
  UNIQUE KEY uq_q_code (form_version_id, code),
  KEY idx_q_fv (form_version_id),
  CONSTRAINT fk_q_fv FOREIGN KEY (form_version_id) REFERENCES form_versions(id),
  CONSTRAINT fk_q_section FOREIGN KEY (section_id) REFERENCES form_sections(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE options (
  id           BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  question_id  BIGINT UNSIGNED NOT NULL,
  value        VARCHAR(100) NOT NULL,
  label        VARCHAR(255) NOT NULL,
  order_index  INT NOT NULL DEFAULT 0,
  allows_free_text TINYINT(1) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_opt (question_id, value),
  CONSTRAINT fk_opt_q FOREIGN KEY (question_id) REFERENCES questions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 4.3 Targeting & Visibility (Campus-aware)

```sql
CREATE TABLE form_targets (
  id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  form_id         BIGINT UNSIGNED NOT NULL,
  form_version_id BIGINT UNSIGNED NULL,  -- null: use latest published
  campus_id       BIGINT UNSIGNED NULL,  -- null: all campuses
  scope_type      ENUM('section','class_session','course','global') NOT NULL,
  scope_id        BIGINT UNSIGNED NULL,
  start_at        DATETIME NOT NULL,
  end_at          DATETIME NULL,
  submission_limit_per_user INT NOT NULL DEFAULT 1,
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ft_lookup (form_id, campus_id, scope_type, scope_id, start_at),
  CONSTRAINT fk_ft_form FOREIGN KEY (form_id) REFERENCES forms(id),
  CONSTRAINT fk_ft_fv   FOREIGN KEY (form_version_id) REFERENCES form_versions(id),
  CONSTRAINT fk_ft_campus FOREIGN KEY (campus_id) REFERENCES campuses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- who can SEE/SUBMIT the form
CREATE TABLE form_visibility_roles (
  id        BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  form_id   BIGINT UNSIGNED NOT NULL,
  role_id   BIGINT UNSIGNED NOT NULL,
  UNIQUE KEY uq_fvr (form_id, role_id),
  CONSTRAINT fk_fvr_form FOREIGN KEY (form_id) REFERENCES forms(id),
  CONSTRAINT fk_fvr_role FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- who can SEE RESULTS and at what detail
CREATE TABLE form_result_visibility (
  id        BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  form_id   BIGINT UNSIGNED NOT NULL,
  role_id   BIGINT UNSIGNED NOT NULL,
  visibility_level ENUM('own_submission','aggregated','full_detail') NOT NULL,
  min_aggregation_threshold INT NULL,  -- e.g., 5 for anonymity
  UNIQUE KEY uq_fres (form_id, role_id),
  CONSTRAINT fk_fres_form FOREIGN KEY (form_id) REFERENCES forms(id),
  CONSTRAINT fk_fres_role FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 4.4 Responses, Answers, Attachments

```sql
CREATE TABLE responses (
  id                BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  form_id           BIGINT UNSIGNED NOT NULL,
  form_version_id   BIGINT UNSIGNED NOT NULL,
  campus_id         BIGINT UNSIGNED NULL,  -- denormalized for partitioning/filter
  target_scope_type ENUM('section','class_session','course','global') NOT NULL,
  target_scope_id   BIGINT UNSIGNED NULL,
  submitted_by_user_id BIGINT UNSIGNED NULL, -- null if anonymized
  anonymized        TINYINT(1) NOT NULL DEFAULT 0,
  status            ENUM('draft','submitted','approved','rejected') NOT NULL DEFAULT 'submitted',
  reviewed_by_user_id BIGINT UNSIGNED NULL,
  reviewed_at      DATETIME NULL,
  origin            ENUM('web','mobile','api') NOT NULL DEFAULT 'web',
  submitted_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_resp_lookup (form_id, campus_id, submitted_at),
  CONSTRAINT fk_r_form FOREIGN KEY (form_id) REFERENCES forms(id),
  CONSTRAINT fk_r_fv FOREIGN KEY (form_version_id) REFERENCES form_versions(id),
  CONSTRAINT fk_r_campus FOREIGN KEY (campus_id) REFERENCES campuses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE answers (
  id           BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  response_id  BIGINT UNSIGNED NOT NULL,
  question_id  BIGINT UNSIGNED NOT NULL,
  answer_text  LONGTEXT NULL,     -- for free text, JSON (matrix), etc.
  answer_number DECIMAL(12,4) NULL,
  answer_date  DATE NULL,
  comment      TEXT NULL,
  KEY idx_ans_q (question_id),
  CONSTRAINT fk_a_resp FOREIGN KEY (response_id) REFERENCES responses(id),
  CONSTRAINT fk_a_q FOREIGN KEY (question_id) REFERENCES questions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE answer_options (
  id           BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  answer_id    BIGINT UNSIGNED NOT NULL,
  option_id    BIGINT UNSIGNED NOT NULL,
  free_text    VARCHAR(500) NULL, -- for "Other"
  UNIQUE KEY uq_ansopt (answer_id, option_id),
  CONSTRAINT fk_ao_ans FOREIGN KEY (answer_id) REFERENCES answers(id),
  CONSTRAINT fk_ao_opt FOREIGN KEY (option_id) REFERENCES options(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE attachments (
  id           BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  response_id  BIGINT UNSIGNED NULL,
  answer_id    BIGINT UNSIGNED NULL,
  storage_key  VARCHAR(500) NOT NULL, -- or file_url
  file_name    VARCHAR(255) NOT NULL,
  mime_type    VARCHAR(150) NOT NULL,
  size_bytes   BIGINT UNSIGNED NOT NULL,
  uploaded_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_att_resp (response_id),
  KEY idx_att_ans  (answer_id),
  CONSTRAINT fk_att_resp FOREIGN KEY (response_id) REFERENCES responses(id),
  CONSTRAINT fk_att_ans  FOREIGN KEY (answer_id) REFERENCES answers(id),
  CHECK ((response_id IS NOT NULL) XOR (answer_id IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 4.5 Queries Workflow

```sql
CREATE TABLE query_topics (
  id           BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  title        VARCHAR(255) NOT NULL,
  description  TEXT NULL,
  is_active    TINYINT(1) NOT NULL DEFAULT 1,
  order_index  INT NOT NULL DEFAULT 0,
  UNIQUE KEY uq_qtopic (title)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE queries_tickets (
  id           BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  response_id  BIGINT UNSIGNED NOT NULL, -- response of a form with type='query'
  topic_id     BIGINT UNSIGNED NULL,
  custom_topic_text VARCHAR(255) NULL,
  status       ENUM('open','pending','answered','closed') NOT NULL DEFAULT 'open',
  priority     ENUM('low','normal','high') NOT NULL DEFAULT 'normal',
  assigned_to_user_id BIGINT UNSIGNED NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  closed_at    DATETIME NULL,
  KEY idx_qt_status_assignee (status, assigned_to_user_id, created_at),
  CONSTRAINT fk_qt_resp FOREIGN KEY (response_id) REFERENCES responses(id),
  CONSTRAINT fk_qt_topic FOREIGN KEY (topic_id) REFERENCES query_topics(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE query_replies (
  id           BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  ticket_id    BIGINT UNSIGNED NOT NULL,
  author_user_id BIGINT UNSIGNED NOT NULL,
  message      LONGTEXT NOT NULL,
  is_official_answer TINYINT(1) NOT NULL DEFAULT 0,
  attachment_id BIGINT UNSIGNED NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_qr_ticket (ticket_id, created_at),
  CONSTRAINT fk_qr_ticket FOREIGN KEY (ticket_id) REFERENCES queries_tickets(id),
  CONSTRAINT fk_qr_att    FOREIGN KEY (attachment_id) REFERENCES attachments(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## 5) Access Control (Policy)

* **Eligibility to SUBMIT a form (students only)**: The authenticated principal **must be a student** (`role.code = 'student'`) with an active `campus_user_role` matching the target `campus_id`, and must also satisfy:

    * current time ∈ `[start_at, end_at]`,
    * any scope constraints (e.g., enrolled in the target section/session), and
    * `submission_limit_per_user` for that target.

* **Eligibility to REVIEW / APPROVE a submission**: Campus staff/users with `campus_user_role` where `role.code ∈ ('advisor','instructor','qa','admin')` may review each `response` and mark it `approved` or `rejected`.

* **Eligibility to VIEW RESULTS** = user has `campus_user_role` whose `role_id` appears in `form_result_visibility`, and:

    * If `visibility_level='aggregated'`, only aggregated stats and only if `n ≥ min_aggregation_threshold`.
    * If `visibility_level='own_submission'`, they only see their own `responses`.
    * If `visibility_level='full_detail'`, they may see row-level data unless `anonymized=1`.
* **Eligibility to VIEW RESULTS** = user has `campus_user_role` whose `role_id` appears in `form_result_visibility`, and:

    * If `visibility_level='aggregated'`, only aggregated stats and only if `n ≥ min_aggregation_threshold`.
    * If `visibility_level='own_submission'`, they only see their own `responses`.
    * If `visibility_level='full_detail'`, they may see row-level data unless `anonymized=1`.
* **Queries tickets** are visible to:

    * the submitter (always),
    * assigned staff and roles defined by institution policy (e.g., `advisor`, `qa`, `admin`) on the same campus,
    * anyone with `full_detail` visibility for the query form.

## 6) Key Workflows

### A) Feedback (role-gated, often anonymous)

1. Seed `forms(type='feedback')` + `form_versions` + question set (Likert, free-text).
2. Set `form_visibility_roles` to `student` only; `form_result_visibility` for `instructor, qa` with `aggregated` and a threshold (e.g., 5).
3. Add `form_targets` per campus/section with time window.
4. When student submits, store `responses.anonymized=1` (and null `submitted_by_user_id`) if anonymity is required.

### B) Post-Session Surveys (auto-open)

1. Seed `forms(type='survey')`.
2. An after-class job creates `form_targets(campus_id, scope_type='class_session', scope_id, start_at=end_of_session, end_at=start+7d, submission_limit_per_user=1)`.
3. Students enrolled in that session’s section and campus see the survey.

### C) Queries (anytime, topics + “Other”, files)

1. Seed `query_topics` (Tuition, Timetable, Grades/Recheck, Student Affairs, Other).
2. Seed `forms(type='query')`:

    * Q1: `single_choice` mapped to `query_topics`, last option `Other` (`allows_free_text=1`).
    * Q2: `long_text` description.
    * Q3: `file` (optional).
3. On submit, create `responses` (+ `attachments`). Optionally create `queries_tickets(status='open')` and auto-assign by topic/campus.
4. Staff reply via `query_replies` (optionally mark `is_official_answer=1`).

## 7) API (REST) — Minimal Contract

*(Paths are illustrative; adjust to your routing/auth)*
Ok, mình sửa lại mục **7) API (REST) — Minimal Contract** để bổ sung rõ phần sinh viên có thể xem lại queries đã gửi 👇

---

## 7) API (REST) — Minimal Contract

*(Paths are illustrative; adjust to routing/auth)*

### Form Discovery & Definition

* **GET /form-targets**
    * Query: `campusId`, `scopeType`, `scopeId`, `now`
    * Returns published, open forms (respecting role via `campus_user_role`).

* **GET /forms/{code}/versions/{version}/definition**

    * Returns sections, questions, options.


### Submitting Responses

* **POST /responses**

    * Body:

      ```json
      {
        "formId": 1,
        "formVersionId": 2,
        "campusId": 1,
        "targetScopeType": "global",
        "targetScopeId": null,
        "anonymized": false,
        "answers": [ ... ]
      }
      ```
    * Validates time window, role, submission limits, enrollment.

* **POST /responses/{id}/attachments** (multipart)

    * Upload one or many files tied to a response or specific answer.

### Queries Workflow

* **POST /queries/tickets**
    * Body: `{ responseId, topicId?, customTopicText?, priority? }`
    * Creates a ticket linked to a student’s query response.

* **POST /queries/tickets/{id}/replies**
    * Body: `{ message, isOfficialAnswer?, attachmentId? }`
    * Adds a reply to an existing ticket (visible to student + staff).

* **GET /queries/my-tickets**
    * Returns tickets created by the authenticated student, with latest status + replies.
    * Enables “My Support Requests” page.

* **GET /queries/tickets/{id}**
    * Returns full detail of a specific ticket (responses, replies, attachments).
    * Auth check: must be ticket owner or staff with visibility.

### Error Model
* `403` → no campus/role permission
* `409` → submission limit exceeded
* `423` → form window closed
* `422` → validation error

## 8) Validation & Data Rules
* `questions.validation_json` examples:

    * short\_text: `{ "maxLength": 500 }`
    * number: `{ "min": 0, "max": 10, "decimals": 1 }`
    * matrix: `{ "rows": ["R1","R2"], "cols": [1,2,3,4,5] }`
* “Other”: when an option has `allows_free_text=1`, capture in `answer_options.free_text`.
* Anonymous feedback: store `submitted_by_user_id = NULL` and `anonymized=1`.
* Attachments: antivirus + size/type whitelist (enforced at upload service).

## 9) Performance & Indexing
* Hot paths: `form_targets` lookup by `(form_id, campus_id, scope_type, scope_id, time)`.
* Analytics: index `responses(form_id, campus_id, submitted_at)` and `answers(question_id)`.
* Consider **campus-based sharding** (later): keep `campus_id` on `responses` for partitioning.

## 10) Security & Privacy
* AuthZ derives from `campus_user_role` + form policy tables.
* Enforce server-side checks for:

    * Campus match, role match, enrollment match (for section/session).
    * Result visibility level and threshold.
* Log audit fields on sensitive mutations (who viewed detailed results, when).

## 11) Seed Examples (minimal)
```sql
INSERT INTO roles(code,name) VALUES
('student','Review'),('instructor','Instructor'),('advisor','Advisor'),('qa','QA'),('admin','Admin');

INSERT INTO campuses(code,name) VALUES ('HCM','Campus HCMC'),('HN','Campus Hanoi');

-- Example: student at HCM
INSERT INTO campus_user_role(campus_id,user_id,role_id,status)
SELECT c.id, 123 /*user*/, r.id, 'active'
FROM campuses c JOIN roles r ON c.code='HCM' AND r.code='student';
```

## 12) Test Cases (must pass)
* **Visibility**: Student with `campus_user_role(HCM, student)` sees only HCM forms; not HN forms.
* **Window**: Form is invisible before `start_at`, after `end_at`.
* **Limit**: Second submission blocked when `submission_limit_per_user=1`.
* **Anonymity**: Instructor sees only aggregate when `n >= threshold`; otherwise no access to raw responses.
* **Queries**: Student can see their own ticket + replies; advisor/admin on same campus can view/answer; cross-campus staff cannot unless they have the role for that campus.

## 13) Implementation Notes (Why these choices)
* **Versioned forms** preserve semantics of old data (safe evolution).
* **Hybrid answer storage** (`text/number/date`) makes analytics faster than pure JSON.
* **Campus scoping via** `campus_user_role` keeps governance clean for multi-site institutions.
* **Targets** separate “what” (form) from “where/when” (availability), powering surveys-after-session and campus-only deployments without duplicating forms.
* **Result visibility table** encodes compliance rules (aggregation threshold) in data, not code.

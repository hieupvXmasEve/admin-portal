Có, kiến thức này **hữu dụng** và nên merge vào bản trước. Phần bạn gửi làm rõ được “AI cần hiểu hệ thống như thế nào” thay vì chỉ nói về API key, chat, MCP và audit. Điểm mạnh nhất là nó tách đúng các lớp: **Business Glossary → Data Catalog → Metric Catalog → Semantic Layer → Query DSL → Tool Calling → Permission → Audit/Evaluation**. Đây là hướng đúng cho AI phân tích dữ liệu thật trong portal giáo dục, vì agent không nên “học” bằng cách train lại toàn bộ database; nó cần biết rule, catalog, tool schema, quyền và output format để hỏi backend lấy dữ liệu đúng.

Dưới đây là bản đã merge lại cho chặt hơn.

---

# Blueprint hoàn thiện AI phân tích dữ liệu thật trong portal

## 1. Nguyên tắc cốt lõi

Không để AI truy cập database tự do, không để AI tự viết SQL production, không để AI tự đoán field, relationship, status hoặc business rule.

Công thức đúng:

```text
User hỏi tự nhiên
→ Agent hiểu intent
→ Agent lập query plan
→ Backend validate quyền + schema
→ Backend lấy dữ liệu thật qua service/resolver
→ Agent phân tích dữ liệu đã được phép xem
→ Trả lời có evidence, source, confidence
```

Agent không cần nhớ toàn bộ dữ liệu. Agent cần biết:

```text
System instruction
Business rules
Business glossary
Data catalog
Entity catalog
Metric catalog
Tool schemas
Permission rules
Example questions
Output format
```

Tức là AI “học cách hỏi hệ thống”, không “học toàn bộ database”.

---

# 2. API key user tự config

Phần này nên tách khỏi quyền dữ liệu.

API key chỉ dùng để:

```text
- chọn AI provider
- gọi model
- tính quota/cost
- giới hạn usage
- bật/tắt provider theo user hoặc tenant
```

Không dùng API key để quyết định AI được đọc dữ liệu nào.

Quyền dữ liệu phải đi theo:

```text
- user đang đăng nhập
- role/permission hiện tại
- policy/gate hiện tại trong Laravel
- tenant/campus/department scope nếu hệ thống có
```

Cấu trúc nên có:

```text
ai_provider_settings
- id
- user_id / tenant_id
- provider
- encrypted_api_key
- default_model
- daily_limit
- monthly_limit
- enabled
- tested_at
- last_used_at
```

Luồng xử lý:

```text
User gửi câu hỏi
→ lấy provider config của user
→ build AI request
→ resolve quyền user
→ chỉ expose tools/data scopes user được phép dùng
→ tool vẫn check permission lần nữa trước khi query
→ log toàn bộ tool call
```

Điểm quan trọng: API key không được lưu plain text, không đưa xuống frontend sau khi lưu, không log vào prompt/message/audit.

---

# 3. Business Glossary

Đây là lớp định nghĩa nghiệp vụ. Nếu định nghĩa nghiệp vụ sai thì AI trả lời vẫn sai dù gọi đúng API.

Cần định nghĩa rõ các khái niệm trong portal giáo dục:

```text
Student
Program
Course
Class
Subject
Term / Semester
Enrollment
Academic result
Attendance
Defer
Withdrawal
Retake
Academic warning
Graduation status
Tuition fee
Invoice
Payment
Outstanding balance
Scholarship
Refund
Current term
Campus
Intake
Cohort
Advisor
Teacher
Department
```

Các rule bắt buộc phải rõ:

```text
"current term" xác định theo logic nào
"active student" khác "enrolled student" thế nào
"defer" trong hệ thống nghĩa là gì
"tuition revenue" tính theo invoice hay payment
"unpaid tuition" tính theo kỳ nào
"student risk" dựa trên yếu tố nào
```

Những khái niệm như defer, current term, active student, tuition revenue, unpaid tuition, student risk cần được định nghĩa bằng logic thật của hệ thống, không để AI tự suy diễn.

---

# 4. Data Catalog

Data Catalog là bản đồ dữ liệu mà AI được phép biết.

Mỗi entity nên khai báo:

```php
'student' => [
    'label' => 'Student',
    'model' => Student::class, // dùng model thật
    'searchable_fields' => [
        // field thật được phép search
    ],
    'readable_fields' => [
        // field thật được phép đọc
    ],
    'relationships' => [
        // relationship thật được phép đọc
    ],
    'permission' => 'viewStudent',
]
```

Data Catalog phải mô tả:

```text
Entity nào tồn tại
Entity dùng Laravel model nào
Field nào được phép đọc
Field nào được phép search
Relationship nào được phép truy cập
Role/permission nào được xem entity đó
```

Backend phải cung cấp catalog đã kiểm soát; agent không tự đoán bảng, field hoặc relationship.

---

# 5. Entity Catalog

Entity Catalog dùng cho các câu hỏi về một đối tượng cụ thể:

```text
Thông tin student ABC là gì?
Student này còn nợ học phí không?
Lịch sử defer của sinh viên này thế nào?
Class A hiện có bao nhiêu sinh viên?
Program X kỳ này có vấn đề gì?
```

Các entity nên bắt đầu từ những phần thật sự có trong project:

```text
student
term
program
class
course
subject
enrollment
attendance
assessment/result
invoice
payment
defer request
academic warning
```

Mỗi entity cần có:

```text
name
business meaning
search strategy
readable fields
allowed relationships
sections
section-level permissions
output format
```

Ví dụ với student, có thể chia section:

```text
basic
academic
attendance
finance
defer_history
warning
advisor_notes
```

Nhưng section nào tồn tại, field nào trả ra, relationship nào đọc được phải dựa trên model và permission thật của project.

---

# 6. Metric Catalog

Metric Catalog là phần quan trọng nhất nếu muốn user hỏi số liệu linh hoạt mà không phải tạo function riêng cho từng câu.

Ví dụ metric:

```text
active_students_count
defer_students_count
withdrawal_students_count
tuition_revenue
unpaid_tuition
attendance_risk_students
failed_subjects_count
academic_warning_students
```

Mỗi metric cần định nghĩa:

```text
name
label
description
calculation rule
data source
allowed filters
allowed group_by
permission
resolver/service
comparison logic
```

Ví dụ cấu trúc:

```php
'defer_students_count' => [
    'label' => 'Số sinh viên defer',
    'description' => 'Tổng số sinh viên defer hợp lệ theo kỳ',
    'allowed_filters' => [
        'term',
        'program',
        'campus',
        'intake',
    ],
    'allowed_group_by' => [
        'program',
        'campus',
        'intake',
    ],
    'permission' => 'viewAcademicReports',
    'resolver' => DeferStudentsCountMetric::class,
]
```

Agent không tự tính metric. Agent chỉ yêu cầu backend chạy metric đã định nghĩa.

---

# 7. Semantic Layer

Semantic Layer nối câu hỏi tự nhiên của user với entity, metric, filter và group_by trong hệ thống.

Nó giúp AI hiểu:

```text
"kỳ hiện tại" → current_term
"học phí đã thu" → tuition_revenue
"nợ học phí" → unpaid_tuition
"bảo lưu" → defer
"nguy cơ nghỉ học" → retention_risk
"sinh viên yếu" → academic_risk
```

Semantic Layer nên gồm:

```text
Business glossary
Entity catalog
Metric catalog
Relationship map
Synonym map
Filter map
Permission map
Query DSL
```

Ví dụ synonym:

```php
[
    'defer' => [
        'defer',
        'bảo lưu',
        'tạm hoãn học',
        'ngưng học tạm thời',
    ],

    'tuition_revenue' => [
        'doanh thu học phí',
        'học phí đã thu',
        'tiền học phí thu được',
    ],

    'current_term' => [
        'kỳ hiện tại',
        'term hiện tại',
        'semester hiện tại',
    ],
]
```

Synonym chỉ giúp hiểu intent. Logic thật vẫn phải nằm trong service/resolver.

---

# 8. Query DSL an toàn

Không cho AI sinh SQL trực tiếp.

Agent chỉ được tạo query object:

```json
{
    "metric": "tuition_revenue",
    "filters": {
        "term": "current"
    },
    "group_by": ["program"]
}
```

Backend kiểm tra:

```text
Metric có tồn tại không
Filter có hợp lệ không
Group by có được phép không
User có quyền xem không
Query có quá nặng không
Có lộ dữ liệu nhạy cảm không
```

Sau đó backend mới gọi service/query thật.

Nguyên tắc: AI tạo **query plan**, backend mới thực thi.

---

# 9. Tool Calling

Bộ tool nền nên có:

```text
search_entities
get_entity_profile
query_metrics
compare_metrics
analyze_context
```

Vai trò từng tool:

```text
search_entities
→ tìm student/class/program/term theo keyword

get_entity_profile
→ lấy hồ sơ chi tiết theo section được phép

query_metrics
→ lấy số liệu theo metric/filter/group_by

compare_metrics
→ so sánh kỳ hiện tại với kỳ trước hoặc khoảng thời gian khác

analyze_context
→ phân tích context đã lấy được, không query DB trực tiếp
```

Ví dụ:

```json
{
    "metric": "defer_students_count",
    "filters": {
        "term": "current"
    },
    "group_by": []
}
```

Hoặc:

```json
{
    "entity": "student",
    "id": 123,
    "sections": ["basic", "academic", "attendance", "finance"]
}
```

Backend phải tự loại bỏ section user không có quyền xem.

---

# 10. Tool Registry và Tool Dispatcher

Cần có lớp quản lý tool thay vì gọi service rải rác trong controller.

Gợi ý:

```text
app/Services/Ai/ToolRegistry.php
app/Services/Ai/ToolDispatcher.php

app/Services/Ai/Tools/
├── SearchEntitiesTool.php
├── GetEntityProfileTool.php
├── QueryMetricsTool.php
├── CompareMetricsTool.php
├── AnalyzeContextTool.php
```

Mỗi tool cần có:

```text
name
description
input_schema
output_schema
permission
handler
rate_limit
audit_policy
```

Tool Registry chịu trách nhiệm:

```text
Đăng ký tool
Cung cấp schema cho model
Map tool name sang PHP class
Validate arguments
Check permission
Execute tool
Log kết quả
Trả output chuẩn
```

Đây cũng là lớp sau này dễ map sang MCP Tools.

---

# 11. Agent Orchestration

Agent Orchestrator điều phối vòng lặp:

```text
User hỏi
→ build context
→ agent hiểu intent
→ agent chọn tool
→ dispatcher chạy tool
→ agent đọc kết quả
→ agent gọi thêm tool nếu cần
→ agent tổng hợp final answer
```

Ví dụ câu đơn giản:

```text
Kỳ hiện tại có bao nhiêu sinh viên defer?
```

Chỉ cần:

```text
query_metrics
```

Ví dụ câu phức tạp:

```text
Tình hình kỳ hiện tại có gì đáng lo?
```

Có thể cần:

```text
get_current_term
query_metrics(active_students_count)
query_metrics(defer_students_count)
query_metrics(unpaid_tuition)
query_metrics(attendance_risk_students)
compare_metrics với kỳ trước
analyze_context
```

Orchestrator phải kiểm soát:

```text
max tool calls
timeout
max records
max tokens
allowed tools
permission failure
ambiguous question
missing data
```

Tài liệu bạn gửi đã nêu đúng phần này: câu đơn giản có thể gọi một tool, câu phức tạp cần nhiều tool và cần giới hạn số lần gọi, thời gian xử lý, dữ liệu tối đa, quyền và điều kiện hỏi lại user.

---

# 12. Context Management và lịch sử chat

Để cải thiện chất lượng đầu ra dựa vào lịch sử chat, không nên lưu memory tự do. Nên lưu context có cấu trúc.

Cần lưu:

```text
Conversation state
Resolved entities
Current filters
Current term
Current student/class/program đang được nói tới
Tool call history
Short-term memory
Conversation summary
User output preferences
```

Ví dụ:

```text
User: Cho tôi thông tin student ABC.
AI: tìm được student ABC.

User: Bạn này có rủi ro nghỉ học không?
AI hiểu "Bạn này" = student ABC trong resolved_entities.
```

Context nên chia 3 lớp:

```text
Recent messages
→ vài tin nhắn gần nhất

Conversation summary
→ mục tiêu, entity đã resolve, quyết định, filter đang dùng

Structured memory
→ preference, thuật ngữ, format user thích, rule đã xác nhận
```

Không nên nhét toàn bộ lịch sử chat vào prompt mỗi lần. Nên build context có chọn lọc theo token budget.

Tài liệu bạn gửi cũng nhấn mạnh cần có conversation state, resolved entities, current filters, tool call history và short-term memory; đồng thời không dùng memory tự do không kiểm soát.

---

# 13. Permission & Security

AI phải tuân thủ quyền giống website, thậm chí chặt hơn.

Cần kiểm soát:

```text
Role-based permission
Field-level permission
Section-level permission
Metric-level permission
Relationship-level permission
Tenant/campus/department scope
PII masking
Audit log
Rate limit
Read-only mode
```

Không cho AI:

```text
Tự lấy toàn bộ database
Tự xem thông tin tài chính khi user không có quyền
Tự xuất danh sách PII lớn
Tự sinh SQL chạy production
Tự sửa dữ liệu khi chưa có approval
```

Giai đoạn đầu nên để:

```text
read-only
```

Tức là AI được:

```text
Đọc
Phân tích
Đề xuất
```

Nhưng không được:

```text
Cập nhật dữ liệu
Đổi trạng thái
Gửi email/action tự động
```

Đây là yêu cầu bắt buộc khi AI xử lý dữ liệu thật trong portal.

---

# 14. Analysis, Recommendation và Risk

AI có giá trị hơn dashboard ở phần diễn giải:

```text
Số liệu thô
→ nhận định
→ rủi ro
→ nguyên nhân khả dĩ
→ hành động đề xuất
```

Các kiểu phân tích nên hỗ trợ:

```text
Descriptive analysis
Comparative analysis
Trend analysis
Cohort analysis
Risk scoring
Anomaly detection
Root-cause hypothesis
Evidence-based recommendation
Missing data detection
Confidence level
```

Tuy nhiên risk nên làm **rule-based trước**, machine learning sau.

Ví dụ:

```text
Attendance thấp hơn ngưỡng hệ thống → attendance risk
Có nhiều môn fail → academic risk
Có unpaid tuition → finance risk
Từng defer nhiều lần → retention risk
Có từ 2 risk factor trở lên → medium/high risk
```

Backend/service tính risk factor. LLM chỉ diễn giải:

```text
Vì sao risk này đáng chú ý
Nên xử lý thế nào
Ưu tiên nhóm nào
Cần kiểm tra thêm dữ liệu gì
```

Không để LLM tự quyết định toàn bộ risk mà không có rule.

---

# 15. Structured Output

AI nên trả output theo schema cố định để Vue/Inertia render tốt hơn.

Ví dụ output tổng quát:

```json
{
    "answer": "string",
    "metrics": [],
    "key_findings": [],
    "risks": [],
    "recommendations": [],
    "missing_data": [],
    "sources": [],
    "confidence": "low|medium|high"
}
```

Ví dụ output cho student:

```json
{
    "student_summary": {},
    "academic_status": {},
    "attendance_status": {},
    "finance_status": {},
    "risk_level": "low|medium|high",
    "risk_factors": [],
    "recommended_actions": [],
    "hidden_sections": [],
    "confidence": "low|medium|high"
}
```

Frontend có thể render thành:

```text
Answer
Metric cards
Tables
Risk badges
Recommendations
Sources
Hidden sections
Confidence
```

Structured output giúp UI ổn định hơn và dễ test hơn.

---

# 16. Audit, Trace và Evaluation

Vì AI có thể gọi nhiều tool, phải log toàn bộ.

Nên có:

```text
ai_conversations
ai_messages
ai_tool_calls
ai_tool_results
ai_agent_traces
ai_provider_usages
ai_feedback
```

Mỗi tool call nên log:

```text
user_id
conversation_id
user_role
tool_name
tool_arguments
permission_result
records_count
hidden_sections
duration_ms
provider
model
tokens
estimated_cost
error
final_answer_id
```

Mục tiêu:

```text
Debug khi AI trả lời sai
Kiểm tra quyền dữ liệu
Đối chiếu với dashboard
Tối ưu performance
Audit khi có dữ liệu nhạy cảm
```

Ngoài audit, cần có Evaluation Set.

Ví dụ câu test:

```text
Kỳ hiện tại có bao nhiêu sinh viên defer?
Doanh thu học phí kỳ hiện tại là bao nhiêu?
Student ABC đang học chương trình nào?
Student ABC còn nợ học phí không?
Program nào có tỷ lệ defer cao nhất?
So với kỳ trước, defer tăng hay giảm?
Tình hình kỳ hiện tại có gì đáng lo?
```

Mỗi câu cần expected result. Test phải kiểm tra AI có gọi đúng tool, dùng đúng term, khớp dashboard, tôn trọng quyền, không bịa số và biết nói thiếu dữ liệu khi không đủ context.

---

# 17. Prompt / Instruction cho Agent

System instruction nên ngắn, rõ và bắt buộc tuân thủ.

Khung đề xuất:

```text
Bạn là AI Assistant cho hệ thống quản lý giáo dục.

Nhiệm vụ:
- Trả lời câu hỏi dựa trên dữ liệu thật từ hệ thống.
- Sử dụng tools được cung cấp để lấy dữ liệu.
- Không tự bịa số liệu.
- Không tự giả định field, status, term hoặc business rule.
- Không tự viết SQL.
- Không lấy dữ liệu ngoài phạm vi user được phép xem.
- Nếu dữ liệu mơ hồ, hỏi lại user hoặc nêu rõ giả định.
- Nếu user không có quyền, không hiển thị dữ liệu.
- Luôn nêu nguồn dữ liệu, filter, kỳ hoặc khoảng thời gian khi trả lời số liệu.
- Khi phân tích, phải dựa trên evidence từ tool result.
- Nếu thiếu dữ liệu để kết luận, nêu rõ missing_data.
```

Quy tắc cứng:

```text
Không có tool result thì không trả số liệu chính xác.
Không có quyền thì không hiển thị.
Không chắc thì nói không chắc.
Không tự mở rộng phạm vi dữ liệu.
```

Phần prompt trong tài liệu bạn gửi rất phù hợp để đưa vào system instruction thật của agent.

---

# 18. AI Core mở rộng cho MCP

Core nên thiết kế MCP-ready, nhưng chưa cần làm MCP server ngay ở MVP.

Tách 3 khái niệm:

```text
Tools
→ hành động động: search_entities, query_metrics, compare_metrics

Resources
→ context tĩnh/bán tĩnh: business glossary, metric catalog, data catalog, schema metadata

Prompts
→ template chuẩn: phân tích kỳ, phân tích student, so sánh metric, tạo báo cáo
```

Core nội bộ nên có:

```text
AiProviderManager
AiClientInterface
AiConversationService
ContextBuilder
AgentRunner
ToolRegistry
ToolDispatcher
SemanticLayer
PermissionResolver
QueryPlanValidator
AgentTraceLogger
```

Khi triển khai MCP sau này, mapping sẽ tự nhiên hơn:

```text
Internal Tool → MCP Tool
Business Glossary/Data Catalog/Metric Catalog → MCP Resource
Prompt Template → MCP Prompt
```

Điểm quan trọng: MCP là phase sau. MVP nên tập trung vào core nội bộ, permission, audit, metric resolver và tool calling trước.

---

# 19. Kiến trúc tổng thể đã merge

```text
Vue/Inertia AI Chat UI
        ↓
AiAgentController
        ↓
AiConversationService
        ↓
AgentRunner / Orchestrator
        ↓
ContextBuilder
        ↓
ToolRegistry + ToolDispatcher
        ↓
Semantic Layer
        ↓
BusinessGlossary / EntityCatalog / MetricCatalog
        ↓
PermissionResolver + QueryPlanValidator
        ↓
Laravel Services / Report Services / Metric Resolvers
        ↓
Database
```

Gợi ý thư mục:

```text
app/Services/Ai/
├── AgentRunner.php
├── AiProviderManager.php
├── AiClientInterface.php
├── AiConversationService.php
├── ContextBuilder.php
├── ToolRegistry.php
├── ToolDispatcher.php
├── AgentTraceLogger.php
├── PermissionResolver.php
├── QueryPlanValidator.php

app/Services/Ai/Tools/
├── SearchEntitiesTool.php
├── GetEntityProfileTool.php
├── QueryMetricsTool.php
├── CompareMetricsTool.php
├── AnalyzeContextTool.php

app/Services/Ai/Semantic/
├── BusinessGlossary.php
├── EntityCatalog.php
├── MetricCatalog.php
├── RelationshipCatalog.php
├── SynonymMap.php
├── FilterMap.php

app/Services/Ai/Metrics/
├── MetricResolver.php
├── ActiveStudentsCountMetric.php
├── DeferStudentsCountMetric.php
├── TuitionRevenueMetric.php
├── UnpaidTuitionMetric.php

app/Services/Ai/Analysis/
├── StudentSituationAnalyzer.php
├── TermHealthAnalyzer.php
├── RiskRuleService.php
├── RecommendationBuilder.php
```

Tên class chỉ là gợi ý. Khi áp dụng vào project, phải thay bằng model, service, policy, field, relationship thật.

Tài liệu bạn gửi cũng đề xuất kiến trúc Vue/Inertia → Controller → AgentRunner → ToolRegistry/Dispatcher → Semantic Layer → Catalog/PermissionResolver → Laravel Services → Database, rất khớp với hướng này.

---

# 20. Roadmap triển khai

## Phase 1 — MVP an toàn

```text
1. Chọn 3 câu hỏi thật user hay hỏi
2. Xác định service/query hiện tại đang trả số liệu đó
3. Tách logic report ra service nếu đang nằm trong controller
4. Tạo ai_provider_settings cho user tự config API key
5. Tạo ai_conversations và ai_messages
6. Tạo MetricCatalog
7. Tạo MetricResolver cho từng metric
8. Tạo query_metrics tool
9. Tạo ToolRegistry + ToolDispatcher
10. Cho Agent gọi query_metrics
11. Log tool call
12. Đối chiếu kết quả với dashboard
```

## Phase 2 — Entity và context

Trạng thái hiện tại: `AI-MOD-006-entity-catalog-search` đã triển khai
EntityCatalog v1 và `search_entities:v1` cho candidate lookup nội bộ, read-only,
permission-aware, campus-scoped, và PII-limited. `AI-MOD-017-live-provider-staff-copilot-agent`
đã bổ sung live provider agent để Staff Copilot có thể diễn giải prompt tự
nhiên hơn, nhưng vẫn buộc mọi data access đi qua ToolDispatcher và các tool
allowlisted. `AI-MOD-018-provider-agnostic-sse-chat-runtime` đã triển khai
queued run + downstream SSE trên runtime fallback snapshot để giữ toàn bộ
provider/tool execution phía backend. `AI-MOD-007-student-profile-sections` đã
triển khai `student-profile-sections:v1` và `get_entity_profile:v1` cho các
section hồ sơ sinh viên nội bộ, chỉ từ opaque `entity_ref`, có campus scope,
section-level permission, hidden-section reporting, audit và PII limit.

```text
1. Tạo EntityCatalog — implemented in AI-MOD-006
2. Tạo search_entities — implemented in AI-MOD-006
3. Tạo live-provider Staff Copilot agent — implemented in AI-MOD-017
4. Tạo provider-agnostic SSE chat runtime — implemented in AI-MOD-018
5. Tạo get_entity_profile — implemented in AI-MOD-007
6. Thêm section-level permission — implemented in AI-MOD-007
7. Thêm conversation state
8. Thêm resolved_entities
9. Thêm current_filters/current_term
10. Thêm conversation summary
```

## Phase 3 — Phân tích và chất lượng đầu ra

```text
1. Tạo compare_metrics
2. Tạo analyze_context
3. Tạo RiskRuleService
4. Tạo RecommendationBuilder
5. Chuẩn hóa structured output
6. Thêm answer sources
7. Thêm confidence/missing_data
8. Thêm feedback thumbs up/down
9. Tạo evaluation set
```

## Phase 4 — Governance và MCP-ready

```text
1. Admin bật/tắt provider/model/tool
2. Quota theo user/role/tenant
3. Prompt versioning
4. Tool versioning
5. Data access preview
6. MCP Resources cho catalog/glossary
7. MCP Tools mapping từ ToolRegistry
8. MCP Prompts mapping từ prompt templates
```

Tài liệu bạn gửi cũng khuyến nghị không làm sớm các phần như multi-agent phức tạp, MCP server, fine-tuning, vector database lớn, AI tự sinh SQL production, machine learning prediction hoặc action tự động thay đổi dữ liệu. Mình đồng ý: các phần đó nên để sau khi core, permission, audit và evaluation đã ổn.

---

# 21. Tính năng nên thêm

Nên thêm các tính năng này:

```text
AI Data Access Preview
→ user thấy AI được phép đọc nhóm dữ liệu nào trước khi hỏi

Answer Sources
→ mỗi câu trả lời hiển thị metric/tool/filter/kỳ đã dùng

Hidden Sections Notice
→ báo rõ phần nào bị ẩn vì thiếu quyền

Cost Control
→ giới hạn usage theo ngày/tháng/user/role/tenant

Admin AI Governance
→ admin bật/tắt provider, model, tool, MCP exposure

Prompt Versioning
→ lưu version system prompt/tool prompt để debug

Tool Versioning
→ khi logic metric thay đổi, biết câu trả lời cũ dùng version nào

Feedback & Evaluation Loop
→ user đánh giá câu trả lời, admin dùng để cải thiện prompt/tool

Read-only Mode
→ mặc định AI chỉ đọc, phân tích, đề xuất

Write Approval Mode
→ nếu sau này cho AI tạo/cập nhật dữ liệu thì bắt buộc có màn hình xác nhận

Data Freshness Badge
→ hiển thị dữ liệu được lấy lúc nào

Risk Explanation Card
→ giải thích risk dựa trên rule/factor nào

Audit Viewer
→ admin xem agent đã gọi tool nào, lấy dữ liệu gì, có bị ẩn section nào không
```

---

# Kết luận

Nên đi theo thứ tự:

```text
API key config
→ Permission
→ Business Glossary
→ Data/Entity/Metric Catalog
→ Semantic Layer
→ Query DSL
→ Tool Registry
→ Agent Orchestration
→ Context/History
→ Structured Output
→ Audit/Evaluation
→ MCP-ready
```

Ưu tiên triển khai thực tế: **MetricCatalog + query_metrics + permission + audit** trước, vì đây là đường ngắn nhất để AI trả lời được số liệu thật mà vẫn an toàn.

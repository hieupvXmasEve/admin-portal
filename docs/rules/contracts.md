---
paths: '**/*.php'
---

# Contract Rules (Shared/Contracts)

## 1. Golden Rule

**Whenever Module A needs data or behavior from Module B -> You MUST use a Contract.**

- No exceptions.
- Contracts act as the "legal boundary" of the Modular Monolith.

## 2. Mandatory Use Cases

1.  **Reading Data from Another Module**:
    - ❌ `AcademicRecord::where('student_id', $id)->avg('grade_points')` inside Finance module.
    - ✅ `$this->academicReader->getStudentGpa($studentId)`
2.  **Critical / Stable Business Logic**:
    - GPA, Graduation, Tuition, Wallet Balance.
    - Logic that will exist long-term should be behind a contract to decouple from Schema/Models.
3.  **Checking "State" Across Modules**:
    - "Has student passed?", "Has tuition been paid?"
4.  **Aggregated Data**:
    - Summaries requiring data from multiple tables/sources.
5.  **External Integrations (API/Mobile)**:
    - API Controllers must NOT query the DB directly for core data. They must go through a Contract.
6.  **Potential Future Service Split**:
    - If code might be split into a separate service later -> Use Contract NOW.

## 3. What Contracts CANNOT Do

- 🚫 Return Eloquent Models.
- 🚫 Accept Eloquent Models as parameters.
- 🚫 Return Collections of Models.
- **Why?** To prevents leakage of the internal database structure.

## 4. Implementation Rules

- **Location**: `app/Shared/Contracts/{Domain}/`.
- **Implementation**: Resides in the **Module Owner**.
- **Binding**: Bind in the Module's Service Provider.
- **Forbidden**: Other modules must NOT `new` or `import` the concrete implementation class.

## 5. DTOs (Data Transfer Objects)

- If a Contract returns multiple fields, use a DTO.
- ❌ Do not return generic `array`.
- ✅ Return `StudentAcademicSummaryDTO`.

## 6. Review Checklist

Before merging, ask:

- [ ] Does this module read another module's table?
- [ ] Does it import another module's Model?
- [ ] Is this "long-term" business logic?
- [ ] Will this be used by API/Mobile?
      **If YES to any -> MUST USE CONTRACT.**

**Prompt for AI Agent: Handle Complex Prerequisite Logic for Academic Units**

### Objective

As an AI Agent, your role is to support a university's academic system by interpreting, structuring, and storing complex prerequisite logic for academic units (courses). You must transform human-readable prerequisite expressions into normalized data using the following table structures:

---

### Database Tables to Use

#### 1. `unit_prerequisite_groups`

This table defines logical prerequisite groups per unit. Each group can represent a logic block (e.g., A **AND** (B **OR** C)).

```sql
CREATE TABLE unit_prerequisite_groups (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    unit_id BIGINT NOT NULL,
    logic_operator ENUM('AND', 'OR') DEFAULT 'AND',
    description TEXT,
    FOREIGN KEY (unit_id) REFERENCES units(id)
);
```

#### 2. `unit_prerequisite_conditions`

This table stores the actual prerequisite conditions, grouped under a group ID.

```sql
CREATE TABLE unit_prerequisite_conditions (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    group_id BIGINT NOT NULL,
    type ENUM('prerequisite', 'co_requisite', 'concurrent', 'anti_requisite', 'assumed_knowledge', 'credit_requirement', 'textual') NOT NULL,
    required_unit_id BIGINT,
    required_credits INT,
    free_text TEXT,
    FOREIGN KEY (group_id) REFERENCES unit_prerequisite_groups(id),
    FOREIGN KEY (required_unit_id) REFERENCES units(id)
);
```

---

### Input to the Agent

* Target `unit_id` (the unit for which the prerequisites apply)
* A human-readable prerequisite expression, e.g.:

  * "COS20015 and (COS10009 or COS10005)"
  * "Must have completed 100 credit points"
  * "Knowledge of Boolean algebra is assumed"

### Output Format

* Create 1 `unit_prerequisite_group` per logic group
* Within the group, create multiple `unit_prerequisite_conditions`
* Use appropriate `type` for each condition:

  * `prerequisite`: when referencing another unit
  * `credit_requirement`: if based on credit points
  * `textual`: for free-form human description
  * etc.

### Example

Given:

> Unit: COS30008 – Data Structures and Patterns
> Prerequisite: COS20015 and (COS10009 or COS10005)

The AI Agent should:

1. Create a `unit_prerequisite_group` with `logic_operator = AND`
2. Within that group:

   * Add `COS20015` as a `prerequisite`
   * Add a sub-group (logic\_operator = OR) with `COS10009` and `COS10005`
   * (Or flatten the logic if subgroups are not supported at DB level)

**How to Store in the Database**

### 🔹 Step 1: `unit_prerequisite_groups`

| id | unit_id  | logic_operator | description               |
|----|----------|----------------|---------------------------|
| 1  | UNIT_XYZ | AND            | Must meet both conditions |

> `UNIT_XYZ` refers to the ID of the unit being evaluated (e.g., `COS20007`).

---

### 🔹 Step 2: `unit_prerequisite_conditions`

| id | group_id | type         | required_unit_id | credit_points | free_text |
|----|----------|--------------|------------------|----------------|------------|
| 1  | 1        | prerequisite | COS10009         | NULL           | NULL       |
| 2  | 1        | credit_point | NULL             | 100            | NULL       |

### Optional Enhancements

* Automatically validate unit codes before inserting
* Generate human-readable description for admins
* Support nested logic through application-level interpretation (since DB is flat)


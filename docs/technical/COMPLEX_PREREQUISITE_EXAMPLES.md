# How to Fill Excel Templates for Complex Prerequisites

## Your Specific Example: `(P)175cps And ((E) BUS30010 OR BUS30024)`

### Breaking Down the Expression

The expression `(P)175cps And ((E) BUS30010 OR BUS30024)` means:
- **175 credit points completed** (credit requirement)
- **AND** 
- **Either BUS30010 OR BUS30024** (one of these units as anti-requisite)

### Excel Template Structure (New Format)

The Prerequisites sheet now has these columns:

| Column | Header | Purpose |
|--------|--------|---------|
| A | Unit Code* | The unit that has these prerequisites |
| B | Group Logic* | AND or OR for this group |
| C | Group Description | Optional name for the group |
| D | Condition Type* | Type of requirement |
| E | Required Unit Code | Unit code (for unit-based requirements) |
| F | Required Credits | Number (for credit requirements) |
| G | Free Text | Additional description |

### How to Fill Your Example

For a unit **CS301** with prerequisites `(P)175cps And ((E) BUS30010 OR BUS30024)`:

```
| Unit Code | Group Logic | Group Description        | Condition Type     | Required Unit Code | Required Credits | Free Text                |
|-----------|-------------|-------------------------|--------------------|-------------------|------------------|-------------------------|
| CS301     | AND         | Credit requirement      | credit_requirement |                   | 175              | 175 credit points       |
| CS301     | OR          | Business ethics choice  | anti_requisite     | BUS30010          |                  |                         |
| CS301     | OR          | Business ethics choice  | anti_requisite     | BUS30024          |                  |                         |
```

### Key Concepts

#### 1. **Groups are defined by Unit Code + Group Description**
- Same Unit Code + Same Group Description = Same logical group
- Same Unit Code + Different Group Description = Different groups (combined with AND)

#### 2. **Group Logic determines how conditions within a group are combined**
- `AND` = ALL conditions in the group must be met
- `OR` = ANY condition in the group can be met

#### 3. **Different groups are always combined with AND**
- Multiple groups for the same unit are joined with AND logic

## More Complex Examples

### Example 1: Multiple Prerequisites with OR
**Expression**: `(CS101 OR COMP101) AND MATH201`

```
| Unit Code | Group Logic | Group Description    | Condition Type | Required Unit Code | Required Credits | Free Text |
|-----------|-------------|---------------------|----------------|-------------------|------------------|-----------|
| CS202     | OR          | Programming base    | prerequisite   | CS101             |                  |           |
| CS202     | OR          | Programming base    | prerequisite   | COMP101           |                  |           |
| CS202     | AND         | Math requirement    | prerequisite   | MATH201           |                  |           |
```

### Example 2: Credit + Multiple Unit Options
**Expression**: `150cps AND (STAT101 OR MATH101 OR DATA101)`

```
| Unit Code | Group Logic | Group Description    | Condition Type     | Required Unit Code | Required Credits | Free Text         |
|-----------|-------------|---------------------|--------------------|-------------------|------------------|-------------------|
| CS401     | AND         | Credit requirement  | credit_requirement |                   | 150              | 150 credits       |
| CS401     | OR          | Statistics choice   | prerequisite       | STAT101           |                  |                   |
| CS401     | OR          | Statistics choice   | prerequisite       | MATH101           |                  |                   |
| CS401     | OR          | Statistics choice   | prerequisite       | DATA101           |                  |                   |
```

### Example 3: Complex Nested Expression
**Expression**: `((CS101 AND MATH101) OR (COMP101 AND STAT101)) AND 100cps`

```
| Unit Code | Group Logic | Group Description      | Condition Type     | Required Unit Code | Required Credits | Free Text     |
|-----------|-------------|------------------------|--------------------|-------------------|------------------|---------------|
| CS501     | AND         | Credit requirement     | credit_requirement |                   | 100              | 100 credits   |
| CS501     | AND         | CS track prerequisites | prerequisite       | CS101             |                  |               |
| CS501     | AND         | CS track prerequisites | prerequisite       | MATH101           |                  |               |
| CS501     | OR          | COMP track alternative | prerequisite       | COMP101           |                  |               |
| CS501     | OR          | COMP track alternative | prerequisite       | STAT101           |                  |               |
```

*Note: This creates 3 groups that are combined with AND logic*

## Available Condition Types

| Type | Purpose | Required Fields |
|------|---------|----------------|
| `prerequisite` | Must complete before | Required Unit Code |
| `credit_requirement` | Minimum credits needed | Required Credits |
| `co_requisite` | Must take at same time | Required Unit Code |
| `anti_requisite` | Cannot take if completed | Required Unit Code |
| `assumed_knowledge` | Expected background | Required Unit Code |
| `textual` | Free-form requirement | Free Text |

## Common Mistakes to Avoid

### ❌ Wrong: Mixing different units in Group Description
```
| Unit Code | Group Logic | Group Description | Condition Type | Required Unit Code |
|-----------|-------------|------------------|----------------|-------------------|
| CS301     | OR          | Prerequisites    | prerequisite   | BUS30010          |
| CS401     | OR          | Prerequisites    | prerequisite   | BUS30024          | ← Different unit!
```

### ✅ Correct: Same unit, different groups
```
| Unit Code | Group Logic | Group Description     | Condition Type | Required Unit Code |
|-----------|-------------|-----------------------|----------------|-------------------|
| CS301     | OR          | Business ethics       | prerequisite   | BUS30010          |
| CS301     | OR          | Business ethics       | prerequisite   | BUS30024          |
```

### ❌ Wrong: Empty required fields
```
| Unit Code | Group Logic | Group Description | Condition Type     | Required Unit Code | Required Credits |
|-----------|-------------|------------------|--------------------|--------------------|------------------|
| CS301     | AND         | Credit req       | credit_requirement |                    |                  | ← Missing credits!
```

### ✅ Correct: All required fields filled
```
| Unit Code | Group Logic | Group Description | Condition Type     | Required Unit Code | Required Credits |
|-----------|-------------|------------------|--------------------|--------------------|------------------|
| CS301     | AND         | Credit req       | credit_requirement |                    | 175              |
```

## Testing Your Template

1. **Download the "detailed" or "complete" template** (these have the new format)
2. **Fill in your data** following the examples above
3. **Import with preview** to see how the system interprets your groups
4. **Check the export** to see the final prerequisite expressions

## Quick Reference for Your Expression

Your expression: `(P)175cps And ((E) BUS30010 OR BUS30024)`

**Translation to Excel rows:**
1. Row for credit requirement (175cps) with AND logic
2. Row for BUS30010 with OR logic (different group)  
3. Row for BUS30024 with OR logic (same group as BUS30010)

The system will automatically combine the credit group (AND) with the business ethics group (OR) using AND logic, creating the exact expression you want. 

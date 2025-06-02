# Step-by-Step Excel Template Guide

## Problem: Old vs New Template Format

**The Issue**: You saw an old template format that looked like this:
```
Unit Code* | Required Unit Code* | Type* | Group Logic | Description
CS201      | CS101              | prerequisite | AND | Basic programming knowledge required
CS201      | MATH101            | prerequisite | OR  | Mathematical foundation
```

**The Solution**: The new template format has been updated and looks like this:
```
Unit Code* | Group Logic* | Group Description | Condition Type* | Required Unit Code | Required Credits | Free Text
CS201      | AND         | Basic programming | prerequisite    | CS101             |                  |
CS301      | AND         | Credit requirement| credit_requirement|                 | 175              | 175 credit points
CS301      | OR          | Business ethics   | prerequisite    | BUS30010          |                  |
CS301      | OR          | Business ethics   | prerequisite    | BUS30024          |                  |
```

## Step 1: Download the Correct Template

1. **Go to Units page** in your application
2. **Click "Import" button**
3. **Select template format**:
   - For units + prerequisites: Choose **"Detailed Format"**
   - For units + prerequisites + equivalents: Choose **"Complete Format"** 
   - For units + syllabus data: Choose **"Combined Format"**

❗ **Important**: All three formats now have the **new prerequisite structure**. Avoid any cached/old templates.

## Step 2: Understanding Your Expression

Your expression: `(P)175cps And ((E) BUS30010 OR BUS30024)`

This breaks down into:
- **Group 1 (AND logic)**: 175 credit points requirement
- **Group 2 (OR logic)**: Either BUS30010 OR BUS30024 as anti-requisites

Both groups are combined with AND logic.

## Step 3: Fill the Prerequisites Sheet

Open the **Prerequisites** sheet in the downloaded template. You'll see these columns:

| A | B | C | D | E | F | G |
|---|---|---|---|---|---|---|
| Unit Code* | Group Logic* | Group Description | Condition Type* | Required Unit Code | Required Credits | Free Text |

### For your example (assuming the unit is CS301):

**Row 2**: Credit requirement
```
CS301 | AND | Credit requirement | credit_requirement | [leave empty] | 175 | 175 credit points completed
```

**Row 3**: First business ethics option  
```
CS301 | OR | Business ethics requirement | anti_requisite | BUS30010 | [leave empty] | [leave empty]
```

**Row 4**: Second business ethics option
```
CS301 | OR | Business ethics requirement | anti_requisite | BUS30024 | [leave empty] | [leave empty]
```

### Visual Example:

```
| A     | B   | C                          | D                  | E        | F   | G                        |
|-------|-----|----------------------------|--------------------|----------|-----|--------------------------|
| CS301 | AND | Credit requirement         | credit_requirement |          | 175 | 175 credit points        |
| CS301 | OR  | Business ethics requirement| anti_requisite     | BUS30010 |     |                          |
| CS301 | OR  | Business ethics requirement| anti_requisite     | BUS30024 |     |                          |
```

## Step 4: Key Rules to Remember

### Rule 1: Group Formation
- **Same Unit Code** + **Same Group Description** = Same group
- **Same Unit Code** + **Different Group Description** = Different groups

### Rule 2: Logic Within Groups
- **AND**: ALL conditions in the group must be met
- **OR**: ANY condition in the group can be met

### Rule 3: Logic Between Groups
- Different groups are **always** combined with AND logic

### Rule 4: Field Requirements
- **credit_requirement**: Fill "Required Credits", leave "Required Unit Code" empty
- **prerequisite/anti_requisite**: Fill "Required Unit Code", leave "Required Credits" empty

## Step 5: More Examples

### Simple Prerequisites
**Expression**: `CS101 AND MATH101`
```
| Unit Code | Group Logic | Group Description | Condition Type | Required Unit Code | Required Credits | Free Text |
|-----------|-------------|------------------|----------------|-------------------|------------------|-----------|
| CS201     | AND         | Basic requirements| prerequisite   | CS101             |                  |           |
| CS201     | AND         | Basic requirements| prerequisite   | MATH101           |                  |           |
```

### OR Alternatives  
**Expression**: `CS101 OR COMP101`
```
| Unit Code | Group Logic | Group Description | Condition Type | Required Unit Code | Required Credits | Free Text |
|-----------|-------------|------------------|----------------|-------------------|------------------|-----------|
| CS201     | OR          | Programming base  | prerequisite   | CS101             |                  |           |
| CS201     | OR          | Programming base  | prerequisite   | COMP101           |                  |           |
```

### Mixed Requirements
**Expression**: `(CS101 OR COMP101) AND MATH101 AND 100cps`
```
| Unit Code | Group Logic | Group Description | Condition Type     | Required Unit Code | Required Credits | Free Text |
|-----------|-------------|------------------|--------------------|--------------------|------------------|-----------|
| CS301     | AND         | Credit requirement| credit_requirement |                    | 100              | 100 credits |
| CS301     | OR          | Programming base  | prerequisite       | CS101              |                  |           |
| CS301     | OR          | Programming base  | prerequisite       | COMP101            |                  |           |
| CS301     | AND         | Math requirement  | prerequisite       | MATH101            |                  |           |
```

## Step 6: Testing Your Template

1. **Save your Excel file** with your data
2. **Import using the preview feature**:
   - Go to Units → Import
   - Upload your file
   - Click "Preview" to see how the system interprets your data
3. **Check the results** before final import
4. **Export after import** to verify the prerequisite expressions are correct

## Troubleshooting Common Issues

### Issue 1: "Invalid Condition Type"
- Make sure you're using exactly: `prerequisite`, `credit_requirement`, `co_requisite`, `anti_requisite`, `assumed_knowledge`, or `textual`
- Use the dropdown if available

### Issue 2: "Missing Required Fields"
- For `credit_requirement`: Must fill "Required Credits" column
- For unit-based types: Must fill "Required Unit Code" column

### Issue 3: "Unexpected Grouping"
- Check that "Group Description" is identical for conditions that should be in the same group
- Verify "Unit Code" is the same for all rows related to one unit

### Issue 4: "Wrong Logic Result"
- Remember: Different "Group Description" values create separate groups
- All separate groups are combined with AND logic
- Use OR logic only within the same group

## Quick Checklist

Before importing, verify:
- ✅ Unit codes are correct in column A
- ✅ Group Logic (AND/OR) is appropriate for each row
- ✅ Group Description groups related conditions correctly  
- ✅ Condition Type matches your requirement type
- ✅ Required fields are filled based on condition type
- ✅ No typos in unit codes or condition types
- ✅ Each prerequisite expression is properly broken down into rows

Your final template should now correctly represent `(P)175cps And ((E) BUS30010 OR BUS30024)` and import successfully! 

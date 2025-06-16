# Complex Prerequisites Import/Export Guide

## Overview

The enhanced unit import/export system now supports complex prerequisite structures including credit requirements, multiple prerequisite groups, and mixed logical operators. This guide explains how to represent complex prerequisite expressions like `(P)175cps And ((E) BUS30010 OR BUS30024)` in Excel format.

## Prerequisite Structure

### Data Model
- **Units**: Individual academic units with code, name, and credit points
- **Prerequisite Groups**: Groups of conditions with a logical operator (AND/OR)
- **Prerequisite Conditions**: Individual requirements within a group

### Condition Types
- `prerequisite`: Must be completed before enrollment
- `credit_requirement`: Minimum credit points required
- `co_requisite`: Must be taken at the same time
- `anti_requisite`: Cannot be taken if this unit is completed
- `assumed_knowledge`: Expected background knowledge
- `textual`: Free-form text requirement

## Excel Template Structure

### Prerequisites Sheet Columns
1. **Unit Code** (*required*): The unit that has prerequisites
2. **Group Logic** (*required*): AND or OR for this group
3. **Group Description**: Optional description for the group
4. **Condition Type** (*required*): Type of prerequisite condition
5. **Required Unit Code**: Unit code for unit-based prerequisites
6. **Required Credits**: Number for credit requirements
7. **Free Text**: Additional description or complex text

## Complex Examples

### Example 1: Simple Prerequisite
**Expression**: `CS201 requires CS101`

```
Unit Code | Group Logic | Group Description | Condition Type | Required Unit Code | Required Credits | Free Text
CS201     | AND         | Basic programming | prerequisite   | CS101              |                  |
```

### Example 2: Your Complex Expression
**Expression**: `(P)175cps And ((E) BUS30010 OR BUS30024)`

This translates to: "175 credit points completed AND CS201 prerequisite AND (BUS30010 OR BUS30024)"

```
Unit Code | Group Logic | Group Description        | Condition Type     | Required Unit Code | Required Credits | Free Text
CS301     | AND         | Credit and unit req      | credit_requirement |                    | 175              | 175 credit points
CS301     | AND         | Credit and unit req      | prerequisite       | CS201              |                  |
CS301     | OR          | Business ethics req      | prerequisite       | BUS30010           |                  |
CS301     | OR          | Business ethics req      | prerequisite       | BUS30024           |                  |
```

### Example 3: Multiple Complex Groups
**Expression**: `((P)CS101 OR (P)COMP101) AND (150cps) AND ((E)MATH201 OR (E)STAT201)`

```
Unit Code | Group Logic | Group Description | Condition Type     | Required Unit Code | Required Credits | Free Text
CS401     | OR          | Programming base  | prerequisite       | CS101              |                  |
CS401     | OR          | Programming base  | prerequisite       | COMP101            |                  |
CS401     | AND         | Credit requirement| credit_requirement |                    | 150              |
CS401     | OR          | Math requirement  | anti_requisite     | MATH201            |                  |
CS401     | OR          | Math requirement  | anti_requisite     | STAT201            |                  |
```

## How Grouping Works

### Same Group
Rows with the **same Unit Code** and **same Group Description** are treated as conditions within the same logical group.

### Different Groups
Rows with **different Group Descriptions** create separate prerequisite groups that are combined with AND logic.

### Logic Operators
- **AND**: ALL conditions in the group must be satisfied
- **OR**: ANY condition in the group can be satisfied

## Import Process

1. **Upload Excel file** with Units and Prerequisites sheets
2. **Configure import settings** (duplicate handling, relationship creation)
3. **Preview data** to verify structure
4. **Process import** with detailed error reporting

## Export Features

### Enhanced Export Columns
- **Prerequisite Expression**: Human-readable format like `(P)175cps AND ((E)BUS30010 OR BUS30024)`
- **Detailed Prerequisites Sheet**: Complete breakdown of all groups and conditions
- **Group Structure**: Shows how conditions are organized into logical groups

### Export Formats
- **Simple**: Basic unit information only
- **Detailed**: Units with prerequisites
- **Complete**: Units with prerequisites and equivalents
- **Combined**: Units with syllabus data and assessments

## Validation Features

### Template Dropdowns
- **Unit Code**: Dropdown with existing unit codes
- **Group Logic**: AND/OR selection
- **Condition Type**: All valid prerequisite types

### Import Validation
- **Unit existence**: Validates that referenced units exist
- **Condition requirements**: Ensures required fields are provided
- **Duplicate detection**: Prevents duplicate relationships
- **Complex expression parsing**: Handles nested logical structures

## Error Handling

### Common Errors
1. **Missing unit codes**: Referenced units don't exist
2. **Invalid condition types**: Unknown prerequisite types
3. **Missing required fields**: Empty required columns
4. **Circular dependencies**: Unit requiring itself (directly or indirectly)

### Warning Messages
1. **Duplicate relationships**: Relationship already exists
2. **Missing optional data**: Non-critical missing information

## Best Practices

### Group Organization
1. Use descriptive group descriptions
2. Keep related conditions in the same group
3. Use separate groups for different logical requirements

### Credit Requirements
1. Always specify the number in Required Credits column
2. Use descriptive Free Text for clarity
3. Combine with other prerequisites as needed

### Complex Expressions
1. Break down complex expressions into simple groups
2. Use consistent naming for group descriptions
3. Test with preview before final import

## Testing Your Prerequisites

1. **Download template** with your desired format
2. **Add sample data** following the examples
3. **Import with preview** to verify structure
4. **Check exported data** to confirm correct interpretation
5. **Validate logic** using the prerequisite expression column

## Support for Legacy Formats

The system maintains backward compatibility with simpler prerequisite formats while supporting the enhanced complex structure. Existing imports will continue to work, and you can migrate to the new format gradually. 

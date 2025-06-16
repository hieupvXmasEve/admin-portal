# Curriculum Specialization System Examples

## Overview
This document demonstrates how the enhanced curriculum system handles complex academic scenarios where:
1. **A unit can be CORE in one specialization and ELECTIVE in another**
2. **Prerequisites are enforced regardless of the unit's role in different specializations**
3. **Common units are shared across all specializations within a program**
4. **Cross-program electives are supported**

## Key Examples from Our Implementation

### 1. System Analysis and Design (IT302) - Core vs Elective

**Unit**: `IT302 - System Analysis and Design`

**Different Roles Across Specializations:**
- **Software Development (IT-SD)**: **CORE** requirement (Year 3, Semester 1)
- **Cybersecurity (IT-CS)**: **ELECTIVE** (Year 4, Semester 2)
- **Network Engineering (IT-NE)**: **ELECTIVE** (Year 4, Semester 1)

**Prerequisites (Applied Regardless of Role):**
- **Prerequisite**: CS201 (Data Structures and Algorithms)
- **Co-requisite**: IT301 (Database Management Systems)

```php
// Query to demonstrate this
$systemAnalysis = Unit::where('code', 'IT302')->first();

// Get all curriculum units for this unit across specializations
$curriculumUnits = CurriculumUnit::where('unit_id', $systemAnalysis->id)
    ->with(['curriculumVersion.specialization', 'curriculumVersion.program'])
    ->get();

foreach ($curriculumUnits as $cu) {
    $spec = $cu->curriculumVersion->specialization;
    echo "{$spec->name}: {$cu->type} ({$cu->getAcademicPeriod()})\n";
    
    // Check prerequisites regardless of role
    $prereqCheck = $cu->checkPrerequisites([/* completed unit IDs */]);
    if (!$prereqCheck['can_enroll']) {
        echo "Missing prerequisites: " . count($prereqCheck['missing_prerequisites']) . "\n";
    }
}
```

### 2. Advanced Statistics (MATH301) - Multiple Core Roles

**Unit**: `MATH301 - Advanced Statistics`

**Different Roles:**
- **Data Science (IT-DS)**: **CORE** requirement (Year 2, Semester 2)
- **Cybersecurity (IT-CS)**: **CORE** requirement (Year 3, Semester 1) - for risk analysis
- **Software Development (IT-SD)**: **ELECTIVE** (available but not required)
- **Business Programs**: **ELECTIVE** for analytics-focused students

### 3. Network Security (CS301) - Specialization Crossover

**Unit**: `CS301 - Network Security`

**Roles:**
- **Cybersecurity (IT-CS)**: **MAJOR** requirement (Year 3, Semester 1)
- **Network Engineering (IT-NE)**: **CORE** requirement (Year 3, Semester 2)
- **Software Development (IT-SD)**: **ELECTIVE** (Year 4, Semester 1)

## Query Examples

### 1. Get Complete Curriculum for a Specialization

```php
$specialization = Specialization::where('code', 'IT-SD')->first();

// Get all units for Software Development specialization
$allUnits = $specialization->getAllUnits();

// Group by source (program-level common vs specialization-specific)
$commonUnits = $allUnits->where('source', 'program');
$specializationUnits = $allUnits->where('source', 'specialization');

echo "=== Software Development Curriculum ===\n";
echo "Common Units (All IT Students):\n";
foreach ($commonUnits as $unit) {
    echo "- {$unit['unit']->code}: {$unit['unit']->name} ({$unit['type']})\n";
}

echo "\nSpecialization-Specific Units:\n";
foreach ($specializationUnits as $unit) {
    echo "- {$unit['unit']->code}: {$unit['unit']->name} ({$unit['type']})\n";
}
```

### 2. Check if a Unit is Available in Different Specializations

```php
$unit = Unit::where('code', 'IT302')->first(); // System Analysis

$itSpecializations = Specialization::where('program_id', 
    Program::where('name', 'IT')->first()->id
)->get();

foreach ($itSpecializations as $spec) {
    $role = $spec->getUnitRole($unit);
    if ($role) {
        echo "{$spec->name}: {$role['type']} ";
        echo "({$role['is_required'] ? 'Required' : 'Optional'})\n";
    } else {
        echo "{$spec->name}: Not available\n";
    }
}
```

### 3. Prerequisite Checking Across Specializations

```php
$student = [
    'specialization' => 'IT-SD',
    'completed_units' => [1, 2, 3], // Unit IDs of completed units
    'current_year' => 3,
    'current_semester' => 1
];

$specialization = Specialization::where('code', $student['specialization'])->first();
$curriculum = $specialization->curriculumVersions()->first();

// Get available units for current semester
$availableUnits = $curriculum->curriculumUnits()
    ->where('year_level', $student['current_year'])
    ->where('semester_number', $student['current_semester'])
    ->with('unit')
    ->get();

foreach ($availableUnits as $curriculumUnit) {
    $prereqCheck = $curriculumUnit->checkPrerequisites($student['completed_units']);
    
    echo "{$curriculumUnit->unit->code} - {$curriculumUnit->unit->name}\n";
    echo "Role: {$curriculumUnit->type}\n";
    echo "Can Enroll: " . ($prereqCheck['can_enroll'] ? 'Yes' : 'No') . "\n";
    
    if (!$prereqCheck['can_enroll']) {
        echo "Missing Prerequisites:\n";
        foreach ($prereqCheck['missing_prerequisites'] as $missing) {
            echo "  - {$missing['unit']->code} ({$missing['type']})\n";
        }
    }
    echo "---\n";
}
```

### 4. Cross-Specialization Enrollment

```php
// Student in Data Science wants to take a Cybersecurity elective
$studentSpecialization = Specialization::where('code', 'IT-DS')->first();
$electiveUnit = Unit::where('code', 'CS302')->first(); // Ethical Hacking

// Check if unit is available as elective in student's specialization
$curriculum = $studentSpecialization->curriculumVersions()->first();
$electiveOffering = $curriculum->curriculumUnits()
    ->where('unit_id', $electiveUnit->id)
    ->where('type', 'elective')
    ->first();

if ($electiveOffering) {
    echo "Unit available as elective\n";
    echo "Minimum Grade Required: {$electiveOffering->minimum_grade}\n";
    
    $prereqCheck = $electiveOffering->checkPrerequisites([/* student's completed units */]);
    echo "Prerequisites Met: " . ($prereqCheck['can_enroll'] ? 'Yes' : 'No') . "\n";
} else {
    echo "Unit not available in this specialization\n";
}
```

## Academic Planning Scenarios

### Scenario 1: Student Transfer Between Specializations

```php
// Student transfers from Software Development to Data Science
$fromSpec = Specialization::where('code', 'IT-SD')->first();
$toSpec = Specialization::where('code', 'IT-DS')->first();

$completedUnits = [/* array of completed unit IDs */];

// Check credit transfer
$fromUnits = $fromSpec->getAllUnits();
$toUnits = $toSpec->getAllUnits();

$transferableCredits = [];
$additionalRequirements = [];

foreach ($toUnits as $requiredUnit) {
    $found = $fromUnits->first(function ($unit) use ($requiredUnit) {
        return $unit['unit']->id === $requiredUnit['unit']->id;
    });
    
    if ($found) {
        $transferableCredits[] = $requiredUnit['unit'];
    } else if ($requiredUnit['is_required']) {
        $additionalRequirements[] = $requiredUnit['unit'];
    }
}
```

### Scenario 2: Academic Progress Tracking

```php
$specialization = Specialization::where('code', 'IT-CS')->first();
$studentCompletedUnits = [1, 2, 3, 5, 8]; // Unit IDs

$allRequiredUnits = $specialization->getAllUnits()
    ->where('is_required', true);

$progress = [
    'total_required' => $allRequiredUnits->count(),
    'completed' => 0,
    'remaining' => [],
    'available_next' => []
];

foreach ($allRequiredUnits as $unitData) {
    if (in_array($unitData['unit']->id, $studentCompletedUnits)) {
        $progress['completed']++;
    } else {
        // Check if prerequisites are met
        $curriculumUnit = CurriculumUnit::where('unit_id', $unitData['unit']->id)
            ->whereHas('curriculumVersion', function ($query) use ($specialization) {
                $query->where('specialization_id', $specialization->id);
            })
            ->first();
            
        if ($curriculumUnit) {
            $prereqCheck = $curriculumUnit->checkPrerequisites($studentCompletedUnits);
            if ($prereqCheck['can_enroll']) {
                $progress['available_next'][] = $unitData['unit'];
            } else {
                $progress['remaining'][] = $unitData['unit'];
            }
        }
    }
}

echo "Academic Progress: {$progress['completed']}/{$progress['total_required']} units completed\n";
echo "Available for next enrollment: " . count($progress['available_next']) . " units\n";
```

## Benefits of This Implementation

1. **Flexibility**: Same unit can serve different purposes across specializations
2. **Consistency**: Prerequisites are enforced uniformly regardless of unit role
3. **Academic Integrity**: Students must meet requirements regardless of how they encounter a unit
4. **Transfer Support**: Easy credit transfer calculations between specializations
5. **Progress Tracking**: Clear academic progression with prerequisite validation
6. **Elective Management**: Proper handling of cross-specialization and cross-program electives

This system accurately models real-world academic complexity while maintaining data integrity and providing clear pathways for student progression. 

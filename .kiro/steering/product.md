---
inclusion: manual
---

# Swinburne University Academic Management System

## Product Context
This is a comprehensive educational management platform for multi-campus university operations. The system manages the complete academic lifecycle from curriculum design to student graduation.

## Core Domain Concepts

### Academic Hierarchy
- **Campus**: Physical university locations with buildings and rooms
- **Program**: Degree programs (e.g., Bachelor of Computer Science)
- **Specialization**: Program tracks (e.g., Software Engineering, AI)
- **Curriculum Version**: Versioned program requirements by year
- **Unit**: Individual courses/subjects with prerequisites and credit points
- **Course Offering**: Scheduled instances of units in specific semesters

### User Roles & Permissions
- **System Admin**: Full system access, campus management
- **Academic Admin**: Program and curriculum management
- **Faculty**: Teaching, grading, student monitoring
- **Student**: Course registration, academic progress tracking
- **Staff**: Administrative support functions

### Key Business Rules
- Students must meet prerequisites before enrolling in units
- Course offerings require available rooms and qualified instructors
- Academic holds prevent registration until resolved
- GPA calculations follow university policies
- Graduation requires completing all curriculum requirements

## Feature Priorities

### Phase 1: Foundation (Completed)
- Semester and curriculum management
- Basic academic structure setup

### Phase 2: Infrastructure (In Progress)
- Campus and classroom management
- User management with role-based permissions
- Building and room scheduling

### Phase 3: Operations (Planned)
- Course offering management
- Student enrollment system
- Academic record tracking

### Phase 4: Analytics (Future)
- Academic performance reporting
- Resource utilization analytics
- Graduation tracking

## User Experience Principles
- **Role-based Navigation**: Show only relevant features per user role
- **Academic Calendar Awareness**: All operations respect semester boundaries
- **Prerequisite Validation**: Prevent invalid enrollments with clear messaging
- **Multi-campus Support**: Campus context maintained throughout user journey
- **Audit Trail**: Track all academic record changes for compliance

## Data Integrity Requirements
- Academic records are immutable once finalized
- All grade changes require approval workflow
- Student enrollment changes must validate prerequisites
- Course capacity limits must be enforced
- Academic holds block registration until resolved

## Integration Points
- External authentication systems (Google OAuth)
- Student information systems
- Academic calendar systems
- Room booking systems
- Grade reporting systems

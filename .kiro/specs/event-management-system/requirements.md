# Requirements Document

## Introduction

The Event Management System builds upon the existing club management infrastructure to provide comprehensive event creation, management, and participation functionality. The system enables administrators to create both campus-wide school events and club-specific events, while students can discover, register for, and participate in events through the Student Portal API to earn gold rewards through the existing wallet system.

**Current Scope:** Event management for administrators using Laravel + Vue 3 + InertiaJS, with student participation through API endpoints and automatic notifications.
**Existing Infrastructure:** Club management system is already implemented and operational.

## Requirements

### Requirement 1

**User Story:** As an administrator, I want to create and manage campus-wide events, so that I can organize institutional activities and track student participation.

#### Acceptance Criteria

1. WHEN an administrator accesses the event creation form THEN the system SHALL display fields for title, description, start_time, end_time, location, gold_reward_amount, and max_participants
2. WHEN an administrator creates an event THEN the system SHALL set the organizer_type to "school" and organizer_id to the campus_id
3. WHEN an administrator saves an event THEN the system SHALL generate a unique QR code for the event and validate all required fields
4. WHEN an administrator publishes an event THEN the system SHALL update the status to "published", set the published_at timestamp, and create notifications for all campus students
5. IF an event is in draft status THEN the system SHALL only allow administrators to view and edit it
6. WHEN an administrator cancels an event THEN the system SHALL update the status to "cancelled", notify all registered participants, and process any necessary gold refunds
7. WHEN creating an event THEN the system SHALL validate that start_time is before end_time and both are in the future
8. WHEN an event is published THEN the system SHALL make it visible to students through the API endpoints

### Requirement 2

**User Story:** As a student using the Student Portal API, I want to discover and register for campus events, so that I can participate in institutional activities and earn gold rewards.

#### Acceptance Criteria

1. WHEN a student calls the events API endpoint THEN the system SHALL return all published events for their campus in JSON format with pagination
2. WHEN a student requests event details via API THEN the system SHALL return comprehensive event information including title, description, organizer details, time, location, gold_reward_amount, and registration status
3. WHEN a student registers for an event via API THEN the system SHALL create an event_participant record with status "registered" and send a confirmation notification
4. IF a student is already registered for an event THEN the API SHALL return their current registration status and prevent duplicate registrations
5. WHEN a student requests their registered events via API THEN the system SHALL return all events they have registered for with current participation status
6. IF an event reaches max_participants THEN the system SHALL prevent new registrations and return appropriate error messages
7. WHEN making API calls THEN the system SHALL require valid student authentication tokens and validate campus access
8. WHEN a student unregisters from an event THEN the system SHALL update their participation status to "cancelled" and send a notification

### Requirement 3

**User Story:** As an administrator or staff member, I want to scan student QR codes to check them into events, so that I can verify attendance and enable students to earn gold rewards.

#### Acceptance Criteria

1. WHEN an authorized user accesses the event check-in interface THEN the system SHALL provide QR code scanning functionality with camera access
2. WHEN scanning a student's QR code THEN the system SHALL verify the student is registered for the selected event and display their information
3. IF a student is not registered for an event THEN the system SHALL allow the staff member to register and check in the student simultaneously with appropriate warnings
4. WHEN successfully checking in a student THEN the system SHALL update the participant status to "checked_in", record the checkin_time, and send a confirmation notification
5. WHEN a student is checked in THEN the system SHALL capture device information, staff member ID, and location data for audit purposes
6. IF attempting to check in a student multiple times THEN the system SHALL prevent duplicate check-ins and display the existing check-in status with timestamp
7. WHEN an event end time passes and a student has been checked in THEN the system SHALL automatically update their status to "completed"
8. IF a user lacks proper permissions THEN the system SHALL deny access to the check-in scanning interface and log the attempt

### Requirement 4

**User Story:** As a student, I want to automatically receive gold rewards for attending events, so that I can build up my wallet balance through participation.

#### Acceptance Criteria

1. WHEN a student's event participation status changes to "completed" THEN the system SHALL award the event's gold_reward_amount to their wallet using the existing WalletService
2. WHEN gold is awarded THEN the system SHALL create a wallet transaction record with type "earn", source_type "event", and source_id referencing the event
3. WHEN gold is awarded THEN the system SHALL update the event_participant record to set gold_awarded to true, record the awarded_at timestamp, and send a reward notification
4. IF a student's participation is cancelled after gold was awarded THEN the system SHALL create a negative adjustment transaction to reclaim the gold and notify the student
5. WHEN gold is awarded THEN the system SHALL update the student's wallet balance immediately and ensure transaction atomicity
6. IF the gold award process fails THEN the system SHALL log the error, maintain data consistency, and allow for manual retry by administrators
7. WHEN processing rewards THEN the system SHALL validate that the student has not already received gold for the same event

### Requirement 5

**User Story:** As an administrator, I want to track event participation and completion rates, so that I can measure event success and improve future events.

#### Acceptance Criteria

1. WHEN an administrator views an event THEN the system SHALL display real-time participant counts for registered, checked_in, completed, and cancelled statuses
2. WHEN viewing event details THEN the system SHALL show the total gold rewards distributed, participation rate, and check-in statistics
3. WHEN accessing event reports THEN the system SHALL provide exportable participation data in CSV format with student details and timestamps
4. WHEN an event end time passes THEN the system SHALL automatically update the event status to "completed", set the completed_at timestamp, and process final rewards
5. IF an event has no participants THEN the system SHALL still allow marking it as completed manually with appropriate notifications
6. WHEN viewing event history THEN the system SHALL display all campus events with their participation statistics, organizer information, and financial impact

### Requirement 6

**User Story:** As an administrator, I want comprehensive notification management for events, so that students stay informed about event activities and updates.

#### Acceptance Criteria

1. WHEN an event is published THEN the system SHALL create notifications for all students in the campus using the existing notification system
2. WHEN a student registers for an event THEN the system SHALL send a registration confirmation notification
3. WHEN a student is checked into an event THEN the system SHALL send a check-in confirmation notification
4. WHEN a student receives gold rewards THEN the system SHALL send a reward notification with the amount earned
5. WHEN an event is cancelled THEN the system SHALL send cancellation notifications to all registered participants
6. WHEN an event is updated THEN the system SHALL send update notifications to registered participants if changes affect them
7. WHEN sending notifications THEN the system SHALL respect user notification preferences and provide opt-out mechanisms

### Requirement 7

**User Story:** As an administrator, I want to manually create events for past activities and add student participants with completion status, so that I can record historical events that occurred before the system was implemented and award appropriate gold rewards.

#### Acceptance Criteria

1. WHEN creating a manual event THEN the system SHALL allow setting past dates for start_time and end_time with appropriate validation warnings
2. WHEN creating a manual event THEN the system SHALL provide an option to mark the event as "historical" to bypass future date validation
3. WHEN adding students to a manual event THEN the system SHALL allow bulk selection of students from the campus roster
4. WHEN adding students to a manual event THEN the system SHALL allow setting their participation status directly to "completed" without requiring check-in
5. WHEN marking students as completed in a manual event THEN the system SHALL automatically award gold rewards according to the event's gold_reward_amount
6. WHEN processing manual event completions THEN the system SHALL create proper wallet transactions with source_type "event" and appropriate audit trails
7. WHEN creating manual events THEN the system SHALL clearly mark them as "manually created" in the event record for audit purposes
8. WHEN adding students to manual events THEN the system SHALL validate that students belong to the same campus as the event
9. WHEN processing manual gold awards THEN the system SHALL prevent duplicate rewards for the same student and event combination
10. WHEN viewing manual events THEN the system SHALL display them alongside regular events with clear indicators of their manual creation

### Requirement 8

**User Story:** As a system administrator, I want to manage event data integrity and security, so that the system operates reliably and maintains data consistency.

#### Acceptance Criteria

1. WHEN an event is created THEN the system SHALL validate that start_time is before end_time and location is specified
2. WHEN a regular event is created THEN the system SHALL validate that dates are in the future unless marked as historical
3. WHEN an event is created THEN the system SHALL ensure the QR code is unique across all events and properly formatted
4. IF a student account is deleted THEN the system SHALL maintain their event participation history for audit purposes while anonymizing personal data
5. WHEN managing events THEN the system SHALL maintain referential integrity with campus and student data
6. WHEN processing QR code scans THEN the system SHALL implement rate limiting and fraud detection to prevent abuse
7. WHEN awarding gold rewards THEN the system SHALL use database transactions to ensure data consistency and prevent double-spending
8. WHEN handling API requests THEN the system SHALL implement proper authentication, authorization, and input validation
9. WHEN storing event data THEN the system SHALL maintain audit logs for all critical operations and state changes

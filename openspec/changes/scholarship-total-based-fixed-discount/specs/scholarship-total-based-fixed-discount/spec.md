## ADDED Requirements

### Requirement: Fixed scholarships persist total-based calculation inputs
The system SHALL store total scholarship amount and total term count on scholarship definitions when the discount type is fixed amount.

#### Scenario: Create fixed scholarship with calculation inputs
- **WHEN** staff creates a fixed-amount scholarship with total amount `175000000` and total terms `9`
- **THEN** the scholarship definition stores total amount `175000000`, total terms `9`, and discount amount `19445000`

#### Scenario: Edit fixed scholarship calculation inputs
- **WHEN** staff edits a fixed-amount scholarship and changes total amount or total terms
- **THEN** the scholarship definition updates the stored calculation inputs and recalculates the discount amount from the new values

### Requirement: Fixed scholarship discount amount is derived by server-side rounding
The system SHALL calculate fixed scholarship discount amount as the total amount divided by total terms, rounded up to the next 1,000 VND.

#### Scenario: Round divided amount up to next thousand
- **WHEN** total amount is `175000000` and total terms is `9`
- **THEN** the calculated discount amount is `19445000`

#### Scenario: Exact thousand stays unchanged
- **WHEN** total amount divided by total terms is exactly `5000000`
- **THEN** the calculated discount amount is `5000000`

#### Scenario: Submitted amount disagrees with total-based calculation
- **WHEN** a fixed-amount scholarship request includes total amount, total terms, and a mismatched discount amount
- **THEN** the system persists the server-calculated discount amount

### Requirement: Fixed scholarship calculation inputs are validated
The system SHALL require positive total amount and positive total term count for fixed-amount scholarships.

#### Scenario: Missing total amount for fixed scholarship
- **WHEN** staff submits a fixed-amount scholarship without total amount
- **THEN** the system rejects the submission with a validation error for total amount

#### Scenario: Missing total terms for fixed scholarship
- **WHEN** staff submits a fixed-amount scholarship without total terms
- **THEN** the system rejects the submission with a validation error for total terms

#### Scenario: Non-positive total terms for fixed scholarship
- **WHEN** staff submits a fixed-amount scholarship with total terms less than `1`
- **THEN** the system rejects the submission with a validation error for total terms

### Requirement: Percentage scholarships ignore total-based fixed calculation fields
The system SHALL keep percentage scholarship amount behavior unchanged and SHALL NOT persist total-based fixed calculation inputs for percentage scholarships.

#### Scenario: Create percentage scholarship
- **WHEN** staff creates a percentage scholarship
- **THEN** the system stores the submitted percentage value as the discount amount and stores total amount and total terms as empty values

#### Scenario: Change fixed scholarship to percentage
- **WHEN** staff edits a fixed-amount scholarship and changes the discount type to percentage
- **THEN** the system clears the stored total amount and total terms and stores the percentage value as the discount amount

### Requirement: Scholarship pages display saved calculation context
The system SHALL display saved total amount and total terms for fixed-amount scholarships on scholarship detail and list surfaces where discount amount is shown.

#### Scenario: View fixed scholarship detail
- **WHEN** staff views a fixed-amount scholarship with saved total amount and total terms
- **THEN** the page shows the calculated discount amount, total amount, and total terms

#### Scenario: View scholarship list
- **WHEN** staff views the scholarship list containing a fixed-amount scholarship with saved total amount and total terms
- **THEN** the amount cell includes or exposes the saved total amount and total terms alongside the calculated discount amount

#### Scenario: View percentage scholarship
- **WHEN** staff views a percentage scholarship
- **THEN** the page does not show fixed-scholarship total amount or total terms as calculation context

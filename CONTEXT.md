---
title: Swinx Domain Glossary
status: canonical
owner: Platform Team
last_verified: 2026-07-25
scope: domain-language
---

# Swinx Domain Glossary

This file defines stable business vocabulary. It does not describe
implementation, task status, architecture, or change history.

## Academic Period

An institution-wide teaching and reporting period. Campuses may have local
schedule overlays without creating separate period identities.

## Actor

A person or service acting through Swinx. Student, guardian, lecturer, staff,
and service account are roles or access relationships, not bounded contexts.

## Application

An Admissions-owned record received from the admissions CRM or created by
staff. Approval converts the accepted intent into operational student,
identity, and enrollment records.

## Billing Account

The Finance-owned payer identity used by new Finance aggregates. A Student has
one billing account; an Applicant receives one only when a payable obligation
requires it.

## Campus

An institution-owned location identity. Physical spaces and payment-provider
codes belong to their owning contexts rather than to Campus itself.

## Course Offering

A scheduled delivery of a Unit in an Academic Period for a campus and cohort.
It owns roster, teaching, attendance, assessment, and completion operations.

## Course Result

The finalized or recalculated outcome of a Student in a Course Offering,
produced by Course Delivery & Assessment and consumed by Academic Progression.

## Decision

A formal governance document that may cover multiple Students and authorize
one or more lifecycle actions. Its roster and its authorized events serve
different audit purposes.

## Discount

A reduction of the fee itself. Discounts are distinct from cash payments and
from credits applied against an outstanding balance.

## Finance Obligation

Finance-owned truth describing why an amount is owed. Obligations are
materialized into the settlement ledger for collection and reporting.

## Guardian

A person with a real-world relationship to a Student. The relationship exists
independently of whether the Guardian has an account or portal access.

## Guardian Access Grant

Identity-owned permission allowing a Guardian account to access an authorized
Student context. Revoking access does not remove the Guardian relationship.

## Intended Program

The Program code requested by an applicant. It is distinct from the actual
Program Enrollment created during approval.

## Payable Line

The atomic settlement unit represented by an invoice line. Cash, discounts,
and credits are applied to payable lines to derive remaining collectible
amounts.

## Program Enrollment

The Academic-owned relationship between a Student, Program, Curriculum
Version, intake period, and enrollment lifecycle. It is separate from Student
Identity.

## Settlement Position

The Finance-owned derived view of gross amount, discount, applied cash,
applied credit, remaining collectible, and settlement state for an explicit
business scope.

## Student

The stable institutional identity of a learner. Account state, Program
Enrollment, and Study Stage are owned separately.

## Student Hub

The staff-facing student-centric operational console for viewing and acting on
one Student's academic lifecycle. Finance appears only through owned read
contracts and links to Finance operations.

## Study Stage

The Student's current academic progression stage, owned by Academic
Progression & Lifecycle rather than by Identity or the Student record.

## Transcript Entry

The Academic Progression-owned durable record derived from an accepted Course
Result and used for GPA, standing, best-attempt, and graduation decisions.

## Unit

An academic catalog item that may be delivered through one or more Course
Offerings.

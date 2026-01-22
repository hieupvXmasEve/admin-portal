# Project Map

## Status

- Phase: Blueprint (auto-filled for existing production system)
- Last updated: 2026-01-22

## Project Structure

- Backend: Laravel 12 modular monolith in `app/Modules/{Domain}/` (Actions, Queries, Http, Policies, Providers, routes).
- Shared models in `app/Models`, with domain services/commands/notifications in `app/`.
- Frontend: Vue 3 + Inertia pages in `resources/js/Pages/{Module}/{Entity}/`.
- Domain focus inferred: Academic, Finance (Invoices, Payments, Charges), Students (Academic Summary, Holds, Enrollments, Status), Scholarships, Vouchers, Surveys, Events, Rooms/Bookings, Programs, Semesters, Syllabus, Users/SystemConfig.

## North Star

- Keep the Student Portal stable and compliant while delivering incremental improvements to academic and finance workflows without disrupting production.

## Integrations

- Assumed integrations (based on modules; providers unknown):
    - Payment gateway(s) for invoices/payments.
    - Email/SMS delivery for notifications.
    - File storage for imports/exports and uploads.
- If multiple options exist, use the provider already configured in `.env` and config files.

## Source of Truth

- Primary data lives in the production relational database used by the Laravel app (Eloquent models and services are the authoritative source).

## Delivery Payload

- In-app UI updates (Inertia pages) and backend behavior changes deployed to the Student Portal.
- Operational outputs delivered as persisted records, exported files, or email/notification events.

## Behavioral Rules

- Maintain backward compatibility; prefer additive changes over breaking schema or API changes.
- No business logic in controllers; use Actions for writes and Queries for reads.
- Inertia pages do not call APIs directly; pass data via controller props.
- API endpoints return `ApiResponse` envelopes; frontend consumes via `useApi` and checks `success`.
- No cross-module Eloquent joins; use queries or module interfaces instead.
- Verify assumptions in code/config before changing behavior; never invent business rules.

## Data Schema

- Input: Existing Eloquent models, FormRequest-validated payloads, and import files (CSV/Excel).
- Output: Updated DB records, rendered Inertia props, API responses, and notifications.

## Assumptions

- Payment and notification providers exist but must be confirmed via `.env` and config.
- No new external integrations unless explicitly requested.

## Context Handoff

- Updated UI rules to standardize DataTable/DataPagination with useInertiaFilters.

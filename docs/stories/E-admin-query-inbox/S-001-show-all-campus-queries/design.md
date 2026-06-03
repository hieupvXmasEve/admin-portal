# Design

## Domain Model

Query inbox records are `responses` rows whose related `forms.type` is `query`. Department ownership comes from the related `form_targets.scope_type = department` and `scope_id`.

## Application Flow

The admin inbox index starts from current-campus query responses and applies only request filters such as `status` and `department_id`. It no longer applies logged-in-user membership or assignee filters to the result set.

## Interface Contract

`GET /forms/admin/inbox` keeps the same query parameters:

- `status`
- `department_id`

The `departments` prop is limited to active departments with code `HQ` or `ACA`.

## Data Model

No data model changes.

## UI / Platform Impact

The department dropdown remains present but is used only for filtering. The expected visible options are `Student HQ` and `Academic Service` when those departments exist and are active.

## Observability

No new logging.

## Alternatives Considered

1. Grant all users `view_all_queries`: rejected because the requested behavior is specific to this inbox and should not broaden other permission checks.
2. Keep user membership guard on selected departments: rejected because the department selector is now a convenience filter, not an authorization boundary.

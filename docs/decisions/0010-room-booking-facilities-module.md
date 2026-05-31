# 0010 Room Booking Facilities Module

Date: 2026-05-31

## Status

Accepted

## Context

Room booking had grown beyond a single legacy route/controller slice. The new flexible booking workflow needs a series creation action, availability queries, conflict preview, clone draft behavior, and a dedicated availability board while preserving the existing `/room-bookings` URLs and route names.

The repository architecture expects new business logic to live in `app/Modules/{Domain}`. Room booking belongs to Facilities alongside rooms and buildings, but the existing route file was still loaded from `routes/web/room-bookings.php` and pointed at the legacy controller namespace.

## Decision

Move the room booking web route ownership to `App\Modules\Facilities`:

- Add `App\Modules\Facilities\Providers\FacilitiesServiceProvider`.
- Load `app/Modules/Facilities/routes/web.php` from the module provider.
- Keep the public route names and URLs stable for existing screens.
- Add the new availability board and series preview routes in the module route file.
- Put new series creation, availability, clone, validation, and request classes under `app/Modules/Facilities`.
- Leave legacy services and models in place where they are still shared by existing single-booking operations.

## Alternatives Considered

1. Keep adding route/controller behavior to the legacy `routes/web/room-bookings.php` and `App\Http\Controllers\RoomBookingController`. Rejected because the feature adds new business logic and queries that should follow module boundaries.
2. Split only the new endpoints into a module and keep existing room booking routes in the legacy file. Rejected because it creates two owners for one user workflow.
3. Move room booking routes to the module while preserving the existing URLs and route names. Accepted because it improves ownership without breaking existing navigation or Ziggy consumers.

## Consequences

Positive:

- New room booking behavior now follows the module structure expected by the codebase.
- Existing route names and URLs stay compatible with current Inertia pages.
- The availability and series preview endpoints live beside their actions, queries, and requests.

Tradeoffs:

- The legacy controller/service path still exists for some existing operations until a future cleanup migrates the remaining room booking behavior fully into the module.
- Ziggy must be regenerated when module routes change.

## Follow-Up

- Consider moving the remaining legacy room booking service methods into Facilities actions/queries when the next booking workflow change touches them.

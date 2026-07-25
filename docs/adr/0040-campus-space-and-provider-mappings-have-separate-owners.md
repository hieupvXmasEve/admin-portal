---
id: ADR-0040
title: "Campus, space, and provider mappings have separate owners"
status: accepted
owner: Platform Team
last_verified: 2026-07-25
scope: architecture-decision
---

# Campus, space, and provider mappings have separate owners

Institution & Organization owns the canonical Institution Campus and organizational departments; Facilities owns Buildings, Rooms, physical capacity, Space Availability, and reservations; Finance owns DNG Campus Mapping and other payment-provider identifiers. Academic contexts consume neutral Campus and space references, so provider metadata does not remain part of Campus identity and Facilities does not import Academic scheduling services.

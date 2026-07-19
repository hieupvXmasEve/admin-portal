# Academic decomposes logically before physical extraction

**Status:** accepted

The Academic domain is decomposed into Academic Catalog & Calendar, Course Delivery & Assessment, and Academic Progression & Lifecycle. These Bounded Contexts first gain explicit ownership, internal namespaces, contracts, and architecture tests inside the current Academic Module; they become separate top-level Modules only after dependencies are clean, avoiding a big-bang model, schema, route, or portal-contract move.

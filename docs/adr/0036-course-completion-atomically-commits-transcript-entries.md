# Course Completion atomically commits Transcript Entries

**Status:** accepted

Finalize and Recalculate synchronously pass Course Result DTOs from Course Delivery & Assessment to Academic Progression & Lifecycle and commit the corresponding Transcript Entries in the same database transaction as Course Completion. A failure rolls back the offering transition; notifications and integration events use post-commit outbox delivery, and eventual consistency is allowed only with an explicit pending-transcript lifecycle if the contexts later become separate services.

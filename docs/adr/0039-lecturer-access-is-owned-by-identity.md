# Lecturer access is owned by Identity

**Status:** accepted

Faculty Workforce owns Faculty Access Eligibility derived from employment and contract state, while Identity & Access owns Account Status and the Lecturer Access Grant used by login and token refresh. Termination, suspension, or contract expiry synchronously revokes the grant through an Identity contract, with durable outbox delivery and reconciliation guarding against drift; leave and sabbatical do not revoke access by default and require an explicit policy.

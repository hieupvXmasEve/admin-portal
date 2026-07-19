# Account, Program Enrollment, and Study Stage are separate state machines

**Status:** accepted

Swinx does not use one Student status to represent authentication, enrollment lifecycle, and academic progression. Identity & Access owns Account Status, Academic Progression & Lifecycle owns Program Enrollment Status and Study Stage, and Student Identity carries none of those states; cross-context gates ask the owning context instead of interpreting a shared enum.

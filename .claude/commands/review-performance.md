Review only the current working set for:

- N+1 query problems (e.g., missing `->with()`)
- Missing indexes on filter fields
- Inefficient loops or repeated queries
- Heavy operations in controllers instead of services
- Redundant computations or eager loading

Rules:
- Only use known model relationships
- Follow CLAUDE.md output format and service-first design
- Highlight lines that cause wasteful DB/API calls

$ARGUMENTS
!git status


---
name: handoff
description: Create context handoff for session continuation
allowed-tools: Read, Write, Glob
---

/handoff
Save state for continuation in new chat (use when context ~10-15% remaining).

Execute
Summarize current project/phase

Note key files and decisions

Save to .claude/handoffs/handoff-[date]-[time].md

Provide continuation prompt

Handoff Format
# Context Handoff - [Date]

## Current Project / Plan
## Current Phase
## Work Completed This Session
## Key Files
## Decisions Made
## Next Steps
## Continuation Prompt
Output
Handoff saved to: .claude/handoffs/handoff-YYYY-MM-DD-HHMM.md

To continue, paste:
---
Resume from handoff: [path]
Context: [brief]
Next: [action]
---
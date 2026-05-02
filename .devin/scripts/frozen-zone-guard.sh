#!/usr/bin/env bash
# frozen-zone-guard.sh
# PreToolUse/write — Block creating NEW files in frozen zones.
# Existing files (bug fix in-place) are allowed.
#
# Frozen zones:
#   app/Services/           — legacy service classes
#   app/Http/Controllers/   — legacy controllers (except base Controller.php)
#   routes/web/             — legacy route files
#   routes/api/             — legacy route files
#
# Logic: if file path is in a frozen zone AND file does not exist yet → block.

set -euo pipefail

INPUT=$(cat)

FILE_PATH=$(echo "$INPUT" | python3 -c "
import sys, json
data = json.load(sys.stdin)
inp = data.get('tool_input', {})
# write tool uses 'file_path', edit uses 'file_path'
print(inp.get('file_path', inp.get('path', '')))
" 2>/dev/null || echo "")

if [[ -z "$FILE_PATH" ]]; then
  exit 0
fi

# Resolve absolute path
PROJECT_DIR="${DEVIN_PROJECT_DIR:-$(pwd)}"
if [[ "$FILE_PATH" != /* ]]; then
  ABS_PATH="$PROJECT_DIR/$FILE_PATH"
else
  ABS_PATH="$FILE_PATH"
fi

# Only block NEW files — existing files are bug-fix-in-place (allowed)
if [[ -f "$ABS_PATH" ]]; then
  exit 0
fi

# Normalize to relative path from project root for pattern matching
REL_PATH="${FILE_PATH#$PROJECT_DIR/}"
REL_PATH="${REL_PATH#./}"

# Check frozen zone patterns
FROZEN=false
ZONE=""

if [[ "$REL_PATH" =~ ^app/Services/[A-Za-z] ]]; then
  FROZEN=true
  ZONE="app/Services/"
elif [[ "$REL_PATH" =~ ^app/Http/Controllers/[A-Za-z] ]] && [[ "$REL_PATH" != "app/Http/Controllers/Controller.php" ]]; then
  FROZEN=true
  ZONE="app/Http/Controllers/"
elif [[ "$REL_PATH" =~ ^routes/web/ ]] || [[ "$REL_PATH" =~ ^routes/api/ ]]; then
  FROZEN=true
  ZONE="routes/web/ or routes/api/"
fi

if [[ "$FROZEN" == "true" ]]; then
  echo "{\"decision\": \"block\", \"reason\": \"FROZEN ZONE: '$ZONE' is frozen — new files are not allowed here.\\n\\nWhere new code must go:\\n  Business logic  → app/Modules/{Domain}/Actions/VerbEntityAction.php\\n  Read queries    → app/Modules/{Domain}/Queries/VerbEntityQuery.php\\n  Web controller  → app/Modules/{Domain}/Http/Web/Admin/EntityController.php\\n  API controller  → app/Modules/{Domain}/Http/Api/Student/EntityController.php\\n  New routes      → app/Modules/{Domain}/routes/web.php or api.php\\n\\nSee: docs/rules/frozen-zones.md\"}"
  exit 2
fi

exit 0

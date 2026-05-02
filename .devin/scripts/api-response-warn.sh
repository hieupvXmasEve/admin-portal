#!/usr/bin/env bash
# api-response-warn.sh
# PostToolUse/edit|write — Warn (non-blocking) when a PHP file that was just
# written contains response()->json( directly.
#
# Correct pattern: ApiResponse::success($data) or ApiResponse::error(...)
# Wrong pattern:   response()->json([...])
#
# Does NOT block (exit 0 always) — only injects a context message so the agent
# self-corrects before moving on.

set -euo pipefail

INPUT=$(cat)

FILE_PATH=$(echo "$INPUT" | python3 -c "
import sys, json
data = json.load(sys.stdin)
inp = data.get('tool_input', {})
print(inp.get('file_path', inp.get('path', '')))
" 2>/dev/null || echo "")

# Only check PHP files
if [[ -z "$FILE_PATH" ]] || [[ "$FILE_PATH" != *.php ]]; then
  exit 0
fi

PROJECT_DIR="${DEVIN_PROJECT_DIR:-$(pwd)}"
if [[ "$FILE_PATH" != /* ]]; then
  ABS_PATH="$PROJECT_DIR/$FILE_PATH"
else
  ABS_PATH="$FILE_PATH"
fi

if [[ ! -f "$ABS_PATH" ]]; then
  exit 0
fi

# Count occurrences of the forbidden pattern
MATCHES=$(grep -c "response()->json(" "$ABS_PATH" 2>/dev/null | tr -d '[:space:]' || echo "0")
MATCHES="${MATCHES:-0}"

if [[ "$MATCHES" -gt 0 ]]; then
  REL_PATH="${FILE_PATH#$PROJECT_DIR/}"
  REL_PATH="${REL_PATH#./}"
  echo "{\"decision\": \"approve\", \"reason\": \"API_RESPONSE WARNING in $REL_PATH: found $MATCHES use(s) of response()->json() which is forbidden.\\n\\nFix before finishing:\\n  WRONG:   return response()->json(['data' => \$data]);\\n  CORRECT: return ApiResponse::success(\$data);\\n  CORRECT: return ApiResponse::error('Message', 422);\\n\\nReplace all occurrences before this task is done.\"}"
fi

exit 0

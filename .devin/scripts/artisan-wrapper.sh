#!/usr/bin/env bash
# artisan-wrapper.sh
# PreToolUse/exec — Block direct `php artisan` calls.
# Project runs in Docker — always use ./scripts/dev.sh artisan <cmd> instead.
#
# Allowed passthrough:
#   ./scripts/dev.sh artisan ...   ← correct wrapper
#   Any non-artisan command        ← not our concern

set -euo pipefail

INPUT=$(cat)

COMMAND=$(echo "$INPUT" | python3 -c "
import sys, json
data = json.load(sys.stdin)
print(data.get('tool_input', {}).get('command', ''))
" 2>/dev/null || echo "")

if [[ -z "$COMMAND" ]]; then
  exit 0
fi

# Match: php artisan ... (with optional leading whitespace or env vars)
# Does NOT match: ./scripts/dev.sh artisan (correct form)
if echo "$COMMAND" | grep -qE '(^|[;&|]\s*|`\s*)php\s+artisan\b'; then
  echo "{\"decision\": \"block\", \"reason\": \"DOCKER WRAPPER REQUIRED: Direct 'php artisan' is blocked — the project runs inside Docker.\\n\\nUse the wrapper instead:\\n  WRONG:   php artisan migrate\\n  CORRECT: ./scripts/dev.sh artisan migrate\\n\\nOther wrapper commands:\\n  ./scripts/dev.sh composer <cmd>\\n  ./scripts/dev.sh npm <cmd>\\n  ./scripts/dev.sh test\\n  ./scripts/dev.sh start\\n\\nSee: AGENTS.md — Commands section\"}"
  exit 2
fi

exit 0

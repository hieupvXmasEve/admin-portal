#!/bin/bash
set -euo pipefail

# === Usage ===
# ./scripts/deploy-fe.sh              # deploy all schools (student + lecturer)
# ./scripts/deploy-fe.sh all          # same as above
# ./scripts/deploy-fe.sh asia         # deploy only one school (student + lecturer)

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
SCHOOLS_FILE="$SCRIPT_DIR/schools.list"
TARGET="${1:-all}"

if [ ! -f "$SCHOOLS_FILE" ]; then
  echo "❌ Error: $SCHOOLS_FILE not found."
  exit 1
fi

# mapfile is bash4+; macOS ships bash 3.2, so read schools.list manually
ALL_SCHOOLS=()
while IFS= read -r line || [ -n "$line" ]; do
  [ -n "$line" ] && ALL_SCHOOLS+=("$line")
done < "$SCHOOLS_FILE"

if [ "$TARGET" = "all" ]; then
  SCHOOLS=("${ALL_SCHOOLS[@]}")
else
  if [[ ! " ${ALL_SCHOOLS[*]} " == *" $TARGET "* ]]; then
    echo "❌ Invalid school: $TARGET"
    echo "Available: ${ALL_SCHOOLS[*]}"
    exit 1
  fi
  SCHOOLS=("$TARGET")
fi

for school in "${SCHOOLS[@]}"; do
  for app in student-nuxt lecturer-nuxt; do
    echo "=== Deploying $app ($school) ==="
    (cd "$ROOT_DIR/FE/$app" && ./deploy.sh "$school")
  done
done

echo "✅ All deploys completed: ${SCHOOLS[*]}"

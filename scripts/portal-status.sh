#!/usr/bin/env bash
set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

portals=(
  "student:FE/student-nuxt:/api/v1/student"
  "lecturer:FE/lecturer-nuxt:/api/v1/lecturer"
)

echo "Swinx repo: ${repo_root}"
echo
echo "Parent repo status:"
git -C "${repo_root}" status --short
echo

for portal in "${portals[@]}"; do
  IFS=":" read -r name path api_base <<< "${portal}"
  abs_path="${repo_root}/${path}"

  echo "${name} portal"
  echo "  path: ${path}"
  echo "  api:  ${api_base}"

  if [[ ! -d "${abs_path}" ]]; then
    echo "  status: missing"
    echo
    continue
  fi

  if git -C "${repo_root}" check-ignore -q "${path}/package.json"; then
    echo "  swinx gitignore: ignored"
  else
    echo "  swinx gitignore: NOT ignored"
  fi

  if git -C "${abs_path}" rev-parse --show-toplevel >/dev/null 2>&1; then
    echo "  nested git: $(git -C "${abs_path}" rev-parse --show-toplevel)"
    echo "  status:"
    git -C "${abs_path}" status --short | sed 's/^/    /'
  else
    echo "  nested git: not a git repository"
  fi

  echo
done

cat <<'EOF'
Validation commands when portal code changes:
  cd FE/student-nuxt && pnpm lint && pnpm typecheck && pnpm build
  cd FE/lecturer-nuxt && pnpm lint && pnpm typecheck && pnpm build

Workflow:
  Read docs/portal-repos.md before changing student or lecturer API contracts.
EOF

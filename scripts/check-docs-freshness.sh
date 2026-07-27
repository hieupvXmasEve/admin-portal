#!/usr/bin/env bash
# Warn when source files documented by a user-guide page change without the
# page being updated in the same set of changes.
#
# Each page under docs-site/src/content/docs/ may declare in its frontmatter:
#
#   source:
#     - resources/js/pages/Programs/Index.vue
#
# Usage:
#   scripts/check-docs-freshness.sh              # compare against origin/main
#   scripts/check-docs-freshness.sh <base-ref>   # compare against a given ref
#
# Exit codes: 0 = no stale pages (or nothing to compare), 1 = stale pages found.
set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
base_ref="${1:-origin/main}"

cd "$repo_root"

if ! git rev-parse --verify --quiet "$base_ref" >/dev/null; then
  echo "check-docs-freshness: base ref '$base_ref' not found, skipping." >&2
  exit 0
fi

changed_files="$(git diff --name-only "$base_ref"...HEAD)"

if [[ -z "$changed_files" ]]; then
  echo "check-docs-freshness: no changes against $base_ref."
  exit 0
fi

python3 - "$repo_root" <<PY
from __future__ import annotations

import subprocess
import sys
from pathlib import Path

root = Path(sys.argv[1])
base_ref = "$base_ref"

changed = set(
    subprocess.run(
        ["git", "diff", "--name-only", f"{base_ref}...HEAD"],
        cwd=root,
        capture_output=True,
        text=True,
        check=True,
    ).stdout.split()
)

docs_root = root / "docs-site/src/content/docs"
stale: list[tuple[str, list[str]]] = []
anchored = 0

for page in sorted(docs_root.rglob("*.md")):
    lines = page.read_text(encoding="utf-8").splitlines()
    if not lines or lines[0] != "---":
        continue

    try:
        end = lines.index("---", 1)
    except ValueError:
        continue

    sources: list[str] = []
    in_source_block = False

    for line in lines[1:end]:
        if line.startswith("source:"):
            in_source_block = True
            continue
        if in_source_block:
            stripped = line.strip()
            if stripped.startswith("- "):
                sources.append(stripped[2:].strip())
                continue
            # Any other key ends the source block.
            if line and not line.startswith((" ", "\t")):
                in_source_block = False

    if not sources:
        continue

    anchored += 1
    relative_page = page.relative_to(root).as_posix()

    if relative_page in changed:
        continue

    touched = [src for src in sources if src in changed]
    if touched:
        stale.append((relative_page, touched))

if not anchored:
    print("check-docs-freshness: no pages declare a source anchor.")
    sys.exit(0)

if not stale:
    print(f"check-docs-freshness: {anchored} anchored page(s), all current.")
    sys.exit(0)

print("check-docs-freshness: user guide pages may be out of date.\n")
for relative_page, touched in stale:
    print(f"  {relative_page}")
    for src in touched:
        print(f"    changed: {src}")
    print()

print("Update these pages in this change, or drop the stale path from their")
print("'source:' list if the page no longer documents that file.")
sys.exit(1)
PY
